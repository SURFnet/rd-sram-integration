<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

namespace OCA\OpenCloudMesh\AppInfo;

use OC\AppFramework\Utility\SimpleContainer;
use OCP\AppFramework\App;
use OCP\IRequest;
use GuzzleHttp\Exception\ServerException;
use OCP\AppFramework\Http;
use OCP\Share\Events\AcceptShare;
use OCP\Share\Events\DeclineShare;
use OCP\Util;
use OCP\IContainer;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\OpenCloudMesh\FederatedFileSharing\FedGroupShareManager;
use OCA\OpenCloudMesh\FederatedFileSharing\FedUserShareManager;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCA\OpenCloudMesh\FederatedFileSharing\UserNotifications;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
use OCA\OpenCloudMesh\FederatedUserShareProvider;
use OCA\OpenCloudMesh\Controller\OcmController;
use OCA\OpenCloudMesh\Files_Sharing\Middleware\RemoteOcsMiddleware;
use OCA\OpenCloudMesh\ShareProviderFactory;

class Application extends App {
	private $isProviderRegistered = false;

	/** @var FederatedGroupShareProvider */
	protected $federatedGroupShareProvider;

	/** @var FederatedUserShareProvider */
	protected $federatedUserShareProvider;
	
	public function __construct(array $urlParams = []) {
		parent::__construct('opencloudmesh', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('OCA\\OpenCloudMesh\\GroupExternalManager', function (SimpleContainer $c) use ($server) {
			$user = $server->getUserSession()->getUser();
			$uid = $user ? $user->getUID() : null;
			return new \OCA\OpenCloudMesh\Files_Sharing\External\Manager(
				$server->getDatabaseConnection(),
				\OC\Files\Filesystem::getMountManager(),
				\OC\Files\Filesystem::getLoader(),
				$server->getNotificationManager(),
				$server->getEventDispatcher(),
				$uid
			);
		});

		$container->registerService('GroupNotifications', function (SimpleContainer $c) use ($server) {
			$addressHandler = new AddressHandler(
				\OC::$server->getURLGenerator(),
				\OC::$server->getL10N('federatedfilesharing')
			);
			$discoveryManager = new DiscoveryManager(
				\OC::$server->getMemCacheFactory(),
				\OC::$server->getHTTPClientService()
			);
			$permissions = new Permissions();
			$notificationManager = new NotificationManager(
				$permissions
			);
			return new GroupNotifications(
				$addressHandler,
				\OC::$server->getHTTPClientService(),
				$discoveryManager,
				$notificationManager,
				\OC::$server->getJobList(),
				\OC::$server->getConfig()
			);
		});

		$container->registerService('ExternalMountProvider', function (IContainer $c) {
			/** @var \OCP\IServerContainer $server */
			$server = $c->query('ServerContainer');
			return new \OCA\OpenCloudMesh\Files_Sharing\External\MountProvider(
				$server->getDatabaseConnection(),
				function () use ($c) {
					return $c->query('OCA\\OpenCloudMesh\\GroupExternalManager');
				}
			);
		});

		$container->registerService(
			'OCA\\OpenCloudMesh\\FederatedFileSharing\\FedGroupShareManager',
			function ($c) use ($server) {
				$addressHandler = new AddressHandler(
					\OC::$server->getURLGenerator(),
					\OC::$server->getL10N('federatedfilesharing')
				);
				$permissions = new Permissions();

				return new FedGroupShareManager(
					$this->getFederatedGroupShareProvider(),
					$c->query('GroupNotifications'),
					$server->getUserManager(),
					$server->getActivityManager(),
					$server->getNotificationManager(),
					$addressHandler,
					$permissions,
					$server->getEventDispatcher()
				);
			}
		);

		$container->registerService(
			'OCA\\OpenCloudMesh\\FederatedFileSharing\\FedUserShareManager',
			function ($c) use ($server) {
				$addressHandler = new AddressHandler(
					\OC::$server->getURLGenerator(),
					\OC::$server->getL10N('federatedfilesharing')
				);
				$discoveryManager = new DiscoveryManager(
					\OC::$server->getMemCacheFactory(),
					\OC::$server->getHTTPClientService()
				);
				$permissions = new Permissions();
				$notificationManager = new NotificationManager(
					$permissions
				);
				$notifications = new UserNotifications(
					$addressHandler,
					\OC::$server->getHTTPClientService(),
					$discoveryManager,
					$notificationManager,
					\OC::$server->getJobList(),
					\OC::$server->getConfig()
				);

				return new FedUserShareManager(
					$this->getFederatedUserShareProvider(),
					$notifications,
					$server->getUserManager(),
					$server->getActivityManager(),
					$server->getNotificationManager(),
					$addressHandler,
					$permissions,
					$server->getEventDispatcher()
				);
			}
		);

		$container->registerService(
			'OCA\\OpenCloudMesh\\Controller\\OcmController',
			function ($c) use ($server) {
				$app = new \OCA\FederatedFileSharing\AppInfo\Application();
				$appContainer = $app->getContainer();

				return new OcmController(
					$c->query('AppName'),
					$c->query('Request'),
					$appContainer->query('OcmMiddleware'),
					$server->getURLGenerator(),
					$server->getUserManager(),
					$appContainer->query('AddressHandler'),
					$c->query('OCA\\OpenCloudMesh\\FederatedFileSharing\\FedGroupShareManager'),
					$c->query('OCA\\OpenCloudMesh\\FederatedFileSharing\\FedUserShareManager'),
					$server->getLogger(),
					$server->getConfig()
				);
			}
		);
	}

	/**
	 * get instance of federated share provider
	 *
	 * @return FederatedGroupShareProvider
	 */
	public function getFederatedGroupShareProvider() {
		if ($this->federatedGroupShareProvider === null) {
			$this->initFederatedGroupShareProvider();
		}
		return $this->federatedGroupShareProvider;
	}

	/**
	 * get instance of federated share provider
	 *
	 * @return FederatedUserShareProvider
	 */
	public function getFederatedUserShareProvider() {
		if ($this->federatedUserShareProvider === null) {
			$this->initFederatedUserShareProvider();
		}
		return $this->federatedUserShareProvider;
	}

	/**
	 * initialize federated share provider
	 */
	protected function initFederatedGroupShareProvider() {
		$addressHandler = new AddressHandler(
			\OC::$server->getURLGenerator(),
			\OC::$server->getL10N('federatedfilesharing')
		);
		$discoveryManager = new DiscoveryManager(
			\OC::$server->getMemCacheFactory(),
			\OC::$server->getHTTPClientService()
		);
		$notificationManager = new NotificationManager(
			new Permissions()
		);
		$notifications = new GroupNotifications(
			$addressHandler,
			\OC::$server->getHTTPClientService(),
			$discoveryManager,
			$notificationManager,
			\OC::$server->getJobList(),
			\OC::$server->getConfig()
		);
		$tokenHandler = new TokenHandler(
			\OC::$server->getSecureRandom()
		);

		$this->federatedGroupShareProvider = new FederatedGroupShareProvider(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getEventDispatcher(),
			$addressHandler,
			$notifications,
			$tokenHandler,
			\OC::$server->getL10N('federatedfilesharing'),
			\OC::$server->getLogger(),
			\OC::$server->getLazyRootFolder(),
			\OC::$server->getConfig(),
			\OC::$server->getUserManager(),
			$this->getContainer()->query('OCA\\OpenCloudMesh\\ShareProviderFactory'),
			function () {
				return $this->getContainer()->query('OCA\\OpenCloudMesh\\GroupExternalManager');
			}
		);
	}

	/**
	 * initialize federated share provider
	 */
	protected function initFederatedUserShareProvider() {
		$addressHandler = new AddressHandler(
			\OC::$server->getURLGenerator(),
			\OC::$server->getL10N('federatedfilesharing')
		);
		$discoveryManager = new DiscoveryManager(
			\OC::$server->getMemCacheFactory(),
			\OC::$server->getHTTPClientService()
		);
		$notificationManager = new NotificationManager(
			new Permissions()
		);
		$notifications = new UserNotifications(
			$addressHandler,
			\OC::$server->getHTTPClientService(),
			$discoveryManager,
			$notificationManager,
			\OC::$server->getJobList(),
			\OC::$server->getConfig()
		);
		$tokenHandler = new TokenHandler(
			\OC::$server->getSecureRandom()
		);

		$this->federatedUserShareProvider = new FederatedUserShareProvider(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getEventDispatcher(),
			$addressHandler,
			$notifications,
			$tokenHandler,
			\OC::$server->getL10N('federatedfilesharing'),
			\OC::$server->getLogger(),
			\OC::$server->getLazyRootFolder(),
			\OC::$server->getConfig(),
			\OC::$server->getUserManager(),
			$this->getContainer()->query('OCA\\OpenCloudMesh\\ShareProviderFactory'),
			function () {
				$sharingApp = new \OCA\Files_Sharing\AppInfo\Application();
				$externalManager = $sharingApp->getContainer()->query('ExternalManager');
				return $externalManager;
			}
		);
	}

	public function registerMountProviders() {
		// We need to prevent adding providers more than once
		// Doing this on MountProviderCollection level makes a lot tests to fail
		if ($this->isProviderRegistered === false) {
			/** @var \OCP\IServerContainer $server */
			$server = $this->getContainer()->query('ServerContainer');
			$mountProviderCollection = $server->getMountProviderCollection();
			$mountProviderCollection->registerProvider($this->getContainer()->query('ExternalMountProvider'));
			$this->isProviderRegistered = true;
		}
	}
} 

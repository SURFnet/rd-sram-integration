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
use OCA\OpenCloudMesh\FederatedFileSharing\FedShareManager;
use OCA\OpenCloudMesh\FederatedFileSharing\Notifications;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
use OCA\OpenCloudMesh\Files_Sharing\Middleware\RemoteOcsMiddleware;

class Application extends App {
	private $isProviderRegistered = false;

	/** @var FederatedGroupShareProvider */
	protected $federatedGroupShareProvider;
	
	public function __construct(array $urlParams = []) {
		parent::__construct('opencloudmesh', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('GroupExternalManager', function (SimpleContainer $c) use ($server) {
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

		$container->registerService('ExternalMountProvider', function (IContainer $c) {
			/** @var \OCP\IServerContainer $server */
			$server = $c->query('ServerContainer');
			return new \OCA\OpenCloudMesh\Files_Sharing\External\MountProvider(
				$server->getDatabaseConnection(),
				function () use ($c) {
					$sharingApp = new \OCA\Files_Sharing\AppInfo\Application();
					$externalManager = $sharingApp->getContainer()->query('ExternalManager');
					return $externalManager;
				}
			);
		});

		$container->registerService('OCA\\OpenCloudMesh\\Files_Sharing\\Middleware\\RemoteOcsMiddleware', function (SimpleContainer $c) use ($container) {
			$sharingApp = new \OCA\Files_Sharing\AppInfo\Application();
			$externalManager = $sharingApp->getContainer()->query('ExternalManager');
			return new RemoteOcsMiddleware(
				$externalManager,
				$container->query('GroupExternalManager')
			);
		});

		$container->registerService(
			'OCA\\OpenCloudMesh\\FederatedFileSharing\\FedShareManager',
			function ($c) use ($server) {
				error_log('before event dispatcher');
				$ed = $server->getEventDispatcher();
				error_log('after event dispatcher> '.get_class($ed));

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
				$notifications = new Notifications(
					$addressHandler,
					\OC::$server->getHTTPClientService(),
					$discoveryManager,
					$notificationManager,
					\OC::$server->getJobList(),
					\OC::$server->getConfig()
				);

				return new FedShareManager(
					$this->getFederatedGroupShareProvider(),
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
	}

	/**
	 * get instance of federated share provider
	 *
	 * @return FederatedShareProvider
	 */
	public function getFederatedGroupShareProvider() {
		if ($this->federatedGroupShareProvider === null) {
			$this->initFederatedGroupShareProvider();
		}
		return $this->federatedGroupShareProvider;
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
		$notifications = new Notifications(
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
			function () {
				return $this->getContainer()->query('GroupExternalManager');
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

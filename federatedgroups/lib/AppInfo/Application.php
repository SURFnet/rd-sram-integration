<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

namespace OCA\FederatedGroups\AppInfo;

use OC\AppFramework\Utility\SimpleContainer;
use OCP\AppFramework\App;
use OCP\IRequest;
use GuzzleHttp\Exception\ServerException;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Command\PollIncomingShares;
use OCA\FederatedFileSharing\Controller\OcmController;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedGroups\FederatedFileSharing\FederatedShareProvider;
use OCA\FederatedGroups\FederatedFileSharing\FedShareManager;
use OCA\FederatedGroups\FederatedFileSharing\Middleware\OcmMiddleware;
use OCA\FederatedFileSharing\Controller\RequestHandlerController;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedGroups\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;
use OCP\AppFramework\Http;
use OCP\Share\Events\AcceptShare;
use OCP\Share\Events\DeclineShare;
use OCP\Util;
use OCP\IContainer;
use OCA\FederatedGroups\FilesSharing\External\MountProvider;


class Application extends App {
	private $isProviderRegistered = false;

	public function __construct(array $urlParams = []) {
		error_log("fg: ". get_parent_class($this));
		parent::__construct('federatedgroups', $urlParams);
		$container = $this->getContainer();
		$server = $container->getServer();


		$container->registerService('ExternalGroupManager', function (SimpleContainer $c) use ($server) {
			$user = $server->getUserSession()->getUser();
			$uid = $user ? $user->getUID() : null;
			return new \OCA\FederatedGroups\FilesSharing\External\Manager(
				$server->getDatabaseConnection(),
				\OC\Files\Filesystem::getMountManager(),
				\OC\Files\Filesystem::getLoader(),
				$server->getNotificationManager(),
				$server->getEventDispatcher(),
				$uid
			);
		});
		// FIXME: https://github.com/SURFnet/rd-sram-integration/issues/71
		$container->registerService('ExternalGroupMountProvider', function (IContainer $c) {
		/** @var \OCP\IServerContainer $server */
		 	$server = $c->query('ServerContainer');
		 	return new \OCA\FederatedGroups\FilesSharing\External\MountProvider(
		 		$server->getDatabaseConnection(),
		 		function () use ($c) {
		 			return $c->query('ExternalGroupManager');
		 		}
		 	);
		});
	}

	public function registerMountProviders() {
		// We need to prevent adding providers more than once
		// Doing this on MountProviderCollection level makes a lot tests to fail
		if ($this->isProviderRegistered === false) {
			/** @var \OCP\IServerContainer $server */
			$server = $this->getContainer()->query('ServerContainer');
			$mountProviderCollection = $server->getMountProviderCollection();
			$mountProviderCollection->registerProvider($this->getContainer()->query('ExternalGroupMountProvider'));
			$this->isProviderRegistered = true;
		}
	}

	public static function getRemoteOcsController(
		IRequest $request,
		\OCA\Files_Sharing\External\Manager $externalManager,
		$uid
	) {
		$a = \OC::$server->getDatabaseConnection();
		$b = \OC\Files\Filesystem::getMountManager();
		$c = \OC\Files\Filesystem::getLoader();
		$d = \OC::$server->getNotificationManager();
		$e = \OC::$server->getEventDispatcher();
		$f = $uid;
		$externalGroupManager = new \OCA\FederatedGroups\FilesSharing\External\Manager($a, $b, $c, $d, $e, $f);
		$controller = new \OCA\FederatedGroups\FilesSharing\Controller\RemoteOcsController(
			'files_sharing',
			$request,
			$externalManager,
			$externalGroupManager,
			$uid
		);
		return $controller;
	}

	

	public static function getOcmController(
		IRequest $request
	) {
		$appManager = \OC::$server->getAppManager();
		$userManager = \OC::$server->getUserManager();
		$logger = \OC::$server->getLogger();
		$urlGenerator = \OC::$server->getURLGenerator();
		$l10n = \OC::$server->getL10N('federatedfilesharing');
		$addressHandler = new AddressHandler(
			$urlGenerator,
			$l10n
		);
		$httpClientService = \OC::$server->getHTTPClientService();
		$memCacheFactory = \OC::$server->getMemCacheFactory();
		$discoveryManager = new DiscoveryManager(
			$memCacheFactory,
			$httpClientService
		);
		$permissions = new Permissions();
		$notificationManagerFFS = new NotificationManager($permissions);
		$notificationManagerServer = \OC::$server->getNotificationManager();
		$jobList = \OC::$server->getJobList();
		$config = \OC::$server->getConfig();
		$notifications = new Notifications(
			$addressHandler,
			$httpClientService,
			$discoveryManager,
			$notificationManagerFFS,
			$jobList,
			$config
		);
		$secureRandom = \OC::$server->getSecureRandom();
		$tokenHandler = new TokenHandler(
			$secureRandom
		);
		$databaseConnection = \OC::$server->getDatabaseConnection();
		$eventDispatcher = \OC::$server->getEventDispatcher();
		$lazyFolderRoot = \OC::$server->getLazyRootFolder();
		$federatedShareProvider = new FederatedShareProvider(
			$databaseConnection,
			$eventDispatcher,
			$addressHandler,
			$notifications,
			$tokenHandler,
			$l10n,
			$logger,
			$lazyFolderRoot,
			$config,
			$userManager
		);
		$ocmMiddleware = new OcmMiddleware(
			$federatedShareProvider,
			$appManager,
			$userManager,
			$addressHandler,
			$logger
		);
		$notifications = new Notifications(
			$addressHandler,
			\OC::$server->getHTTPClientService(),
			$discoveryManager,
			$notificationManagerFFS,
			\OC::$server->getJobList(),
			\OC::$server->getConfig()
		);
		$activityManager = \OC::$server->getActivityManager();
		$fedShareManager = new FedShareManager(
			$federatedShareProvider,
			$notifications,
			$userManager,
			$activityManager,
			$notificationManagerServer,
			$addressHandler,
			$permissions,
			$eventDispatcher
		);
		$user = \OC::$server->getUserSession()->getUser();
		$uid = $user ? $user->getUID() : null;
		return new \OCA\FederatedGroups\Controller\OcmController(
			'federatedgroups',
			$request,
			$ocmMiddleware,
			$urlGenerator,
			$userManager,
			$addressHandler,
			$fedShareManager,
			$uid,
			$logger
		);
	}



    
    public function getFederatedShareProvider()
    {
			$appManager = \OC::$server->getAppManager();
			$userManager = \OC::$server->getUserManager();
			$logger = \OC::$server->getLogger();
			$urlGenerator = \OC::$server->getURLGenerator();
			$l10n = \OC::$server->getL10N('federatedfilesharing');
			$addressHandler = new AddressHandler(
				$urlGenerator,
				$l10n
			);
			$httpClientService = \OC::$server->getHTTPClientService();
			$memCacheFactory = \OC::$server->getMemCacheFactory();
			$discoveryManager = new DiscoveryManager(
				$memCacheFactory,
				$httpClientService
			);
			$permissions = new Permissions();
			$notificationManagerFFS = new NotificationManager($permissions);
			$notificationManagerServer = \OC::$server->getNotificationManager();
			$jobList = \OC::$server->getJobList();
			$config = \OC::$server->getConfig();
			$notifications = new Notifications(
				$addressHandler,
				$httpClientService,
				$discoveryManager,
				$notificationManagerFFS,
				$jobList,
				$config
			);
			$secureRandom = \OC::$server->getSecureRandom();
			$tokenHandler = new TokenHandler(
				$secureRandom
			);
			$databaseConnection = \OC::$server->getDatabaseConnection();
			$eventDispatcher = \OC::$server->getEventDispatcher();
			$lazyFolderRoot = \OC::$server->getLazyRootFolder();
			error_log("our application returning our FederatedShareProvider");
			return new FederatedShareProvider(
				$databaseConnection,
				$eventDispatcher,
				$addressHandler,
				$notifications,
				$tokenHandler,
				$l10n,
				$logger,
				$lazyFolderRoot,
				$config,
				$userManager
			);
	}

	public static function getExternalManager(){
		$appManager = \OC::$server->getAppManager();
		$userManager = \OC::$server->getUserManager();
		$logger = \OC::$server->getLogger();
		$urlGenerator = \OC::$server->getURLGenerator();
		$l10n = \OC::$server->getL10N('federatedfilesharing');
		$addressHandler = new AddressHandler(
			$urlGenerator,
			$l10n
		);
		$httpClientService = \OC::$server->getHTTPClientService();
		$memCacheFactory = \OC::$server->getMemCacheFactory();
		$discoveryManager = new DiscoveryManager(
			$memCacheFactory,
			$httpClientService
		);
		$permissions = new Permissions();
		$notificationManagerFFS = new NotificationManager($permissions);
		$notificationManagerServer = \OC::$server->getNotificationManager();
		$jobList = \OC::$server->getJobList();
		$config = \OC::$server->getConfig();
		$notifications = new Notifications(
			$addressHandler,
			$httpClientService,
			$discoveryManager,
			$notificationManagerFFS,
			$jobList,
			$config
		);
		$secureRandom = \OC::$server->getSecureRandom();
		$tokenHandler = new TokenHandler(
			$secureRandom
		);
		$databaseConnection = \OC::$server->getDatabaseConnection();
		$eventDispatcher = \OC::$server->getEventDispatcher();
		$lazyFolderRoot = \OC::$server->getLazyRootFolder();
		$federatedShareProvider = new FederatedShareProvider(
			$databaseConnection,
			$eventDispatcher,
			$addressHandler,
			$notifications,
			$tokenHandler,
			$l10n,
			$logger,
			$lazyFolderRoot,
			$config,
			$userManager
		);
		$ocmMiddleware = new OcmMiddleware(
			$federatedShareProvider,
			$appManager,
			$userManager,
			$addressHandler,
			$logger
		);
		$notifications = new Notifications(
			$addressHandler,
			\OC::$server->getHTTPClientService(),
			$discoveryManager,
			$notificationManagerFFS,
			\OC::$server->getJobList(),
			\OC::$server->getConfig()
		);
		$activityManager = \OC::$server->getActivityManager();
		$fedShareManager = new FedShareManager(
			$federatedShareProvider,
			$notifications,
			$userManager,
			$activityManager,
			$notificationManagerServer,
			$addressHandler,
			$permissions,
			$eventDispatcher
		);
		$user = \OC::$server->getUserSession()->getUser();
		return $fedShareManager->getExternalManager($user->getUID());
	}
} 

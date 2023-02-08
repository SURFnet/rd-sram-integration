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
use OCP\AppFramework\Http;
use OCP\Share\Events\AcceptShare;
use OCP\Share\Events\DeclineShare;
use OCP\Util;
use OCP\IContainer;

function getFederatedUserNotifications() {
	$appManager = \OC::$server->getAppManager();
	$userManager = \OC::$server->getUserManager();
	$logger = \OC::$server->getLogger();
	$urlGenerator = \OC::$server->getURLGenerator();
	$l10n = \OC::$server->getL10N('federatedfilesharing');
	$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
		$urlGenerator,
		$l10n
	);
	$httpClientService = \OC::$server->getHTTPClientService();
	$memCacheFactory = \OC::$server->getMemCacheFactory();
	$discoveryManager = new \OCA\FederatedFileSharing\DiscoveryManager(
		$memCacheFactory,
		$httpClientService
	);
	$permissions = new \OCA\FederatedFileSharing\Ocm\Permissions();
	$notificationManagerFFS = new \OCA\FederatedFileSharing\Ocm\NotificationManager($permissions);
	$notificationManagerServer = \OC::$server->getNotificationManager();
	$jobList = \OC::$server->getJobList();
	$config = \OC::$server->getConfig();
	return new \OCA\FederatedFileSharing\Notifications(
		$addressHandler,
		$httpClientService,
		$discoveryManager,
		$notificationManagerFFS,
		$jobList,
		$config
	);
}


function getFederatedGroupNotifications() {
	$appManager = \OC::$server->getAppManager();
	$userManager = \OC::$server->getUserManager();
	$logger = \OC::$server->getLogger();
	$urlGenerator = \OC::$server->getURLGenerator();
	$l10n = \OC::$server->getL10N('federatedgroups');
	$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
		$urlGenerator,
		$l10n
	);
	$httpClientService = \OC::$server->getHTTPClientService();
	$memCacheFactory = \OC::$server->getMemCacheFactory();
	$discoveryManager = new \OCA\FederatedFileSharing\DiscoveryManager(
		$memCacheFactory,
		$httpClientService
	);
	$permissions = new \OCA\FederatedFileSharing\Ocm\Permissions();
	$notificationManagerFFS = new \OCA\FederatedFileSharing\Ocm\NotificationManager($permissions);
	$notificationManagerServer = \OC::$server->getNotificationManager();
	$jobList = \OC::$server->getJobList();
	$config = \OC::$server->getConfig();
	return new \OCA\FederatedGroups\FederatedFileSharing\Notifications(
		$addressHandler,
		$httpClientService,
		$discoveryManager,
		$notificationManagerFFS,
		$jobList,
		$config
	);
}

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

	public static function getOcmController(IRequest $request) {
		return self::getFederatedGroupOcmController($request);
	}

	public static function getFederatedGroupOcmController(IRequest $request) {
		$federatedUserNotifications = getFederatedUserNotifications();
		$federatedGroupNotifications = getFederatedGroupNotifications();
		$secureRandom = \OC::$server->getSecureRandom();
		$tokenHandler = new \OCA\FederatedFileSharing\TokenHandler(
			$secureRandom
		);
		$databaseConnection = \OC::$server->getDatabaseConnection();
		$eventDispatcher = \OC::$server->getEventDispatcher();
		$lazyFolderRoot = \OC::$server->getLazyRootFolder();
		$urlGenerator = \OC::$server->getURLGenerator();
		$l10n = \OC::$server->getL10N('federatedfilesharing');
		$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
			$urlGenerator,
			$l10n
		);
		$logger = \OC::$server->getLogger();
		$config = \OC::$server->getConfig();
		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();
		$appManager = \OC::$server->getAppManager();
		$notificationManagerServer = \OC::$server->getNotificationManager();
		$permissions = new \OCA\FederatedFileSharing\Ocm\Permissions();

	  $federatedUserShareProvider = new \OCA\FederatedFileSharing\FederatedShareProvider(
			$databaseConnection,
			$eventDispatcher,
			$addressHandler,
			$federatedUserNotifications,
			$tokenHandler,
			$l10n,
			$logger,
			$lazyFolderRoot,
			$config,
			$userManager
    );

	  $federatedGroupShareProvider = new \OCA\FederatedGroups\FederatedGroupShareProvider(
			$databaseConnection,
			$eventDispatcher,
			$addressHandler,
			$federatedGroupNotifications,
			$tokenHandler,
			$l10n,
			$logger,
			$lazyFolderRoot,
			$config,
			$userManager,
			$groupManager
    );
		$federeatedGroupOcmMiddleware = new \OCA\FederatedGroups\FederatedFileSharing\Middleware\OcmMiddleware(
			$federatedGroupShareProvider,
			$appManager,
			$userManager,
			$addressHandler,
			$logger
		);
		$activityManager = \OC::$server->getActivityManager();
		$fedShareManager = new \OCA\FederatedGroups\FederatedFileSharing\FedShareManager(
			$federatedUserShareProvider,
			$federatedGroupShareProvider,
			$federatedUserNotifications,
			$federatedGroupNotifications,
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
			$federeatedGroupOcmMiddleware,
			$urlGenerator,
			$userManager,
			$addressHandler,
			$fedShareManager,
			$uid,
			$logger
		);
	}

  public function getMixedGroupShareProvider() {
		error_log("returning the \OCA\FederatedGroups\MixedGroupShareProvider from the Application getMixedGroupShareProvider method");
		$urlGenerator = \OC::$server->getURLGenerator();
		$l10n = \OC::$server->getL10N('federatedfilesharing');
		$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
			$urlGenerator,
			$l10n
		);
	  return new \OCA\FederatedGroups\MixedGroupShareProvider(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getUserManager(),
			\OC::$server->getGroupManager(),
			\OC::$server->getLazyRootFolder(),
			getFederatedGroupNotifications(),
			$addressHandler,
			$l10n,
			\OC::$server->getLogger()
		);
	}

  public function getFederatedUserShareProvider()
    {
			$appManager = \OC::$server->getAppManager();
			$userManager = \OC::$server->getUserManager();
			$logger = \OC::$server->getLogger();
			$urlGenerator = \OC::$server->getURLGenerator();
			$l10n = \OC::$server->getL10N('federatedfilesharing');
			$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
				$urlGenerator,
				$l10n
			);
			$httpClientService = \OC::$server->getHTTPClientService();
			$memCacheFactory = \OC::$server->getMemCacheFactory();
			$discoveryManager = new \OCA\FederatedFileSharing\DiscoveryManager(
				$memCacheFactory,
				$httpClientService
			);
			$permissions = new \OCA\FederatedFileSharing\Ocm\Permissions();
			$notificationManagerFFS = new \OCA\FederatedFileSharing\Ocm\NotificationManager($permissions);
			$notificationManagerServer = \OC::$server->getNotificationManager();
			$jobList = \OC::$server->getJobList();
			$config = \OC::$server->getConfig();
			$federatedUserNotifications = new \OCA\FederatedFileSharing\Notifications(
				$addressHandler,
				$httpClientService,
				$discoveryManager,
				$notificationManagerFFS,
				$jobList,
				$config
			);
			$secureRandom = \OC::$server->getSecureRandom();
			$tokenHandler = new \OCA\FederatedFileSharing\TokenHandler(
				$secureRandom
			);
			$databaseConnection = \OC::$server->getDatabaseConnection();
			$eventDispatcher = \OC::$server->getEventDispatcher();
			$lazyFolderRoot = \OC::$server->getLazyRootFolder();
			error_log("our application returning our \OCA\FederatedFileSharing\FederatedShareProvider");
			return new \OCA\FederatedFileSharing\FederatedShareProvider(
				$databaseConnection,
				$eventDispatcher,
				$addressHandler,
				$federatedUserNotifications,
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
		$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
			$urlGenerator,
			$l10n
		);
		$httpClientService = \OC::$server->getHTTPClientService();
		$memCacheFactory = \OC::$server->getMemCacheFactory();
		$discoveryManager = new \OCA\FederatedFileSharing\DiscoveryManager(
			$memCacheFactory,
			$httpClientService
		);
		$permissions = new \OCA\FederatedFileSharing\Ocm\Permissions();
		$notificationManagerFFS = new \OCA\FederatedFileSharing\Ocm\NotificationManager($permissions);
		$notificationManagerServer = \OC::$server->getNotificationManager();
		$jobList = \OC::$server->getJobList();
		$config = \OC::$server->getConfig();
		$federatedUserNotifications = new \OCA\FederatedFileSharing\Notifications(
			$addressHandler,
			$httpClientService,
			$discoveryManager,
			$notificationManagerFFS,
			$jobList,
			$config
		);
		$federatedGroupNotifications = new \OCA\FederatedGroups\FederatedFileSharing\Notifications(
			$addressHandler,
			$httpClientService,
			$discoveryManager,
			$notificationManagerFFS,
			$jobList,
			$config
		);
		$secureRandom = \OC::$server->getSecureRandom();
		$tokenHandler = new \OCA\FederatedFileSharing\TokenHandler(
			$secureRandom
		);
		$databaseConnection = \OC::$server->getDatabaseConnection();
		$eventDispatcher = \OC::$server->getEventDispatcher();
		$lazyFolderRoot = \OC::$server->getLazyRootFolder();
		$federatedUserShareProvider = new \OCA\FederatedFileSharing\FederatedShareProvider(
			$databaseConnection,
			$eventDispatcher,
			$addressHandler,
			$federatedUserNotifications,
			$tokenHandler,
			$l10n,
			$logger,
			$lazyFolderRoot,
			$config,
			$userManager
		);
		$ocmMiddleware = new \OCA\FederatedGroups\FederatedFileSharing\Middleware\OcmMiddleware(
			$federatedGroupShareProvider,
			$appManager,
			$userManager,
			$addressHandler,
			$logger
		);
		$federatedGroupNotifications = new \OCA\FederatedGroups\FederatedFileSharing\Notifications(
			$addressHandler,
			\OC::$server->getHTTPClientService(),
			$discoveryManager,
			$notificationManagerFFS,
			\OC::$server->getJobList(),
			\OC::$server->getConfig()
		);
		$activityManager = \OC::$server->getActivityManager();
		$fedShareManager = new \OCA\FederatedGroups\FederatedFileSharing\FedShareManager(
			$federatedUserShareProvider,
			$federatedGroupShareProvider,
			$federatedUserNotifications,
			$federatedGroupNotifications,
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

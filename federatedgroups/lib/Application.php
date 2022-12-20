<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

namespace OCA\FederatedGroups;

use OCP\AppFramework\App;
use OCP\IRequest;
use GuzzleHttp\Exception\ServerException;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Command\PollIncomingShares;
use OCA\FederatedFileSharing\Controller\OcmController;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedGroups\FederatedShareProvider;
use OCA\FederatedGroups\FedShareManager;
use OCA\FederatedFileSharing\Middleware\OcmMiddleware;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\Controller\RequestHandlerController;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\TokenHandler;
use OCP\AppFramework\Http;
use OCP\Share\Events\AcceptShare;
use OCP\Share\Events\DeclineShare;
use OCP\Util;


class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('federatedgroups', $urlParams);
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
		return new \OCA\FederatedGroups\Controller\OcmController(
			'federatedgroups',
			$request,
			$ocmMiddleware,
			$urlGenerator,
			$userManager,
			$addressHandler,
			$fedShareManager,
			$logger
		);
	}
} 

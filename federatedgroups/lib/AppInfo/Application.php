<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCA\FederatedFileSharing\TokenHandler;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedGroups\GroupBackend;
use OCA\FederatedGroups\SRAMFederatedGroupShareProvider;


class Application extends App {
	private $isProviderRegistered = false;
	
	public function __construct(array $urlParams = []) {
		parent::__construct('federatedgroups', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('OCA\\FederatedGroups\\GroupBackend', function (SimpleContainer $c) use ($server) {
			return new GroupBackend();
		});
	}
		
  	public static  function getMixedGroupShareProvider() {
		$urlGenerator = \OC::$server->getURLGenerator();
		$l10n = \OC::$server->getL10N('federatedfilesharing');
		$addressHandler = new AddressHandler(
			$urlGenerator,
			$l10n
		);

		$tokenHandler = new TokenHandler(
			\OC::$server->getSecureRandom()
		);
		$ocmApp = new \OCA\OpenCloudMesh\AppInfo\Application();

	  	return new \OCA\FederatedGroups\MixedGroupShareProvider(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getUserManager(),
			\OC::$server->getGroupManager(),
			\OC::$server->getLazyRootFolder(),
			$ocmApp->getContainer()->query("GroupNotifications"),
			$tokenHandler,
			$addressHandler,
			$l10n,
			\OC::$server->getLogger()
		);
	}

	public function getSRAMFederatedGroupShareProvider(){
		
		
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

		return new SRAMFederatedGroupShareProvider(
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
			$this->getContainer()->query('OCA\\FederatedGroups\\ShareProviderFactory'),
			function () {
				$ocmApp = new \OCA\OpenCloudMesh\AppInfo\Application();
				return $ocmApp->getContainer()->query('OCA\\OpenCloudMesh\\GroupExternalManager');
			}
		);
	}

	public function registerBackends() {
		$server = $this->getContainer()->getServer();
		$groupBackend = $this->getContainer()->query('OCA\\FederatedGroups\\GroupBackend');
		$server->getGroupManager()->addBackend($groupBackend);
	}
} 

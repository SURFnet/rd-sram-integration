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

class Application extends App {
	private $isProviderRegistered = false;
	
	public function __construct(array $urlParams = []) {
		// error_log("fg: ". get_parent_class($this));
		parent::__construct('federatedgroups', $urlParams);
	}
		
  	public static  function getMixedGroupShareProvider() {
		$urlGenerator = \OC::$server->getURLGenerator();
		$l10n = \OC::$server->getL10N('federatedfilesharing');
		$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
			$urlGenerator,
			$l10n
		);

		$ocmApp = new \OCA\OpenCloudMesh\AppInfo\Application();

	  	return new \OCA\FederatedGroups\MixedGroupShareProvider(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getUserManager(),
			\OC::$server->getGroupManager(),
			\OC::$server->getLazyRootFolder(),
			$ocmApp->getContainer()->query("GroupNotifications"),
			$addressHandler,
			$l10n,
			\OC::$server->getLogger()
		);
	}
} 

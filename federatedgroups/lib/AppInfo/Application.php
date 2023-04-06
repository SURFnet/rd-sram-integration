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
use OCA\FederatedGroups\FederatedFileSharing\FedShareManager;

class Application extends App {
	private $isProviderRegistered = false;
	
	public function __construct(array $urlParams = []) {
		parent::__construct('federatedgroups', $urlParams);
	}

	public function registerMountProviders() {
		// We need to prevent adding providers more than once
		// Doing this on MountProviderCollection level makes a lot tests to fail
		if ($this->isProviderRegistered === false) {
			/** @var \OCP\IServerContainer $server */
			$server = $this->getContainer()->query('ServerContainer');
			$mountProviderCollection = $server->getMountProviderCollection();
			$mountProviderCollection->registerProvider($this->getContainer()->query('OCA\FederatedGroups\Files_Sharing\External\MountProvider'));
			$this->isProviderRegistered = true;
		}
	}
} 

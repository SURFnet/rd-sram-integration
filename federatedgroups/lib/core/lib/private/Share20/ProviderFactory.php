<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 */
namespace OCA\FederatedGroups;

use OC\Share20\Exception\ProviderException;
use OCP\Share\IProviderFactory;
use OC\Share20\ProviderFactory;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;


use OCP\IServerContainer;

class ShareProviderFactory extends ProviderFactory implements IProviderFactory {

	// These two variables exist in the parent class,
	// but need to be redeclared here at the child class
	// level because they're private:

	/** @var IServerContainer */
	private $serverContainer;

	/** @var DefaultShareProvider */
	private $defaultProvider = null;

	/** @var FederatedShareProvider */
	private $federatedUserProvider = null;

	/** @var FederatedShareProvider */
	private $federatedGroupProvider = null;

	public function __construct(IServerContainer $serverContainer) {
		parent::__construct($serverContainer);
		error_log("FederatedShareProviderFactory constructor");
		$this->serverContainer = $serverContainer;
	}
	protected function defaultShareProvider() {
		
		error_log("our defaultShareProvider!");
		if ($this->defaultProvider === null) {
			$addressHandler = new \OCA\FederatedFileSharing\AddressHandler(
				\OC::$server->getURLGenerator(),
				\OC::$server->getL10N('federatedfilesharing')
			);
			$discoveryManager = new \OCA\FederatedFileSharing\DiscoveryManager(
				\OC::$server->getMemCacheFactory(),
				\OC::$server->getHTTPClientService()
			);
			$notificationManager = new \OCA\FederatedFileSharing\Ocm\NotificationManager(
				new \OCA\FederatedFileSharing\Ocm\Permissions()
			);
			$notifications = new \OCA\FederatedFileSharing\Notifications(
				$addressHandler,
				\OC::$server->getHTTPClientService(),
				$discoveryManager,
				$notificationManager,
				\OC::$server->getJobList(),
				\OC::$server->getConfig()
			);
			$tokenHandler = new \OCA\FederatedFileSharing\TokenHandler(
				\OC::$server->getSecureRandom()
			);

			$this->defaultProvider = new GroupShareProvider(
				$this->serverContainer->getDatabaseConnection(),
				
				$this->serverContainer->getEventDispatcher(),
				$addressHandler,
				$notifications,
				$tokenHandler,
				$this->serverContainer->getL10N('federatedgroups'),
				$this->serverContainer->getLogger(),
				$this->serverContainer->getLazyRootFolder(),
				$this->serverContainer->getConfig(),
				$this->serverContainer->getUserManager(),
				$this->serverContainer->getGroupManager()
			);
		}
		return $this->defaultProvider;
	}

	/**
	 * Create the federated share provider for OCM to groups
	 *
	 * @return FederatedShareProvider
	 */
	protected function federatedGroupShareProvider() {
		error_log("our factory getting our FederatedShareProvider for OCM to group");
		if ($this->federatedGroupProvider === null) {
			$federatedGroupsApp = new Application();
			$this->federatedGroupProvider = $federatedGroupsApp->getFederatedShareProvider();
		}

		return $this->federatedGroupProvider;
	}

	/**
	 * Create the federated share provider for OCM to users
	 *
	 * @return FederatedShareProvider
	 */
	protected function federatedUserShareProvider() {
		error_log("our factory getting our FederatedShareProvider for OCM to user");
		if ($this->federatedUserProvider === null) {
			$federatedFileSharingApp = new \OCA\FederatedFileSharing\AppInfo\Application();
			$this->federatedUserProvider = $federatedFileSharingApp->getFederatedShareProvider();
		}

		return $this->federatedUserProvider;
	}

	/**
	 * @inheritdoc
	 */
	public function getProviderForType($shareType) {
		error_log("getProviderForType $shareType");
		$provider = null;

		if ($shareType === \OCP\Share::SHARE_TYPE_USER  ||
						$shareType === \OCP\Share::SHARE_TYPE_GROUP ||
						$shareType === \OCP\Share::SHARE_TYPE_LINK) {
						$provider = $this->defaultShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
						$provider = $this->federatedUserShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_REMOTE_GROUP) {
						$provider = $this->federateGroupShareProvider();
		}

		if ($provider === null) {
						throw new ProviderException('No share provider for share type ' . $shareType);
		}

		return $provider;
	}
}
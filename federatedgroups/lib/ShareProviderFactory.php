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
use OC\Share20\DefaultShareProvider;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\FederatedGroups\AppInfo\Application;


use OCP\IServerContainer;

class ShareProviderFactory extends \OC\Share20\ProviderFactory implements IProviderFactory {

	// These two variables exist in the parent class,
	// but need to be redeclared here at the child class
	// level because they're private:

	/** @var IServerContainer */
	private $serverContainer;

	/** @var DefaultShareProvider */
	private $defaultShareProvider = null;

	/** @var FederatedUserShareProvider */
	private $federatedUserShareProvider = null;

	/** @var FederatedGroupShareProvider */
	private $federatedGroupShareProvider = null;

	/** @var MixedGroupShareProvider */
	private $mixedGroupShareProvider = null;

	public function __construct(IServerContainer $serverContainer) {
		parent::__construct($serverContainer);
		error_log("FederatedGroups ProviderFactory constructor");
		$this->serverContainer = $serverContainer;
	}
	protected function defaultShareProvider() {
		error_log("our defaultShareProvider!");
		if ($this->defaultShareProvider === null) {
			$this->defaultShareProvider = new DefaultShareProvider(
				$this->serverContainer->getDatabaseConnection(),
				$this->serverContainer->getUserManager(),
				$this->serverContainer->getGroupManager(),
				$this->serverContainer->getLazyRootFolder()
			);
		}
		return $this->defaultShareProvider;
	}

	/**
	 * Create the federated share provider for OCM to groups
	 *
	 * @return FederatedGroupShareProvider
	 */
	protected function federatedGroupShareProvider() {
		error_log("our factory getting our FederatedShareProvider for OCM to group");
		if ($this->federatedGroupShareProvider === null) {
			$federatedGroupsApp = new Application();
			$this->federatedGroupShareProvider = $federatedGroupsApp->getFederatedGroupShareProvider();
		}
		return $this->federatedGroupShareProvider;
	}
	/**
	 * Create the mixed group share provider for OCM to groups
	 *
	 * @return MixedGroupShareProvider
	 */
	protected function mixedGroupShareProvider() {
		error_log("our factory getting our FederatedShareProvider for OCM to group");
		if ($this->mixedGroupShareProvider === null) {
			$federatedGroupsApp = new Application();
			$this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
		}
    error_log("returning the MixedGroupShareProvider from the ShareProviderFactory");
		return $this->mixedGroupShareProvider;
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
				$shareType === \OCP\Share::SHARE_TYPE_LINK) {
			$provider = $this->defaultShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
			$provider = $this->mixedGroupShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
			$provider = $this->federatedUserShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_REMOTE_GROUP) {
			$provider = $this->federatedGroupShareProvider();
		}

		if ($provider === null) {
						throw new ProviderException('No share provider for share type ' . $shareType);
		}

		return $provider;
	}
}

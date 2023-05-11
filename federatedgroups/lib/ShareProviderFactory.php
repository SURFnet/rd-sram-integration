<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 */
namespace OCA\FederatedGroups;

use OC\Share20\Exception\ProviderException;
use OC\Share20\DefaultShareProvider;
use OCP\IServerContainer;

class ShareProviderFactory extends \OCA\OpenCloudMesh\ShareProviderFactory {

	// These two variables exist in the parent class,
	// but need to be redeclared here at the child class
	// level because they're private:

	/** @var DefaultShareProvider */
	private $defaultShareProvider = null;

	/** @var FederatedUserShareProvider */
	private $federatedUserShareProvider = null;

	/** @var SRAMFederatedGroupShareProvider */
	private $federatedGroupShareProvider = null;

	/** @var MixedGroupShareProvider */
	private $mixedGroupShareProvider = null;

	public function __construct(IServerContainer $serverContainer) {
		parent::__construct($serverContainer);
		$this->serverContainer = $serverContainer;
	}

	/**
	 * Create the federated share provider for OCM to groups
	 *
	 * @return SRAMFederatedGroupShareProvider
	 */
	protected function federatedGroupShareProvider() {
		if ($this->federatedGroupShareProvider === null) {
			$app = new \OCA\FederatedGroups\AppInfo\Application();
			$this->federatedGroupShareProvider = $app->getSRAMFederatedGroupShareProvider();
		}
		return $this->federatedGroupShareProvider;
	}
	/**
	 * Create the mixed group share provider for OCM to groups
	 *
	 * @return MixedGroupShareProvider
	 */
	protected function mixedGroupShareProvider() {
		if ($this->mixedGroupShareProvider === null) {
			$this->mixedGroupShareProvider = \OCA\FederatedGroups\AppInfo\Application::getMixedGroupShareProvider();
		}
		return $this->mixedGroupShareProvider;
	}

	/**
	 * @inheritdoc
	 */
	public function getProviderForType($shareType) {
		$provider = null;

		// SHARE_TYPE_USER = 0;
		// SHARE_TYPE_GROUP = 1;
		// SHARE_TYPE_LINK = 3;
		// SHARE_TYPE_GUEST = 4;
		// SHARE_TYPE_CONTACT = 5; // ToDo Check if it is still in use otherwise remove it
		// SHARE_TYPE_REMOTE = 6;
		// SHARE_TYPE_REMOTE_GROUP = 7;
	
	
		if ($shareType === \OCP\Share::SHARE_TYPE_USER  ||
				$shareType === \OCP\Share::SHARE_TYPE_LINK  ||
				$shareType === \OCP\Share::SHARE_TYPE_GUEST  ||
				$shareType === \OCP\Share::SHARE_TYPE_CONTACT ||
				$shareType === \OCP\Share::SHARE_TYPE_GROUP) {
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

	/**
	 * @inheritdoc
	 */
	public function getProvider($id) {
		$provider = null;
		if ($id === 'ocinternal') {
			$provider = $this->defaultShareProvider();
		} elseif ($id === 'ocFederatedSharing') {
			$provider = $this->federatedShareProvider();
		}
		elseif($id === 'ocMixFederatedSharing'){
			$provider = $this->mixedGroupShareProvider();
		}
		elseif($id === 'ocGroupFederatedSharing'){
			$provider = $this->federatedGroupShareProvider();
		}

		if ($provider === null) {
			throw new ProviderException('No provider with id .' . $id . ' found.');
		}

		return $provider;
	}

	public function getProviders(){
		return [
			$this->defaultShareProvider(),
			$this->federatedShareProvider(),
			$this->mixedGroupShareProvider(),
			$this->federatedGroupShareProvider()
		]; 
	}
}

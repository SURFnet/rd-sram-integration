<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 */
namespace OCA\FederatedGroups;

use OC\Share20\Exception\ProviderException;
use OC\Share20\ProviderFactory;
use OCA\FederatedGroups\AppInfo\Application;
use OCP\IServerContainer;

class ShareProviderFactory extends ProviderFactory {

	/** @var FederatedGroupShareProvider */
	private $federatedGroupShareProvider = null;

	public function __construct(IServerContainer $serverContainer) {
		parent::__construct($serverContainer);
	}

	/**
	 * Create the federated share provider for OCM to groups
	 *
	 * @return FederatedGroupShareProvider
	 */
	protected function federatedGroupShareProvider() {
		if ($this->federatedGroupShareProvider === null) {
			//$this->federatedGroupShareProvider = \OC::$server->query('OCA\FederatedGroups\FederatedGroupShareProvider');
			$app = new Application();
			$this->federatedGroupShareProvider = $app->getFederatedGroupShareProvider();
		}
		return $this->federatedGroupShareProvider;
	}

	/**
	 * @inheritdoc
	 */
	public function getProviders() {
		$providers = parent::getProviders();
		array_push($providers, $this->federatedGroupShareProvider());
		return $providers;
	}

	/**
	 * @inheritdoc
	 */
	public function getProvider($id) {
		try {
			return parent::getProvider($id);
		} catch(ProviderException $e) {
			if ($id === 'ocGroupFederatedSharing') {
				return $this->federatedGroupShareProvider();
			}
		}
		
		throw new ProviderException('No provider with id .' . $id . ' found.');
	}

	/**
	 * @inheritdoc
	 */
	public function getProviderForType($shareType) {
		try {
			return parent::getProviderForType($shareType);
		} catch(ProviderException $e) {
			if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE_GROUP) {
				return $this->federatedGroupShareProvider();
			}
		}
		throw new ProviderException('No share provider for share type ' . $shareType);
	}
}

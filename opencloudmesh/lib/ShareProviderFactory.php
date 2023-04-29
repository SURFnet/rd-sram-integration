<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 */
namespace OCA\OpenCloudMesh;

use OC\Share20\Exception\ProviderException;
use OC\Share20\ProviderFactory;
use OCA\OpenCloudMesh\AppInfo\Application;
use OCP\IServerContainer;

class ShareProviderFactory extends ProviderFactory {

	/** @var FederatedGroupShareProvider */
	private $federatedGroupShareProvider = null;

	/** @var FederatedUserShareProvider */
	private $federatedUserShareProvider = null;

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
			$app = new Application();
			$this->federatedGroupShareProvider = $app->getFederatedGroupShareProvider();
		}
		return $this->federatedGroupShareProvider;
	}

	/**
	 * Create the federated share provider for OCM to users
	 *
	 * @return FederatedUserShareProvider
	 */
	protected function federatedUserShareProvider() {
		if ($this->federatedUserShareProvider === null) {
			$app = new Application();
			$this->federatedUserShareProvider = $app->getFederatedUserShareProvider();
		}
		return $this->federatedUserShareProvider;
	}

	/**
	 * @inheritdoc
	 */
	public function getProviders() {
		$providers = parent::getProviders();

		$providers = \array_filter($providers, function($provider) {
			return $provider->identifier() !== 'ocFederatedSharing';
		});

		array_push($providers, $this->federatedUserShareProvider());
		array_push($providers, $this->federatedGroupShareProvider());
		return $providers;
	}

	/**
	 * @inheritdoc
	 */
	public function getProvider($id) {
		if ($id === 'ocFederatedSharing') {
			return $this->federatedUserShareProvider();
		}
		else if ($id === 'ocGroupFederatedSharing') {
			return $this->federatedGroupShareProvider();
		}
		else {
			return parent::getProvider($id);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getProviderForType($shareType) {
		if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
			return $this->federatedUserShareProvider();
		}
		else if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE_GROUP) {
			return $this->federatedGroupShareProvider();
		}
		else {
			return parent::getProviderForType($shareType);
		}
	}
}

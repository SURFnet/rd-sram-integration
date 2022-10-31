<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\FederatedGroups;

use OCA\FederatedFileSharing\AppInfo\Application;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCP\Share\IProviderFactory;
use OC\Share20\Exception\ProviderException;
use OCA\FederatedGroups\ShareProvider;
use OCP\IServerContainer;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;

/**
 * Class ShareProviderFactory
 *
 * @package OCA\FederatedGroups
 */
class ShareProviderFactory implements IProviderFactory {

	/** @var IServerContainer */
	private $serverContainer;
	/** @var ShareProvider */
	private $defaultProvider = null;
	/** @var FederatedShareProvider */
	private $federatedProvider = null;

	/**
	 * IProviderFactory constructor.
	 * @param IServerContainer $serverContainer
	 */
	public function __construct(IServerContainer $serverContainer) {
		error_log("FederatedGroupsProviderFactory!");
		$this->serverContainer = $serverContainer;
	}

	/**
	 * Create the default share provider.
	 *
	 * @return ShareProvider
	 */
	protected function defaultShareProvider() {
		if ($this->defaultProvider === null) {
			// serverContainer really has to be more than just an IServerContainer
			// because getLazyRootFolder() is only in \OC\Server

		// IDBConnection $connection,
		// IUserManager $userManager,
		// IGroupManager $groupManager,
		// AddressHandler $addressHandler,
		// Notifications $notifications,
		// TokenHandler $tokenHandler,
		// IRootFolder $rootFolder

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

		$this->defaultProvider = new ShareProvider(
				$this->serverContainer->getDatabaseConnection(),
				$this->serverContainer->getUserManager(),
				$this->serverContainer->getGroupManager(),
				$addressHandler,
				$notifications,
				$tokenHandler,
				$this->serverContainer->getLazyRootFolder()
			);
		}

		return $this->defaultProvider;
	}

	/**
	 * Create the federated share provider
	 *
	 * @return FederatedShareProvider
	 */
	protected function federatedShareProvider() {
		if ($this->federatedProvider === null) {
			/*
			 * Check if the app is enabled
			 */
			$appManager = $this->serverContainer->getAppManager();
			if (!$appManager->isEnabledForUser('federatedfilesharing')) {
				return null;
			}

			/*
			 * TODO: add factory to federated sharing app
			 */
			$federatedFileSharingApp = new Application();
			$this->federatedProvider = $federatedFileSharingApp->getFederatedShareProvider();
		}

		return $this->federatedProvider;
	}

	public function getProviders() {
		return [
			$this->defaultShareProvider(),
			$this->federatedShareProvider()
		];
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

		if ($provider === null) {
			throw new ProviderException('No provider with id .' . $id . ' found.');
		}

		return $provider;
	}

	/**
	 * @inheritdoc
	 */
	public function getProviderForType($shareType) {
		$provider = null;

		if ($shareType === \OCP\Share::SHARE_TYPE_USER  ||
			$shareType === \OCP\Share::SHARE_TYPE_GROUP ||
			$shareType === \OCP\Share::SHARE_TYPE_LINK) {
			$provider = $this->defaultShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
			$provider = $this->federatedShareProvider();
		}

		if ($provider === null) {
			throw new ProviderException('No share provider for share type ' . $shareType);
		}

		return $provider;
	}
}

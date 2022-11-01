<?php
/**
 */
namespace OCA\FederatedGroups;

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
	/** @var IServerContainer */
	private $serverContainerInChild;

	public function __construct(IServerContainer $serverContainerInChild) {
		$this->serverContainerInChild = $serverContainerInChild;
	}
	protected function defaultShareProvider() {
		error_log("child defaultShareProvider!");
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

			$this->defaultProvider = new ShareProvider(
				$this->serverContainerInChild->getDatabaseConnection(),
				$this->serverContainerInChild->getUserManager(),
				$this->serverContainerInChild->getGroupManager(),
				$addressHandler,
				$notifications,
				$tokenHandler,
				$this->serverContainerInChild->getLazyRootFolder()
			);
		}

		return $this->defaultProvider;
	}
}

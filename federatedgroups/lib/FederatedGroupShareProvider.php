<?php

// Copyright (c) 2018, ownCloud GmbH
// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\FederatedGroups;

use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OC\Share20\Share;
use OC\Share20\DefaultShareProvider;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\TokenHandler;

use OCA\FederatedGroups\FederatedFileSharing\Notifications;

use OCP\Files\File;
use OCP\Share\IAttributes;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IDBConnection;
use OCA\FederatedFileSharing;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * Class FederatedGroupShareProvider
 *
 * @package OC\Share20
 */
class FederatedGroupShareProvider extends FederatedShareProvider implements IShareProvider {
	// For representing foreign group members
	// e.g. 'marie#oc2.docker'
	public const SEPARATOR = '#';

	/** @var IGroupManager */
	private $groupManager;

	/** @var FederatedShareProvider */
	private $federatedProvider;

	private $dbConnection;

	/**
	 * FederatedGroupShareProvider constructor.
	 *
	 * @param IDBConnection $dbConnection
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param AddressHandler $addressHandler
	 * @param Notifications $notifications
	 * @param TokenHandler $tokenHandler
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param IRootFolder $rootFolder
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct(
		IDBConnection $dbConnection,
		EventDispatcherInterface $eventDispatcher,
		AddressHandler $addressHandler,
		Notifications $notifications,
		TokenHandler $tokenHandler,
		IL10N $l10n,
		ILogger $logger,
		IRootFolder $rootFolder,
		IConfig $config,
		IUserManager $userManager,
		IGroupManager $groupManager
	) {
		parent::__construct(
			 $dbConnection,
		 $eventDispatcher,
		 $addressHandler,
		 $notifications,
		 $tokenHandler,
		 $l10n,
		 $logger,
		 $rootFolder,
		 $config,
		 $userManager
		);
		error_log("Constructing the FederatedGroupShareProvider");
		$this->groupManager = $groupManager;
    $this->federatedProvider = new FederatedShareProvider(
			$dbConnection,
			$eventDispatcher,
			$addressHandler,
			$notifications,
			$tokenHandler,
			$l10n,
			$logger,
			$rootFolder,
			$config,
			$userManager
		);
		$this->notifications = $notifications;
		$this->dbConnection = $dbConnection;
		// error_log("FederatedGroups FederatedGroupShareProvider!");
	}

	/**
	 * @param $remote
	 * @param $token
	 * @param $name
	 * @param $owner
	 * @param $shareWith
	 * @param $remoteId
	 *
	 * @return int
	 */
	public function addShare($remote, $token, $name, $owner, $shareWith, $remoteId) {
		error_log("FederatedGroupShareProvider addShare calling our External Manager");
		\OC_Util::setupFS($shareWith);
		$externalManager = new \OCA\FederatedGroups\Files_Sharing\External\Manager(
			$this->dbConnection,
			\OC\Files\Filesystem::getMountManager(),
			\OC\Files\Filesystem::getLoader(),
			\OC::$server->getNotificationManager(),
			\OC::$server->getEventDispatcher(),
			$shareWith
		);
		$externalManager->addShare(
			$remote,
			$token,
			'',
			$name,
			$owner,
			$this->getAccepted($remote, $shareWith),
			$shareWith,
			$remoteId
		);
		return $this->dbConnection->lastInsertId("*PREFIX*{$this->externalShareTable}");
	}

	/**
	 * Share a path
	 *
	 * @param \OCP\Share\IShare $share
	 * @return \OCP\Share\IShare The share object
	 * @throws ShareNotFound
	 * @throws InvalidArgumentException if the share validation failed
	 * @throws \Exception
	 */
	public function create(\OCP\Share\IShare $share) {
		error_log("FederatedGroupShareProvider create calling parent");
		// Create group share locally
		$created = parent::create($share);
		error_log("FederatedGroupShareProvider create called parent");
		$remotes = [];
		// Send OCM invites to remote group members
		error_log("Sending OCM invites");
		error_log($share->getSharedWith());
		$group = $this->groupManager->get($share->getSharedWith());
		// error_log("Got group");
		$backend = $group->getBackend();
		// error_log("Got backend");
		$recipients = $backend->usersInGroup($share->getSharedWith());
		// error_log("Got recipients");
		error_log(var_export($recipients, true));
		foreach($recipients as $k => $v) {
			$parts = explode(self::SEPARATOR, $v);
			if (count($parts) == 2) {
				error_log("Considering remote " . $parts[1] . " because of " . $parts[0] . " there");
				$remotes[$parts[1]] = true;
			} else {
				error_log("Local user: $v");
			}
		}
		foreach($remotes as $remote => $_dummy) {
			$this->sendOcmInvite($share->getSharedBy(), $share->getShareOwner(), $share->getSharedWith(), $remote, $share->getNode()->getName());
		}
	}
	public function getAllSharedWith($userId, $node){
		error_log("you `getAllSharedWith` me on FederatedGroupShareProvider...");
		return parent::getAllSharedWith($userId, $node);
	}

	public function getSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0){
		error_log("you `getSharedWith` on FederatedGroupShareProvider...");
		return parent::getSharedWith($userId, $shareType, $node, $limit, $offset);
	}

	
}

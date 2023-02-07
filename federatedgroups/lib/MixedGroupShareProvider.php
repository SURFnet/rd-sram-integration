<?php

// Copyright (c) 2018, ownCloud GmbH
// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\FederatedGroups;

use OCA\FederatedGroups\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Address;
use OC\Share20\DefaultShareProvider;
use OCP\Share\IShareProvider;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;

/**
 * Class MixedGroupShareProvider
 *
 * @package OC\Share20
 */
class MixedGroupShareProvider extends DefaultShareProvider implements IShareProvider {
	// For representing foreign group members
	// e.g. 'marie#oc2.docker'
	public const SEPARATOR = '#';

	/** @var Notifications */
	private $notifications;

	/** @var AddressHandler */
	private $addressHandler;

	/** @var IL10N */
	private $l;

	/** @var ILogger */
	private $logger;

	/**
	 * Note $dbConn is private in the parent class
	 * so we need to keep a copy of it here
	 * @var IDBConnection
	 */
	private $dbConn;

	/**
	 * Note this is private in the parent class
	 * so we need to keep a copy of it here
	 * @var IGroupManager $groupManager
	 */
	private $groupManager;

	/**
	 * Note this is private in the parent class
	 * so we need to keep a copy of it here
	 * @var IUserManager $userManager
	 */
	private $userManager;

	/**
	 * DefaultShareProvider constructor.
	 *
	 * @param IDBConnection $dbConn
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IRootFolder $rootFolder
	 * @param Notifications $notifications
	 * @param AddressHandler $addressHandler
	 * @param IL10N $l
	 * @param ILogger $logger
	 */
	public function __construct(
		IDBConnection $dbConn,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IRootFolder $rootFolder,
		Notifications $notifications,
		AddressHandler $addressHandler,
		IL10N $l,
		ILogger $logger
	) {
		parent::__construct(
			 $dbConn,
			 $userManager,
			 $groupManager,
			 $rootFolder
		);
		error_log("Constructing the MixedGroupShareProvider");
		$this->notifications = $notifications;
		$this->addressHandler = $addressHandler;
		$this->l = $l;
		$this->logger = $logger;
		$this->dbConn = $dbConn;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	/**
	 * Adapted from FederatedShareProvider::createFederatedShare
	 * See https://github.com/owncloud/core/blob/v10.11.0/apps/federatedfilesharing/lib/FederatedShareProvider.php#L220
	 *
	 * @param IShare $share
	 * @param string $fedGroupId
	 * @param string $remote
	 * @return void
	 */
	private function sendOcmInvite($share, $fedGroupId, $remote) {
		error_log("Send OCM invite (share, $fedGroupId, $remote)");
		try {
			$sharedBy = $share->getSharedBy();
			if ($this->userManager->userExists($sharedBy)) {
				$sharedByAddress = $this->addressHandler->getLocalUserFederatedAddress($sharedBy);
			} else {
				$sharedByAddress = new Address($sharedBy);
			}

			$owner = $share->getShareOwner();
			$ownerAddress = $this->addressHandler->getLocalUserFederatedAddress($owner);
			$sharedWith = $share->getSharedWith() . "@" . $remote;
			$shareWithAddress = new Address($sharedWith);
			$token = "a good question"; // FIXME this will be null because the DefaultShareProvider doesn't set this?
			error_log("Calling sendRemoteShare!");
			error_log(var_export([				$shareWithAddress,
			$ownerAddress,
			$sharedByAddress,
			$token,
			$share->getNode()->getName(),
			$share->getId(),
			\OCP\Share::SHARE_TYPE_REMOTE_GROUP], true));
			// protected function sendOcmRemoteShare(
			// 	Address $shareWithAddress,
			// 	Address $ownerAddress,
			// 	Address $sharedByAddress,
			// 	$token,
			// 	$name,
			// 	$remote_id,
			// 	$remoteShareType = 6) {

			$result = $this->notifications->sendRemoteShare(
				$shareWithAddress,
				$ownerAddress,
				$sharedByAddress,
				$token,
				$share->getNode()->getName(),
				$share->getId(),
				\OCP\Share::SHARE_TYPE_REMOTE_GROUP
			);

			/* Check for failure or null return from sending and pick up an error message
			 * if there is one coming from the remote server, otherwise use a generic one.
			 */
			if (\is_bool($result)) {
				$status = $result;
			} elseif (isset($result['ocs']['meta']['status'])) {
				$status = $result['ocs']['meta']['status'];
			} else {
				$status = false;
			}

			if ($status === false) {
				$msg = $result['ocs']['meta']['message'] ?? false;
				if (!$msg) {
					$message_t = $this->l->t(
						'Sharing %s failed, could not find %s, maybe the server is currently unreachable.',
						[$share->getNode()->getName(), $share->getSharedWith()]
					);
				} else {
					$message_t = $this->l->t("Federated Sharing failed: %s", [$msg]);
				}
				throw new \Exception($message_t);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to notify remote server of mixed group share, panic (' . $e->getMessage() . ')');
			// $this->removeShareFromTableById($shareId);
			throw $e;
		}	}

	private function customGroupHasForeignersFrom($remote, $customGroupId) {
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$cursor = $queryBuilder->select('user_id')->from('custom_group_member')
			->where($queryBuilder->expr()->eq('group_id', $queryBuilder->createNamedParameter($customGroupId, IQueryBuilder::PARAM_INT)))
			->where($queryBuilder->expr()->like('user_id', $queryBuilder->createNamedParameter("%#$remote", IQueryBuilder::PARAM_STR)))
			->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();
		error_log("Got row:");
		error_log(var_export($row, true));
		return ($row !== false);
	}

	private function regularGroupHasForeignersFrom($remote, $regularGroupId) {
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$cursor = $queryBuilder->select('uid')->from('group_user')
			->where($queryBuilder->expr()->eq('gid', $queryBuilder->createNamedParameter($regularGroupId, IQueryBuilder::PARAM_STR)))
			->where($queryBuilder->expr()->like('uid', $queryBuilder->createNamedParameter("%#$remote", IQueryBuilder::PARAM_STR)))
			->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();
		error_log("Got row:");
		error_log(var_export($row, true));
		return ($row !== false);
	}

	public function notifyNewRegularGroupMember($userId, $regularGroupId) {
		return; // FIXME
		if (str_contains($userId, '#')) {
			$parts = explode('#', $userId);
			$remote = $parts[1];
			error_log("Checking if we need to send any OCM invites to $remote");
			if (!$this->regularGroupHasForeignersFrom($remote, $regularGroupId)) {
				$sharesToThisGroup = $this->getSharesToRegularGroup($regularGroupId);
				for ($i = 0; $i < count($sharesToThisGroup); $i++) {
					$this->sendOcmInvitesFor(
						$sharesToThisGroup[$i]->getSharedBy(),
						$sharesToThisGroup[$i]->getShareOwner(),
						$regularGroupId,
						$remote,
						$$sharesToThisGroup[$i]->getName()
					);
				}
			}
		} else {
			error_log("Local user, no need to check for OCM invites to send");
		}
	}
	public function notifyNewCustomGroupMember($userId, $customGroupId) {
		return; // FIXME
		if (str_contains($userId, '#')) {
			$parts = explode('#', $userId);
			$remote = $parts[1];
			error_log("Checking if we need to send any OCM invites to $remote");
			if (!$this->customGroupHasForeignersFrom($remote, $customGroupId)) {
				$sharesToThisGroup = $this->getSharesToCustomGroup($customGroupId);
				for ($i = 0; $i < count($sharesToThisGroup); $i++) {
					$this->sendOcmInvitesFor($remote, $sharesToThisGroup[$i]);
				}
			}
		} else {
			error_log("Local user, no need to check for OCM invites to send");
		}
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
		error_log("MixedGroupShareProvider create calling parent");
		// Create group share locally
		$created = parent::create($share);
		error_log("MixedGroupShareProvider create called parent");
		$remotes = [];
		// Send OCM invites to remote group members
		error_log("Sending OCM invites");
		error_log($share->getSharedWith());
		$group = $this->groupManager->get($share->getSharedWith());
		error_log("Got group");
		$backend = $group->getBackend();
		error_log("Got backend");
		$recipients = $backend->usersInGroup($share->getSharedWith());
		error_log("Got recipients");
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
			$this->sendOcmInvite($share, $share->getSharedWith(), $remote);
		}
	}
	public function getAllSharedWith($userId, $node){
		error_log("you `getAllSharedWith` me on MixedGroupShareProvider...");
		return parent::getAllSharedWith($userId, $node);
	}

	public function getSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0){
		error_log("you `getSharedWith` on MixedGroupShareProvider...");
		return parent::getSharedWith($userId, $shareType, $node, $limit, $offset);
	}

	
}
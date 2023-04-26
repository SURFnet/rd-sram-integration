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
use OC\Share20\Share;
use OCP\Share\IShareProvider;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;


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
	 * Note this is private in the parent class
	 * so we need to keep a copy of it here
	 * @var IRootFolder
	 */
	private $rootFolder;

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
		$this->rootFolder = $rootFolder;
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocMixFederatedSharing';
	}

	/**
	 * Adapted from FederatedShareProvider::createFederatedShare
	 * See https://github.com/owncloud/core/blob/v10.11.0/apps/federatedfilesharing/lib/FederatedShareProvider.php#L220
	 *
	 * @param IShare $share
	 * @param string $remote
	 * @return void
	 */
	private function sendOcmInvite($share, $remote) {
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
			/*error_log("Calling sendRemoteShare!");
			error_log(var_export([				$shareWithAddress,
			$ownerAddress,
			$sharedByAddress,
			$token,
			$share->getNode()->getName(),
			$share->getId(),
			\OCP\Share::SHARE_TYPE_REMOTE_GROUP], true));*/

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
			// FIXME: https://github.com/SURFnet/rd-sram-integration/issues/92
			// $this->removeShareFromTableById($shareId);
			throw $e;
		}
	}

	/**
	 * Copied from OCA\FederatedFilesSharing\FederatedShareProvider:
	 * Create a share object from an database row
	 *
	 * @param array $data
	 * @return IShare
	 * @throws InvalidShare
	 * @throws ShareNotFound
	 */
	private function createShareObject($data) {
		error_log("MixedGroupShareProvider createShareObjectt");
		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId($data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setMailSend((bool)$data['mail_send'])
			->setToken($data['token']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);
		$share->setSharedWith($data['share_with']);

		if ($data['uid_initiator'] !== null) {
			$share->setShareOwner($data['uid_owner']);
			$share->setSharedBy($data['uid_initiator']);
		} else {
			//OLD SHARE
			$share->setSharedBy($data['uid_owner']);
			$path = $this->getNode($share->getSharedBy(), (int)$data['file_source']);

			$owner = $path->getOwner();
			$share->setShareOwner($owner->getUID());
		}

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			$share->setExpirationDate($expiration);
		}

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		$share->setProviderId($this->identifier());

		return $share;
	}

	private function customGroupHasForeignersFrom($remote, $customGroupId) {
		error_log("MixedGroupShareProvider customGroupHasForeignersFrom");
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$cursor = $queryBuilder->select('user_id')->from('custom_group_member')
			->where($queryBuilder->expr()->eq('group_id', $queryBuilder->createNamedParameter($customGroupId, IQueryBuilder::PARAM_INT)))
			->where($queryBuilder->expr()->like('user_id', $queryBuilder->createNamedParameter("%#$remote", IQueryBuilder::PARAM_STR)))
			->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();
		error_log("Got row like '%#$remote':");
		error_log(var_export($row, true));
		return ($row !== false);
	}

	private function regularGroupHasForeignersFrom($remote, $regularGroupId) {
		error_log("MixedGroupShareProvider regularGroupHasForeignersFrom");
	
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
		error_log("MixedGroupShareProvider notifyNewRegularGroupMember");
		if (str_contains($userId, '#')) {
			$parts = explode('#', $userId);
			$remote = $parts[1];
			error_log("Checking if we need to send any OCM invites to $remote");
			if (!$this->regularGroupHasForeignersFrom($remote, $regularGroupId)) {
				$sharesToThisGroup = $this->getSharesToRegularGroup($regularGroupId);
				for ($i = 0; $i < count($sharesToThisGroup); $i++) {
					$this->sendOcmInvite($sharesToThisGroup[$i], $remote);
				}
			}
		} else {
			error_log("Local user, no need to check for OCM invites to send");
		}
	}
	private function getSharesToRegularGroup($regularGroupId) {
		error_log("MixedGroupShareProvider getSharesToRegularGroup");
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($regularGroupId, IQueryBuilder::PARAM_STR)));

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_INT)));

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			error_log("Found another shares with share_with $regularGroupId and share_type " . \OCP\Share::SHARE_TYPE_GROUP);
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();
    	error_log("returning " . count($shares) . " shares");
		return $shares;
	}

	private function getSharesToCustomGroup($customGroupId) {
		error_log("MixedGroupShareProvider getSharesToCustomGroup");

    	$qb = $this->dbConn->getQueryBuilder();
		$qb->select('uri')
		  ->from('custom_group');

		$qb->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($customGroupId, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		if (!$data) {
			error_log("Custom group_id not found $customGroupId");
			return [];
		}
		$groupUri = $data['uri'];
		error_log("Found uri '$groupUri' for custom group $customGroupId");
    	return $this->getSharesToRegularGroup('customgroup_' . $groupUri);
	}
	
	public function notifyNewCustomGroupMember($userId, $customGroupId) {
		error_log("MixedGroupShareProvider notifyNewCustomGroupMember");
		if (str_contains($userId, '#')) {
			$parts = explode('#', $userId);
			$remote = $parts[1];
			error_log("Checking if we need to send any OCM invites to $remote");
			if (!$this->customGroupHasForeignersFrom($remote, $customGroupId)) {
				$sharesToThisGroup = $this->getSharesToCustomGroup($customGroupId);
				error_log("looping over " . count($sharesToThisGroup) . " shares");
				for ($i = 0; $i < count($sharesToThisGroup); $i++) {
					error_log("sending OCM invite for $remote about share id " . $sharesToThisGroup[$i]->getId());
					$this->sendOcmInvite($sharesToThisGroup[$i], $remote);
				}
			}
		} else {
			error_log("Local user, no need to check for OCM invites to send");
		}
	}

	public function newDomainInGroup($remote, $groupId) {
		error_log("MixedGroupShareProvider newDomainInGroup");
		// Note that we assume all federated groups are regular groups.
		$shares = $this->getSharesToRegularGroup($groupId);
		foreach ($shares as $share) {
			$this->sendOcmInvite($share, $remote);
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
		$remotes = [];
		// Send OCM invites to remote group members
		$group = $this->groupManager->get($share->getSharedWith());
		$backend = $group->getBackend();
		$recipients = $backend->usersInGroup($share->getSharedWith());
		foreach($recipients as $k => $v) {
			$parts = explode(self::SEPARATOR, $v);
			if (count($parts) == 2) {
				$remotes[$parts[1]] = true;
			} else {
				error_log("Local user: $v");
			}
		}
		foreach($remotes as $remote => $_dummy) {
			$this->sendOcmInvite($share, $remote);
		}
	}
	public function getAllSharedWith($userId, $node){
		return parent::getAllSharedWith($userId, $node);
	}

	public function getSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0){
		return parent::getSharedWith($userId, $shareType, $node, $limit, $offset);
	}

	
}

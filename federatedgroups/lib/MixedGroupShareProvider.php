<?php

// Copyright (c) 2018, ownCloud GmbH
// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\FederatedGroups;

use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
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
use OCA\FederatedFileSharing\TokenHandler;
use OC\Share20\Exception\InvalidShare;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;

/**
 * Class MixedGroupShareProvider
 *
 * @package OC\Share20
 */
class MixedGroupShareProvider extends DefaultShareProvider implements IShareProvider {
	// For representing foreign group members
	// e.g. 'marie#oc2.docker'
	public const SEPARATOR = '#';

	/** @var GroupNotifications */
	private $groupNotifications;
		
	/** @var TokenHandler */
	private $tokenHandler;

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
	 * @param GroupNotifications $groupNotifications
	 * @param TokenHandler $tokenHandler
	 * @param AddressHandler $addressHandler
	 * @param IL10N $l
	 * @param ILogger $logger
	 */
	public function __construct(
		IDBConnection $dbConn,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IRootFolder $rootFolder,
		GroupNotifications $groupNotifications,
		TokenHandler $tokenHandler,
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
		$this->groupNotifications = $groupNotifications;
		$this->addressHandler = $addressHandler;
		$this->l = $l;
		$this->logger = $logger;
		$this->dbConn = $dbConn;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->rootFolder = $rootFolder;
		$this->tokenHandler = $tokenHandler; 
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
	public function sendOcmInvite($share, $remote) {
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
			
			///// TODO =>>> set sharedSecret
			$result = $this->groupNotifications->sendRemoteShare(
				$shareWithAddress,
				$ownerAddress,
				$sharedByAddress,
				$share->getToken(),
				$share->getNode()->getName(),
				$share->getId()
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
	 * Get the node with file $id for $user
	 *
	 * @param string $userId
	 * @param int $id
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws InvalidShare
	 */
	private function getNode($userId, $id) {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\OCP\Files\NotFoundException $e) {
			throw new InvalidShare();
		}

		$nodes = $userFolder->getById($id, true);

		if (empty($nodes)) {
			throw new InvalidShare();
		}

		return $nodes[0];
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
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$cursor = $queryBuilder->select('user_id')->from('custom_group_member')
			->where($queryBuilder->expr()->eq('group_id', $queryBuilder->createNamedParameter($customGroupId, IQueryBuilder::PARAM_INT)))
			->where($queryBuilder->expr()->like('user_id', $queryBuilder->createNamedParameter("%#$remote", IQueryBuilder::PARAM_STR)))
			->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();
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
		return ($row !== false);
	}

	private function notifyNewRegularGroupMember($userId, $regularGroupId) {
		if (str_contains($userId, '#')) {
			$parts = explode('#', $userId);
			$remote = $parts[1];
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
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($regularGroupId, IQueryBuilder::PARAM_STR)));

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_INT)));

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();
		return $shares;
	}

	private function getSharesToCustomGroup($customGroupId) {
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
    	return $this->getSharesToRegularGroup('customgroup_' . $groupUri);
	}
	
	private function notifyNewCustomGroupMember($userId, $customGroupId) {
		if (str_contains($userId, '#')) {
			$parts = explode('#', $userId);
			$remote = $parts[1];
			if (!$this->customGroupHasForeignersFrom($remote, $customGroupId)) {
				$sharesToThisGroup = $this->getSharesToCustomGroup($customGroupId);
				for ($i = 0; $i < count($sharesToThisGroup); $i++) {
					$this->sendOcmInvite($sharesToThisGroup[$i], $remote);
				}
			}
		} else {
			error_log("Local user, no need to check for OCM invites to send");
		}
	}

	public function sendOcmInviteForExistingShares($remote, $groupId) {
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
		// Create group share locally
		$created = parent::create($share);
		if($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP){
			$remotes = $this->getRemotePartieslist($share->getSharedWith());
			$created->setToken($this->tokenHandler->generateToken());
			// Send OCM invites to remote group members
			try {
				foreach (\array_unique($remotes) as $remote) {
					$this->sendOcmInvite($created, $remote);
				}
				$qb = $this->dbConn->getQueryBuilder();
				$qb->update('share')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($created->getId())))
					->set('token', $qb->createNamedParameter($created->getToken()))
					->execute();

			}
			catch (\Exception $e){
				throw $e;
			}
			return $created;
		}
	}

	/**
	 * Delete all shares received by this group. As well as any custom group
	 * shares for group members.
	 *
	 * @param string $gid
	 */

	public function delete(\OCP\Share\IShare $share) {
		parent::delete($share);
		if ($share->getShareType() == \OCP\Share::SHARE_TYPE_GROUP ){
			$remotes = $this->getRemotePartieslist($share->getSharedWith());
			foreach($remotes as $remote){
				$this->sendUnshareNotification($share, $remote);
			}
		}
	}

	/**
	* @param IShare $share
	* @param string $remote
	* @return void
	*/
   	private function sendUnshareNotification($share, $remote) {
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
		   
		   $result = $this->groupNotifications->sendRemoteUnshare(
				$remote, $share->getId(), $share->getToken()			   
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
					   'Unsharing %s failed, could not find %s, maybe the server is currently unreachable.',
					   [$share->getNode()->getName(), $share->getSharedWith()]
				   );
			   } else {
				   $message_t = $this->l->t("remote Unsharing failed: %s", [$msg]);
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
	 * Get a share by token
	 *
	 * @param string $token
	 * @return \OCP\Share\IShare
	 * @throws ShareNotFound
	 */
	public function getShareByToken($token) {
		$qb = $this->dbConn->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where(
				$qb->expr()->orX()
					->add($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_LINK)))
					->add($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP))) 
					)
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->execute();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new \OCP\Share\Exceptions\ShareNotFound();
		}

		try {
			$share = $this->createShare($data);
		} catch (\OC\Share20\Exception\InvalidShare $e) {
			throw new ShareNotFound();
		}

		return $share;
	}


	private function getRemotePartieslist($groupName){
		$remotes = [];
		$group = $this->groupManager->get($groupName);
		$backend = $group->getBackend();
		$recipients = $backend->usersInGroup($groupName);
			foreach ($recipients as $k => $v) {
			$parts = explode(self::SEPARATOR, $v);
			if (count($parts) > 1) {
				$remotes[] = $parts[1];
			}
		}
		return $remotes; 
	}

	/**
	 * Create a share object from an database row
	 *
	 * @param mixed[] $data
	 * @return \OCP\Share\IShare
	 * @throws InvalidShare
	 */
	private function createShare($data) {
		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId($data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setMailSend((bool)$data['mail_send']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);

		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			$share->setSharedWith($data['share_with']);
		} elseif ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			$share->setSharedWith($data['share_with']);
			$share->setToken($data['token']);
		} elseif ($share->getShareType() === \OCP\Share::SHARE_TYPE_LINK) {
			$share->setPassword($data['share_with']);
			$share->setToken($data['token']);
		}

		$share = $this->updateShareAttributes($share, $data['attributes']);

		$share->setSharedBy($data['uid_initiator']);
		$share->setShareOwner($data['uid_owner']);

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);
		$share->setName($data['share_name']);
		$share->setState((int)$data['accepted']);

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			$share->setExpirationDate($expiration);
		}

		$share->setProviderId($this->identifier());

		return $share;
	}

	/**
	 * Load from database format (JSON string) to IAttributes
	 *
	 * @param IShare $share
	 * @param string|null $data
	 * @return IShare modified share
	 */
	private function updateShareAttributes(\OCP\Share\IShare $share, $data) {
		if ($data !== null) {
			$attributes = new \OC\Share20\ShareAttributes();
			$compressedAttributes = \json_decode($data, true);
			foreach ($compressedAttributes as $compressedAttribute) {
				$attributes->setAttribute(
					$compressedAttribute[0],
					$compressedAttribute[1],
					$compressedAttribute[2]
				);
			}
			$share->setAttributes($attributes);
		}

		return $share;
	}

	public function getShareById($id, $recipientId = null) {
		if (!ctype_digit($id)) {
			// share id is defined as a field of type integer
			// if someone calls the API asking for a share id like "abc"
			// then there is no point trying to query the database,
			// and, depending on the database, the query may throw an exception
			// with a message like "invalid input syntax for type integer"
			// So throw ShareNotFound now.
			throw new ShareNotFound();
		}
		$qb = $this->dbConn->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->in(
					'share_type',
					$qb->createNamedParameter([
						\OCP\Share::SHARE_TYPE_USER,
						\OCP\Share::SHARE_TYPE_GROUP,
						\OCP\Share::SHARE_TYPE_LINK,
					], IQueryBuilder::PARAM_INT_ARRAY)
				)
			)
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		try {
			$share = $this->createShare($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		// If the recipient is set for a group share resolve to that user
		if ($recipientId !== null && $share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			$resolvedShares = $this->resolveGroupShares([$share], $recipientId);
			if (\count($resolvedShares) === 1) {
				// If we pass to resolveGroupShares() an with one element,
				// we expect to receive exactly one element, otherwise it is error
				$share = $resolvedShares[0];
			} else {
				throw new ProviderException("ResolveGroupShares() returned wrong result");
			}
		}

		return $share;
	}

	
}

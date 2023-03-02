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
use OCA\FederatedFileSharing\Address;
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

	/** @var AddressHandler */
	private $addressHandler; 

	/** @var TokenHandler  */
	private $tokenHandler;

	/** @var IL10N */
	private $l; 
	/** @var ILogger */
	private $logger ; 
	private $dbConnection;
	private $shareTable = 'share';
	private $externalShareTable= 'share_external_group'; 
	private $rootFolder;
	/** @var IUserManager */
	private $userManager;
	const SHARE_TYPE_REMOTE_GROUP = 7;

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
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->addressHandler = $addressHandler;
		$this->tokenHandler = $tokenHandler;
		$this->logger = $logger; 
		$this->l = $l10n;
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocGroupFederatedSharing';
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
		$shareWith = $share->getSharedWith();
		$itemSource = $share->getNodeId();
		$itemType = $share->getNodeType();
		$permissions = $share->getPermissions();
		$expiration = $share->getExpirationDate();
		$sharedBy = $share->getSharedBy();

		/*
		 * Check if file is not already shared with the remote user
		 */
		$alreadyShared = $this->getSharedWith($shareWith, self::SHARE_TYPE_REMOTE_GROUP, $share->getNode(), 1, 0);
		if (!empty($alreadyShared)) {
			$message = 'Sharing %s failed, because this item is already shared with %s';
			$message_t = $this->l->t('Sharing %s failed, because this item is already shared with %s', [$share->getNode()->getName(), $shareWith]);
			$this->logger->debug(\sprintf($message, $share->getNode()->getName(), $shareWith), ['app' => 'Federated File Sharing']);
			throw new \Exception($message_t);
		}

		// don't allow federated shares if source and target server are the same
		$currentUser = $sharedBy;
		$ownerAddress =  $this->addressHandler->getLocalUserFederatedAddress($currentUser);
		$shareWithAddress = new Address($shareWith);

		if ($ownerAddress->equalTo($shareWithAddress)) {
			$message = 'Not allowed to create a federated share with the same user.';
			$message_t = $this->l->t('Not allowed to create a federated share with the same user');
			$this->logger->debug($message, ['app' => 'Federated File Sharing']);
			throw new \Exception($message_t);
		}

		$share->setSharedWith($shareWithAddress->getCloudId());

		try {
			$remoteShare = $this->getShareFromExternalShareTable($share);
		} catch (ShareNotFound $e) {
			$remoteShare = null;
		}

		if ($remoteShare) {
			try {
				$uidOwner = $remoteShare['owner'] . '@' . $remoteShare['remote'];
				$shareId = $this->addShareToDB($itemSource, $itemType, $shareWith, $sharedBy, $uidOwner, $permissions, $expiration, 'tmp_token_' . \time(), $share->getShareType());
				$share->setId($shareId);
				list($token, $remoteId) = $this->askOwnerToReShare($shareWith, $share, $shareId);
				// remote share was create successfully if we get a valid token as return
				$send = \is_string($token) && $token !== '';
			} catch (\Exception $e) {
				// fall back to old re-share behavior if the remote server
				// doesn't support flat re-shares (was introduced with ownCloud 9.1)
				$this->removeShareFromTable($share);
				$shareId = $this->createFederatedShare($share);
			}
			if ($send) {
				$this->updateSuccessfulReShare($shareId, $token);
				$this->storeRemoteId($shareId, $remoteId);
			} else {
				$this->removeShareFromTable($share);
				$message_t = $this->l->t('File is already shared with %s', [$shareWith]);
				throw new \Exception($message_t);
			}
		} else {
			$shareId = $this->createFederatedShare($share);
		}

		$data = $this->getRawShare($shareId);
		return $this->createShareObject($data);
	}
	public function getAllSharedWith($userId, $node){
		return parent::getAllSharedWith($userId, $node);
	}

	public function getSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0){
		return parent::getSharedWith($userId, $shareType, $node, $limit, $offset);
	}

	public function getExternalManager($userId){
		return new \OCA\FederatedGroups\Files_Sharing\External\Manager(
			$this->dbConnection,
			\OC\Files\Filesystem::getMountManager(),
			\OC\Files\Filesystem::getLoader(),
			\OC::$server->getNotificationManager(),
			\OC::$server->getEventDispatcher(),
			$userId
		);
	}

	public function getAllSharesBy($userId, $shareTypeArray, $nodeIDs, $reshares){
		$shares = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from($this->shareTable);

		// In federated sharing currently we have only one share_type_remote
		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_REMOTE_GROUP)));

		$qb->andWhere($qb->expr()->in('file_source', $qb->createParameter('file_source_ids')));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			//Special case for old shares created via the web UI
			$or1 = $qb->expr()->andX(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
				$qb->expr()->isNull('uid_initiator')
			);

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
					$or1
				)
			);
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		$qb->orderBy('id');
		$nodeIdsChunks = \array_chunk($nodeIDs, 900);
		foreach ($nodeIdsChunks as $nodeIdsChunk) {
			$qb->setParameter('file_source_ids', $nodeIdsChunk, IQueryBuilder::PARAM_INT_ARRAY);

			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				$shares[] = $this->createShareObject($data);
			}
			$cursor->closeCursor();
		}
		return $shares;
	}
	
	private function createShareObject($data) {
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

	/**
	 * create federated share and inform the recipient
	 *
	 * @param IShare $share
	 * @return int
	 * @throws ShareNotFound
	 * @throws \Exception
	 */
	protected function createFederatedShare(IShare $share) {
		error_log("createFederatedShare");
		$token = $this->tokenHandler->generateToken();
		$shareId = $this->addShareToDB(
			$share->getNodeId(),
			$share->getNodeType(),
			$share->getSharedWith(),
			$share->getSharedBy(),
			$share->getShareOwner(),
			$share->getPermissions(),
			$share->getExpirationDate(),
			$token,
			$share->getShareType()
		);

		try {
			$sharedBy = $share->getSharedBy();
			if ($this->userManager->userExists($sharedBy)) {
				$sharedByAddress = $this->addressHandler->getLocalUserFederatedAddress($sharedBy);
			} else {
				$sharedByAddress = new Address($sharedBy);
			}

			$owner = $share->getShareOwner();
			$ownerAddress = $this->addressHandler->getLocalUserFederatedAddress($owner);
			$sharedWith = $share->getSharedWith();
			$shareWithAddress = new Address($sharedWith);
			$result = $this->notifications->sendRemoteShare(
				$shareWithAddress,
				$ownerAddress,
				$sharedByAddress,
				$token,
				$share->getNode()->getName(),
				$shareId,
				$share->getShareType()
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
			$this->logger->error('Failed to notify remote server of federated share, removing share (' . $e->getMessage() . ')');
			$this->removeShareFromTableById($shareId);
			throw $e;
		}

		return $shareId;
	}

	private function addShareToDB($itemSource, $itemType, $shareWith, $sharedBy, $uidOwner, $permissions, $expiration, $token, $shareType = SHARE_TYPE_REMOTE ) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert($this->shareTable)
			->setValue('share_type', $qb->createNamedParameter($shareType))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_owner', $qb->createNamedParameter($uidOwner))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('expiration', $qb->createNamedParameter($expiration, IQueryBuilder::PARAM_DATE))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('stime', $qb->createNamedParameter(\time()));

		/*
		 * Added to fix https://github.com/owncloud/core/issues/22215
		 * Can be removed once we get rid of ajax/share.php
		 */
		$qb->setValue('file_target', $qb->createNamedParameter(''));

		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}


	/**
	 * get database row of a give share
	 *
	 * @param $id
	 * @return array
	 * @throws ShareNotFound
	 */
	private function getRawShare($id) {

		// Now fetch the inserted share and create a complete share object
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from($this->shareTable)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound;
		}

		return $data;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from($this->shareTable);

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_REMOTE_GROUP)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			//Special case for old shares created via the web UI
			$or1 = $qb->expr()->andX(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
				$qb->expr()->isNull('uid_initiator')
			);

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
					$or1
				)
			);
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->setFirstResult($offset);
		$qb->orderBy('id');
		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();
		return $shares;
	}
}

<?php

/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\FederatedGroups\FederatedFileSharing;

use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Address;
use OCA\Files_Sharing\Activity;
use OCP\Activity\IManager as ActivityManager;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use OCP\Share\IShare;
use OCP\Share;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

// For user-to-user OCM:
use OCA\FederatedFileSharing\FederatedShareProvider;
// For user-to-group OCM:
use OCA\FederatedGroups\FederatedGroupShareProvider;

/**
 * Class FedShareManager holds the share logic
 *
 * @package OCA\FederatedFileSharing
 */
class FedShareManager {
	public const ACTION_URL = 'ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending/';

	/**
	 * @var FederatedShareProvider
	 */
	private $federatedUserShareProvider;

	/**
	 * @var FederatedGroupShareProvider
	 */
	private $federatedGroupShareProvider;

	/**
	 * @var \OCA\FederatedFileSharing\Notifications
	 */
	private $federatedUserNotifications;

	/**
	 * @var \OCA\FederatedGroups\FederatedFileSharing\Notifications
	 */
	private $federatedGroupNotifications;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ActivityManager
	 */
	private $activityManager;

	/**
	 * @var NotificationManager
	 */
	private $notificationManager;

	/**
	 * @var AddressHandler
	 */
	private $addressHandler;

	/**
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * @var EventDispatcherInterface
	 */
	private $eventDispatcher;

	/**
	 * FedShareManager constructor.
	 *
	 * @param FederatedShareProvider $federatedUserShareProvider
	 * @param FederatedGroupShareProvider $federatedGroupShareProvider
	 * @param \OCA\FederatedFileSharing\Notifications $federatedUserNotifications
	 * @param \OCA\FederatedGroups\FederatedFileSharing\Notifications $federatedGroupNotifications
	 * @param IUserManager $userManager
	 * @param ActivityManager $activityManager
	 * @param NotificationManager $notificationManager
	 * @param AddressHandler $addressHandler
	 * @param Permissions $permissions
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct(
		FederatedShareProvider $federatedUserShareProvider,
		FederatedGroupShareProvider $federatedGroupShareProvider,
		\OCA\FederatedFileSharing\Notifications $federatedUserNotifications,
		\OCA\FederatedGroups\FederatedFileSharing\Notifications $federatedGroupNotifications,
		IUserManager $userManager,
		ActivityManager $activityManager,
		NotificationManager $notificationManager,
		AddressHandler $addressHandler,
		Permissions $permissions,
		EventDispatcherInterface $eventDispatcher
	) {
		$this->federatedUserShareProvider = $federatedUserShareProvider;
		$this->federatedGroupShareProvider = $federatedGroupShareProvider;
		$this->federatedUserNotifications = $federatedUserNotifications;
		$this->federatedGroupNotifications = $federatedGroupNotifications;
		$this->userManager = $userManager;
		$this->activityManager = $activityManager;
		$this->notificationManager = $notificationManager;
		$this->addressHandler = $addressHandler;
		$this->permissions = $permissions;
		$this->eventDispatcher = $eventDispatcher;
	}

	private function getProviderForOcmShareType($ocmShareType) {
		// error_log("oooooooo getProviderForOcmShareType ocmShareType {$ocmShareType}");
		if ($ocmShareType == 'user' || $ocmShareType == 6) {
			return $this->federatedUserShareProvider;
		} else if ($ocmShareType == 'group') {
			return $this->federatedGroupShareProvider;
		} else {
			error_log("Unsupported share type $ocmShareType");
			throw new \Exception("Unsupported share type");
		}
	}

	/**
	 * Create an incoming share
	 *
	 * @param Address $ownerAddress
	 * @param Address $sharedByAddress
	 * @param string $shareWith
	 * @param string $remoteId
	 * @param string $name
	 * @param string $token
	 *
	 * @return void
	 */
	public function createShare(
		Address $ownerAddress,
		Address $sharedByAddress,
		$shareWith,
		$remoteId,
		$name,
		$token,
		$ocmShareType
	) {
		error_log("Our FedShareManager creating share of type $ocmShareType");
		$owner = $ownerAddress->getUserId();
		$remote = $ownerAddress->getOrigin();
		$shareId = $this->getProviderForOcmShareType($ocmShareType)->addShare(
			$remote,
			$token,
			$name,
			$owner,
			$shareWith,
			$remoteId
		);

		$this->eventDispatcher->dispatch(
			'\OCA\FederatedFileSharing::remote_shareReceived',
			new GenericEvent(
				null,
				[
					'name' => $name,
					'targetuser' => $sharedByAddress->getCloudId(),
					'owner' => $owner,
					'sharewith' => $shareWith,
					'sharedby' => $sharedByAddress->getUserId(),
					'remoteid' => $remoteId
				]
			)
		);
		$this->publishActivity(
			$shareWith,
			Activity::SUBJECT_REMOTE_SHARE_RECEIVED,
			[$ownerAddress->getCloudId(), \trim($name, '/'), ['shareId' => $shareId]],
			'files',
			'',
			'',
			''
		);
		$link = $this->getActionLink($shareId);
		$params = [
			$ownerAddress->getCloudId(),
			$sharedByAddress->getCloudId(),
			\trim($name, '/')
		];
		if (!$this->getProviderForOcmShareType($ocmShareType)->getAccepted($remote, $shareWith)) {
			$notification = $this->createNotification($shareWith);
			$notification->setDateTime(new \DateTime())
				->setObject('remote_share', $shareId)
				->setSubject('remote_share', $params)
				->setMessage('remote_share', $params);
			$declineAction = $notification->createAction();
			$declineAction->setLabel('decline')
				->setLink($link, 'DELETE');
			$notification->addAction($declineAction);
			$acceptAction = $notification->createAction();
			$acceptAction->setLabel('accept')
				->setLink($link, 'POST');
			$notification->addAction($acceptAction);
			$this->notificationManager->notify($notification);
		}
	}

	/**
	 * @param IShare $share
	 * @param string $remoteId
	 * @param string $shareWith
	 * @param int|null $permissions - null for OCM 1.0-proposal1
	 *
	 * @return IShare
	 *
	 * @throws \OCP\Share\Exceptions\ShareNotFound
	 */
	public function reShare(IShare $share, $remoteId, $shareWith, $permissions = null) {
		if ($permissions !== null) {
			$share->setPermissions($share->getPermissions() & $permissions);
		}
		// the recipient of the initial share is now the initiator for the re-share
		$share->setSharedBy($share->getSharedWith());
		$share->setSharedWith($shareWith);
		$result = $this->getProviderForOcmShareType($share->getShareType())->create($share);
		$this->getProviderForOcmShareType($share->getShareType())->storeRemoteId(
			(int)$result->getId(),
			$remoteId
		);
		return $result;
	}

	/**
	 *
	 *
	 * @param IShare $share
	 *
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function acceptShare(IShare $share) {
		$uid = $this->getCorrectUid($share);
		$fileId = $share->getNode()->getId();
		list($file, $link) = $this->getFile($uid, $fileId);
		$this->publishActivity(
			$uid,
			Activity::SUBJECT_REMOTE_SHARE_ACCEPTED,
			[$share->getSharedWith(), \basename($file)],
			'files',
			$fileId,
			$file,
			$link
		);
		$this->notifyRemote($share, [$this->notifications, 'sendAcceptShare']);
	}


	/**
	 * Delete declined share and create a activity
	 *
	 * @param IShare $share
	 *
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function declineShare(IShare $share) {
		$this->notifyRemote($share, [$this->notifications, 'sendDeclineShare']);
		$uid = $this->getCorrectUid($share);
		$fileId = $share->getNode()->getId();
		$this->getProviderForOcmShareType($share->getType())->removeShareFromTable($share);
		list($file, $link) = $this->getFile($uid, $fileId);
		$this->publishActivity(
			$uid,
			Activity::SUBJECT_REMOTE_SHARE_DECLINED,
			[$share->getSharedWith(), \basename($file)],
			'files',
			$fileId,
			$file,
			$link
		);
	}

	/**
	 * Unshare an item from self
	 *
	 * @param int $id
	 * @param string $token
	 * @param int $ocmShareType
	 *
	 * @return void
	 */
	public function unshare($id, $token, $ocmShareType) {
		$shareRow = $this->getProviderForOcmShareType($ocmShareType)->unshare($id, $token);
		if ($shareRow === false) {
			return;
		}
		$ownerAddress = new Address($shareRow['owner'] . '@' . $shareRow['remote']);
		$mountpoint = $shareRow['mountpoint'];
		$user = $shareRow['user'];
		if ($shareRow['accepted']) {
			$path = \trim($mountpoint, '/');
		} else {
			$path = \trim($shareRow['name'], '/');
		}
		$notification = $this->createNotification($user);
		$notification->setObject('remote_share', (int) $shareRow['id']);
		$this->notificationManager->markProcessed($notification);
		$this->publishActivity(
			$user,
			Activity::SUBJECT_REMOTE_SHARE_UNSHARED,
			[$ownerAddress->getCloudId(), $path],
			'files',
			'',
			'',
			''
		);
	}

	/**
	 * @param IShare $share
	 *
	 * @return void
	 */
	public function undoReshare(IShare $share) {
		$this->getProviderForOcmShareType($share->getType())->removeShareFromTable($share);
	}

	/**
	 * Update permissions
	 *
	 * @param IShare $share
	 * @param string[] $ocmPermissions as ['read', 'write', 'share']
	 *
	 * @return void
	 */
	public function updateOcmPermissions(IShare $share, $ocmPermissions) {
		$permissions = $this->permissions->toOcPermissions($ocmPermissions);
		$this->updatePermissions($share, $permissions);
	}

	/**
	 * Update permissions
	 *
	 * @param IShare $share
	 * @param int $permissions
	 *
	 * @return void
	 */
	public function updatePermissions(IShare $share, $permissions) {
		if ($share->getPermissions() !== $permissions) {
			$share->setPermissions($permissions);
			$this->getProviderForOcmShareType($share->getType())->update($share);
		}
	}

	/**
	 * Check if a federated share was re-shared with another federated server.
	 *
	 * @param IShare $share
	 * @return bool
	 * @throws NotFoundException
	 */
	public function isFederatedReShare(IShare $share) {
		// get all federated shares on this file
		$shares = $this->getProviderForOcmShareType($share->getType())->getSharesByPath($share->getNode());

		foreach ($shares as $matchingShare) {
			// if the share initiator (sharedBy) received a share for this file
			// in the past, this is a re-share
			if ($share->getSharedBy() === $matchingShare->getSharedWith()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * retrieve OCM group shared files
	 * 
	 */
	public function getSharedWithMyGroup($userId, $ocmShareType) {
		return $this->getProviderForOcmShareType($ocmShareType)->getAllSharedWithMyGroup($userId);
	}

	public function getSharedById($id, $ocmShareType) {
		return $this->getProviderForOcmShareType($ocmShareType)->getShareById($id);
	}

	public function acceptSharedFile(string $shareId, $ocmShareType) {
		$this->getProviderForOcmShareType($ocmShareType)->acceptSharedFile($shareId);
	}

	public function getExternalManager($userId = null, $ocmShareType) {
		return $this->getProviderForOcmShareType($ocmShareType)->getExternalManager($userId);
	}


	/**
	 * @param IShare $share
	 * @param callable $callback
	 *
	 * @throws \OCP\Share\Exceptions\ShareNotFound
	 * @throws \OC\HintException
	 */
	protected function notifyRemote($share, $callback) {
		if ($share->getShareOwner() !== $share->getSharedBy()) {
			try {
				list(, $remote) = $this->addressHandler->splitUserRemote(
					$share->getSharedBy()
				);
				$remoteId = $this->getProviderForShare($share)->getRemoteId($share);
				$callback($remote, $remoteId, $share->getToken());
			} catch (\Exception $e) {
				// expected fail if sender is a local user
			}
		}
	}

	/**
	 * Publish a new activity
	 *
	 * @param string $affectedUser
	 * @param string $subject
	 * @param array $subjectParams
	 * @param string $objectType
	 * @param int $objectId
	 * @param string $objectName
	 * @param string $link
	 *
	 * @return void
	 */
	protected function publishActivity(
		$affectedUser,
		$subject,
		$subjectParams,
		$objectType,
		$objectId,
		$objectName,
		$link
	) {
		$event = $this->activityManager->generateEvent();
		$event->setApp(Activity::FILES_SHARING_APP)
			->setType(Activity::TYPE_REMOTE_SHARE)
			->setAffectedUser($affectedUser)
			->setSubject($subject, $subjectParams)
			->setObject($objectType, $objectId, $objectName)
			->setLink($link);
		$this->activityManager->publish($event);
	}

	/**
	 * Get a new notification
	 *
	 * @param string $uid
	 *
	 * @return \OCP\Notification\INotification
	 */
	protected function createNotification($uid) {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('files_sharing');
		$notification->setUser($uid);
		return $notification;
	}

	/**
	 * @param int $shareId
	 * @return string
	 */
	protected function getActionLink($shareId) {
		$urlGenerator = \OC::$server->getURLGenerator();
		return $urlGenerator->linkTo('', self::ACTION_URL . $shareId);
	}

	/**
	 * Get file
	 *
	 * @param string $user
	 * @param int $fileSource
	 *
	 * @return array with internal path of the file and a absolute link to it
	 */
	protected function getFile($user, $fileSource) {
		\OC_Util::setupFS($user);

		try {
			$file = \OC\Files\Filesystem::getPath($fileSource);
		} catch (NotFoundException $e) {
			$file = null;
		}
		// FIXME:  use permalink here, see ViewController for reference
		$args = \OC\Files\Filesystem::is_dir($file)
			? ['dir' => $file]
			: ['dir' => \dirname($file), 'scrollto' => $file];
		$link = \OCP\Util::linkToAbsolute('files', 'index.php', $args);

		return [$file, $link];
	}

	/**
	 * Check if we are the initiator or the owner of a re-share
	 * and return the correct UID
	 *
	 * @param IShare $share
	 *
	 * @return string
	 */
	protected function getCorrectUid(IShare $share) {
		if ($this->userManager->userExists($share->getShareOwner())) {
			return $share->getShareOwner();
		}

		return $share->getSharedBy();
	}
}

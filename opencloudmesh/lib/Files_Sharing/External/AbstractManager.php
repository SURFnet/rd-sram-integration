<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Yashar PM <yashar@pondersource.com>
 * @author Michiel de Jong <michiel@pondersource.com>
 *
 * @copyright Copyright (c) 2023, SURF
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.

 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenCloudMesh\Files_Sharing\External;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OC\Files\Filesystem;
use OC\User\NoUserException;
use OCA\Files_Sharing\Helper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Share\Events\AcceptShare;
use OCP\Share\Events\DeclineShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

abstract class AbstractManager
{
	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var string
	 */
	protected $uid;

	/**
	 * @var \OCP\IDBConnection
	 */
	protected $connection;

	/**
	 * @var \OC\Files\Mount\Manager
	 */
	private $mountManager;

	/**
	 * @var \OCP\Files\Storage\IStorageFactory
	 */
	private $storageLoader;

	/**
	 * @var IManager
	 */
	private $notificationManager;

	/**
	 * @var EventDispatcherInterface
	 */
	private $eventDispatcher;

	/**
	 * @var IUserManager
	 */
	protected $userManager;

	/**
	 * @var IGroupManager
	 */
	protected $groupManager;

	/**
	 * @param \OCP\IDBConnection $connection
	 * @param \OC\Files\Mount\Manager $mountManager
	 * @param \OCP\Files\Storage\IStorageFactory $storageLoader
	 * @param IManager $notificationManager
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param string $uid
	 * @param string $storage
	 */
	public function __construct(
		$storage,
		$tableName,
		\OCP\IDBConnection $connection,
		\OC\Files\Mount\Manager $mountManager,
		\OCP\Files\Storage\IStorageFactory $storageLoader,
		IManager $notificationManager,
		EventDispatcherInterface $eventDispatcher,
		IUserManager $userManager,
		IGroupManager $groupManager,
		$uid = null
	) {
		$this->storage = $storage;
		$this->tableName = $tableName;
		$this->connection = $connection;
		$this->mountManager = $mountManager;
		$this->storageLoader = $storageLoader;
		$this->uid = $uid;
		$this->notificationManager = $notificationManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	/**
	 * add new server-to-server share
	 *
	 * @param string $remote
	 * @param string $token
	 * @param string $password
	 * @param string $name
	 * @param string $owner
	 * @param boolean $accepted
	 * @param string $user
	 * @param string $remoteId
	 * @return Mount|null
	 */
	public function addShare($remote, $token, $password, $name, $owner, $accepted = false, $user = null, $remoteId = -1)
	{
		$user = $user ? $user : $this->uid;
		$accepted = $accepted ? 1 : 0;
		$name = Filesystem::normalizePath('/' . $name);

		if (!$accepted) {
			// To avoid conflicts with the mount point generation later,
			// we only use a temporary mount point name here. The real
			// mount point name will be generated when accepting the share,
			// using the original share item name.
			$tmpMountPointName = '{{TemporaryMountPointName#' . $name . '}}';
			$mountPoint = $tmpMountPointName;
			$hash = \md5($tmpMountPointName);
			$data = [
				'remote' => $remote,
				'share_token' => $token,
				'password' => $password,
				'name' => $name,
				'owner' => $owner,
				'user' => $user,
				'mountpoint' => $mountPoint,
				'mountpoint_hash' => $hash,
				'accepted' => $accepted,
				'remote_id' => $remoteId,
			];

			$this->prepareData($data);

			$i = 1;
			while (!$this->connection->insertIfNotExist("*PREFIX*{$this->tableName}", $data, ['user', 'mountpoint_hash'])) {
				// The external share already exists for the user
				$data['mountpoint'] = $tmpMountPointName . '-' . $i;
				$data['mountpoint_hash'] = \md5($data['mountpoint']);
				$i++;
			}

			return null;
		}

		$shareFolder = Helper::getShareFolder();
		$mountPoint = Files::buildNotExistingFileName($shareFolder, $name);
		$mountPoint = Filesystem::normalizePath($mountPoint);
		$hash = \md5($mountPoint);

		$query = $this->connection->prepare("
				INSERT INTO `*PREFIX*{$this->tableName}`
					(`remote`, `share_token`, `password`, `name`, `owner`, `user`, `mountpoint`, `mountpoint_hash`, `accepted`, `remote_id`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			");
		$query->execute([$remote, $token, $password, $name, $owner, $user, $mountPoint, $hash, $accepted, $remoteId]);

		$options = [
			'remote' => $remote,
			'token' => $token,
			'password' => $password,
			'mountpoint' => $mountPoint,
			'owner' => $owner
		];
		return $this->mountShare($options);
	}

	/**
	 * Prepare data to be inserter into the database. i.e. set values for additional columns.
	 */
	protected function prepareData(array &$data)
	{

	}

	/**
	 * get share
	 *
	 * @param int $id share id
	 * @return mixed share of false
	 */
	protected function fetchShare($id)
	{
		$getShare = $this->connection->prepare("
			SELECT *
			FROM  `*PREFIX*{$this->tableName}`
			WHERE `id` = ?");
		$result = $getShare->execute([$id]);

		return $result ? $getShare->fetch() : false;
	}

	/**
	 * get share
	 *
	 * @param int $id share id
	 * @return mixed share of false
	 */
	abstract public function getShare($id);

	/**
	 * Get the file id for an accepted share. Returns null when
	 * the file id cannot be determined.
	 *
	 * @param mixed $share
	 * @param string $mountPoint
	 * @return string|null
	 */
	public function getShareFileId($share, $mountPoint)
	{
		$options = [
			'remote' => $share['remote'],
			'token' => $share['share_token'],
			'mountpoint' => $mountPoint,
			'owner' => $share['owner']
		];

		// We need to scan the new file/folder here to get its fileId
		// which will be passed to the event for further processing.
		$mount = $this->getMount($options);
		$storage = $mount->getStorage();

		if ($storage) {
			$scanner = $storage->getScanner('');

			// No need to scan all the folder contents as we only care about the root share
			$file = $scanner->scanFile('');

			if (isset($file['fileid'])) {
				return $file['fileid'];
			}
		}

		return null;
	}

	/**
	 * Get the mount point of a newly received share.
	 *
	 * @param mixed $share
	 * @return string
	 */
	public function getShareRecipientMountPoint($share)
	{
		\OC_Util::setupFS($share['user']);
		$shareFolder = Helper::getShareFolder();
		$mountPoint = Files::buildNotExistingFileName($shareFolder, $share['name']);
		return Filesystem::normalizePath($mountPoint);
	}

	/**
	 * accept server-to-server share
	 *
	 * @param int $id
	 * @return bool True if the share could be accepted, false otherwise
	 */
	public function acceptShare($id)
	{
		$share = $this->getShare($id);

		if ($share) {
			$mountPoint = $this->getShareRecipientMountPoint($share);
			$hash = \md5($mountPoint);

			$result = $this->acceptShareInDb($share, $mountPoint, $hash);

			if (!$result) {
				$this->processNotification($id);
				return false;
			}

			$fileId = $this->getShareFileId($share, $mountPoint);

			$this->eventDispatcher->dispatch(
				new AcceptShare($share),
				AcceptShare::class
			);

			$event = new GenericEvent(
				null,
				[
					'sharedItem' => $share['name'],
					'shareAcceptedFrom' => $share['owner'],
					'remoteUrl' => $share['remote'],
					'fileId' => $fileId,
					// can be null in case the file was not scanned properly
					'shareId' => $id,
					'shareRecipient' => $this->uid,
				]
			);
			$this->eventDispatcher->dispatch($event, 'remoteshare.accepted');
			\OC_Hook::emit('OCP\Share', 'federated_share_added', ['server' => $share['remote']]);

			$this->processNotification($id);
			return true;
		}

		return false;
	}

	/**
	 * @return bool True if db could be accepted, false otherwise
	 */
	abstract protected function acceptShareInDb($share, $mountPoint, $hash);

	/**
	 * decline server-to-server share
	 *
	 * @param int $id
	 * @return bool True if the share could be declined, false otherwise
	 */
	public function declineShare($id)
	{
		$share = $this->getShare($id);

		if ($share) {
			$this->executeDeclineShareStatement($share);
			$this->processNotification($id);
			return true;
		}

		return false;
	}

	abstract protected function executeDeclineShareStatement($id);

	/**
	 * @param int $remoteShare
	 */
	public function processNotification($remoteShare)
	{
		$filter = $this->notificationManager->createNotification();
		$filter->setApp('files_sharing')
			->setUser($this->uid)
			->setObject('remote_share', (int) $remoteShare);
		$this->notificationManager->markProcessed($filter);
	}

	/**
	 * remove '/user/files' from the path and trailing slashes
	 *
	 * @param string $path
	 * @return string
	 */
	protected function stripPath($path)
	{
		$prefix = "/{$this->uid}/files";
		return \rtrim(\substr($path, \strlen($prefix)), '/');
	}

	public function getMount($data)
	{
		$data['manager'] = $this;
		$mountPoint = '/' . $this->uid . '/files' . $data['mountpoint'];
		$data['mountpoint'] = $mountPoint;
		$data['certificateManager'] = \OC::$server->getCertificateManager($this->uid);
		return new Mount($this->storage, $mountPoint, $data, $this, $this->storageLoader);
	}

	/**
	 * @param array $data
	 * @return Mount
	 */
	protected function mountShare($data)
	{
		$mount = $this->getMount($data);
		$this->mountManager->addMount($mount);
		return $mount;
	}

	/**
	 * @return \OC\Files\Mount\Manager
	 */
	public function getMountManager()
	{
		return $this->mountManager;
	}

	/**
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	public function setMountPoint($source, $target)
	{
		$source = $this->stripPath($source);
		$target = $this->stripPath($target);
		$sourceHash = \md5($source);
		$targetHash = \md5($target);

		$query = $this->connection->prepare("
			UPDATE `*PREFIX*{$this->tableName}`
			SET `mountpoint` = ?, `mountpoint_hash` = ?
			WHERE `mountpoint_hash` = ?
			AND `user` = ?
		");
		try {
			$result = (bool) $query->execute([$target, $targetHash, $sourceHash, $this->uid]);
		} catch (UniqueConstraintViolationException $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * Explicitly set uid when the shares are managed in CLI
	 *
	 * @param string|null $uid
	 */
	public function setUid($uid)
	{
		// FIXME: External manager should not depend on uid
		$this->uid = $uid;
	}

	/**
	 * @param $mountPoint
	 * @return bool
	 *
	 * @throws NoUserException
	 */
	public function removeShare($mountPoint)
	{
		if ($this->uid === null) {
			throw new NoUserException();
		}

		$mountPointObj = $this->mountManager->find($mountPoint);
		$id = $mountPointObj->getStorage()->getCache()->getId('');

		$mountPoint = $this->stripPath($mountPoint);
		$hash = \md5($mountPoint);

		$getShare = $this->connection->prepare("
			SELECT `id`, `remote`, `share_token`, `remote_id`
			FROM  `*PREFIX*{$this->tableName}`
			WHERE `mountpoint_hash` = ? AND `user` = ?");
		$result = $getShare->execute([$hash, $this->uid]);

		$removeResult = false;

		if ($result) {
			$share = $getShare->fetch();
			if ($share !== false) {
				$removeResult = $this->executeRemoveShareStatement($share, $hash);
				if ($this->shouldNotifyShareDecline($share)) {
					$this->eventDispatcher->dispatch(new DeclineShare($share), DeclineShare::class);
				}
			}
		}
		$getShare->closeCursor();

		if ($removeResult) {
			$this->removeReShares($id);
			$event = new GenericEvent(null, ['user' => $this->uid, 'targetmount' => $mountPoint]);
			$this->eventDispatcher->dispatch($event, '\OCA\Files_Sharing::unshareEvent');
		}

		return $result;
	}

	abstract protected function executeRemoveShareStatement($share, $mountHash);
	abstract protected function shouldNotifyShareDecline($share);

	/**
	 * remove re-shares from share table and mapping in the federated_reshares table
	 *
	 * @param $mountPointId
	 */
	protected function removeReShares($mountPointId)
	{
		$selectQuery = $this->connection->getQueryBuilder();
		$query = $this->connection->getQueryBuilder();
		$selectQuery->select('id')->from('share')
			->where($selectQuery->expr()->eq('file_source', $query->createNamedParameter($mountPointId)));
		$select = $selectQuery->getSQL();

		$query->delete('federated_reshares')
			->where($query->expr()->in('share_id', $query->createFunction('(' . $select . ')')));
		$query->execute();

		$deleteReShares = $this->connection->getQueryBuilder();
		$deleteReShares->delete('share')
			->where($deleteReShares->expr()->eq('file_source', $deleteReShares->createNamedParameter($mountPointId)));
		$deleteReShares->execute();
	}

	/**
	 * remove all shares for user $uid if the user was deleted
	 *
	 * @param string $uid
	 * @return bool
	 */
	abstract public function removeUserShares($uid);

	/**
	 * return a list of shares which are not yet accepted by the user
	 *
	 * @return array list of open server-to-server shares
	 */
	public function getOpenShares()
	{
		return $this->getShares(false);
	}

	/**
	 * return a list of shares which are accepted by the user
	 *
	 * @return array list of accepted server-to-server shares
	 */
	public function getAcceptedShares()
	{
		return $this->getShares(true);
	}

	/**
	 * return a list of shares for the user
	 *
	 * @param bool|null $accepted True for accepted only,
	 *                            false for not accepted,
	 *                            null for all shares of the user
	 * @return array list of open server-to-server shares
	 */
	private function getShares($accepted)
	{
		$user = $this->userManager->get($this->uid);
		$groups = $this->groupManager->getUserGroups($user);
		$userGroups = [];
		foreach ($groups as $group) {
			$userGroups[] = $group->getGID();
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('user', $qb->createNamedParameter($this->uid)),
					$qb->expr()->in(
						'user',
						$qb->createNamedParameter($userGroups, IQueryBuilder::PARAM_STR_ARRAY)
					)
				)
			)
			->orderBy('id', 'ASC');

		$result = $qb->execute();

		$shares = $result ? $this->fetchShares($result) : [];

		if (!is_null($accepted)) {
			$shares = array_filter($shares, function ($share) use ($accepted) {
				return (bool) $share['accepted'] === $accepted;
			});
		}
		return \array_values($shares);
	}

	abstract protected function fetchShares($shares);

	public function acceptRemoteGroupShares($groupId, $userId)
	{
		$getGroupSharesStmt = $this->connection->prepare("SELECT * FROM  `*PREFIX*{$this->tableName}` WHERE `user` = ?");

		$getUserSharesStmt = $this->connection->prepare("SELECT * FROM  `*PREFIX*{$this->tableName}` WHERE `user` = ?");

		$groupShares = $getGroupSharesStmt->execute([$groupId]) ? $getGroupSharesStmt->fetchAll() : [];
		$userShares = $getUserSharesStmt->execute([$userId]) ? $getUserSharesStmt->fetchAll() : [];

		$openShares = array_diff($userShares, $groupShares);

		$openShares = array_filter($groupShares, function ($groupShare) use ($userShares) {
			return !array_filter($userShares, function ($userShare) use ($groupShare) {
				return $userShare['parent'] == $groupShare['id'];
			});
		});

		foreach ($openShares as $share) {

			$shareFolder = \OCA\Files_Sharing\Helper::getShareFolder();
			$mountPoint = \OCP\Files::buildNotExistingFileName($shareFolder, $share['name']);
			$mountPoint = \OC\Files\Filesystem::normalizePath($mountPoint);
			$mountpoint_hash = \md5($mountPoint);

			$query = $this->connection->prepare("
                            INSERT INTO `*PREFIX*{$this->tableName}`
                            (`parent`, `remote`,`remote_id`, `share_token`, `password`, `name`, `owner`, `user`, `mountpoint`, `mountpoint_hash`, `accepted`)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
			$query->execute([
				$share['id'],
				$share['remote'],
				$share['remote_id'],
				$share['share_token'],
				$share['password'],
				$share['name'],
				$share['owner'],
				$userId,
				$mountPoint,
				$mountpoint_hash,
				1,
			]);
		}
	}
}
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
 * @author Michiel de Jong <michiel@pondersource.com>
 * @author Navid Shokri <navid@pondersource.com>
 * @author Yashar PM <yashar@pondersource.com>
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

use OCP\Notification\IManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Manager extends AbstractManager {
	public const STORAGE = '\OCA\OpenCloudMesh\Files_Sharing\External\Storage';

	/**
	 * @param \OCP\IDBConnection $connection
	 * @param \OC\Files\Mount\Manager $mountManager
	 * @param \OCP\Files\Storage\IStorageFactory $storageLoader
	 * @param IManager $notificationManager
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param \OCP\IUserManager $userManger
	 * @param \OCP\IGroupManager $groupManager
	 * @param string $uid
	 */
	public function __construct(
		\OCP\IDBConnection $connection,
		\OC\Files\Mount\Manager $mountManager,
		\OCP\Files\Storage\IStorageFactory $storageLoader,
		IManager $notificationManager,
		EventDispatcherInterface $eventDispatcher,
		\OCP\IUserManager $userManager,
		\OCP\IGroupManager $groupManager,
		$uid = null
	) {
		parent::__construct(
			self::STORAGE,
			'share_external_group',
			$connection,
			$mountManager,
			$storageLoader,
			$notificationManager,
			$eventDispatcher,
			$userManager,
			$groupManager,
			$uid);
	}

	protected function prepareData(array &$data) {
		$data['parent'] = -1;
		$data['lastscan'] = time();
	}
	
	/**
	 * write remote share to the database
	 *
	 * @param $remote
	 * @param $token
	 * @param $password
	 * @param $name
	 * @param $owner
	 * @param $user
	 * @param $mountPoint
	 * @param $hash
	 * @param $accepted
	 * @param $remoteId
	 * @param $parent
	 * @param $shareType
	 *
	 * @return void
	 * @throws \Doctrine\DBAL\Driver\Exception
	 */
	private function writeShareToDb($remote, $token, $password, $name, $owner, $user, $mountPoint, $hash, $accepted, $remoteId, $parent): void {
		$query = $this->connection->prepare("
				INSERT INTO `*PREFIX*{$this->tableName}`
					(`remote`, `share_token`, `password`, `name`, `owner`, `user`, `mountpoint`, `mountpoint_hash`, `accepted`, `remote_id`, `parent`, `lastscan`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			");
		$query->execute([$remote, $token, $password, $name, $owner, $user, $mountPoint, $hash, $accepted, $remoteId, $parent, time()]);
	}
	
	public function getShare($id) {
		$share = $this->fetchShare($id);
		$validShare = is_array($share) && isset($share['user']);

		if ($validShare) {
			$parentId = (int)$share['parent'];
			if ($parentId !== -1) {
				// we just retrieved a sub-share, switch to the parent entry for verification
				$groupShare = $this->fetchShare($parentId);
			} else {
				$groupShare = $share;
			}
			$user = $this->userManager->get($this->uid);
			$group = $this->groupManager->get($groupShare['user']);
			if (isset($group) && $group->inGroup($user)) {
				return $share;
			}
		}

		return false;
	}

	private function fetchUserShare($parentId, $uid) {
		$getShare = $this->connection->prepare("
			SELECT `id`, `remote`, `remote_id`, `share_token`, `name`, `owner`, `user`, `mountpoint`, `accepted`, `parent`, `password`, `mountpoint_hash`
			FROM  `*PREFIX*{$this->tableName}`
			WHERE `parent` = ? AND `user` = ?");
		if ($getShare->execute([$parentId, $uid])) {
			$share = $getShare->fetch();
			$getShare->closeCursor();
			if ($share !== false) {
				return $share;
			}
		}
		return null;
	}

	/**
	 * @return bool True if db could be accepted, false otherwise
	 */
	public function acceptShareInDb($share, $mountPoint, $hash) {
		$id = $share['id'];
		$parentId = (int)$share['parent'];
		if ($parentId !== -1) {
			// this is the sub-share
			$subshare = $share;
		} else {
			$subshare = $this->fetchUserShare($id, $this->uid);
		}

		if ($subshare !== null) {
			try {
				$acceptShare = $this->connection->prepare("
				UPDATE `*PREFIX*{$this->tableName}`
				SET `accepted` = ?,
					`mountpoint` = ?,
					`mountpoint_hash` = ?
				WHERE `id` = ? AND `user` = ?");
				$acceptShare->execute([1, $mountPoint, $hash, $subshare['id'], $this->uid]);
				$result = true;
			} catch (Exception $e) {
				error_log('Could not update share: '.$e->getMessage());
				$result = false;
			}
		} else {
			try {
				$this->writeShareToDb(
					$share['remote'],
					$share['share_token'],
					$share['password'],
					$share['name'],
					$share['owner'],
					$this->uid,
					$mountPoint, $hash, 1,
					$share['remote_id'],
					$id);
				$result = true;
			} catch (Exception $e) {
				error_log('Could not create share: '.$e->getMessage());
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * Updates accepted flag in the database
	 *
	 * @param int $id
	 */
	private function updateAccepted(int $shareId, bool $accepted) : void {
		$query = $this->connection->prepare("
			UPDATE `*PREFIX*{$this->tableName}`
			SET `accepted` = ?
			WHERE `id` = ?");
		$query->execute([$accepted ? 1 : 0, $shareId]);
		$query->closeCursor();
	}

	protected function executeDeclineShareStatement($share) {
		$id = $share['id'];
		$parentId = (int)$share['parent'];
		if ($parentId !== -1) {
			// this is the sub-share
			$subshare = $share;
		} else {
			$subshare = $this->fetchUserShare($id, $this->uid);
		}

		if ($subshare !== null) {
			try {
				$this->updateAccepted((int)$subshare['id'], false);
				$result = true;
			} catch (Exception $e) {
				error_log('Could not update share: '.$e->getMessage());
				$result = false;
			}
		} else {
			try {
				$this->writeShareToDb(
					$share['remote'],
					$share['share_token'],
					$share['password'],
					$share['name'],
					$share['owner'],
					$this->uid,
					$share['mountpoint'],
					$share['mountpoint_hash'],
					0,
					$share['remote_id'],
					$id);
				$result = true;
			} catch (Exception $e) {
				error_log('Could not create share: '.$e->getMessage());
				$result = false;
			}
		}

		return $result;
	}

	public function removeUserShares($uid) {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('user', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->neq('parent', $qb->expr()->literal(-1)));
		$qb->execute();
		return true;
	}

	public function userRemovedFromGroup($uid, $gid) {
		$getShares = $this->connection->prepare("
			SELECT `id` FROM `*PREFIX*{$this->tableName}` WHERE `parent` = ? AND `user` = ?");
		if ($getShares->execute([-1, $gid])) {
			$parentShares = $getShares->fetchAll();
			$getShares->closeCursor();
			if (!empty($parentShares)) {
				foreach ($parentShares as $parentShare) {
					$qb = $this->connection->getQueryBuilder();
					$qb->delete($this->tableName)
						->where($qb->expr()->eq('user', $qb->expr()->literal($uid)))
						->andWhere($qb->expr()->eq('parent', $qb->expr()->literal($parentShare['id'])));
					$qb->execute();
				}
			}
		}

		return true;
	}

	public function removeGroupShares($gid): bool {
		try {
			$getShare = $this->connection->prepare("
				SELECT `id`, `remote`, `share_token`, `remote_id`
				FROM  `*PREFIX*{$this->tableName}`
				WHERE `user` = ?");
			$result = $getShare->execute([$gid]);
			$shares = $getShare->fetchAll();
			$getShare->closeCursor();

			$deletedGroupShares = [];
			$qb = $this->connection->getQueryBuilder();
			// delete group share entry and matching sub-entries
			$qb->delete($this->tableName)
			   ->where(
			   	$qb->expr()->orX(
			   		$qb->expr()->eq('id', $qb->createParameter('share_id')),
			   		$qb->expr()->eq('parent', $qb->createParameter('share_parent_id'))
			   	)
			   );

			foreach ($shares as $share) {
				$qb->setParameter('share_id', $share['id']);
				$qb->setParameter('share_parent_id', $share['id']);
				$qb->execute();
			}
		} catch (\Doctrine\DBAL\Exception $ex) {
			error_log('Could not delete user shares: '.$ex->getMessage());
			return false;
		}

		return true;
	}

	protected function executeRemoveShareStatement($share, $mountHash) {
		$this->updateAccepted((int)$share['id'], false);
		return true;
	}

	protected function fetchShares($shares) {
		$shares = $shares->fetchAll();

		// remove parent group share entry if we have a specific user share entry for the user
		$toRemove = [];
		foreach ($shares as $share) {
			if ((int)$share['parent'] > 0) {
				$toRemove[] = $share['parent'];
			}
		}
		$shares = array_filter($shares, function ($share) use ($toRemove) {
			return !in_array($share['id'], $toRemove, true) && ((int)$share['parent'] === -1 || (int)$share['accepted'] === 1);
		});

		$shares = array_map(function ($item) {
			$item["share_type"] = "group";
			return $item;
		}, $shares);
		return $shares;
	}
}

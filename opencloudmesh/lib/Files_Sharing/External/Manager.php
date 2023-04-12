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

namespace OCA\OpenCloudMesh\Files_Sharing\External;

use OCA\Files_Sharing\External\AbstractManager;
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
	 * @param string $uid
	 */
	public function __construct(
		\OCP\IDBConnection $connection,
		\OC\Files\Mount\Manager $mountManager,
		\OCP\Files\Storage\IStorageFactory $storageLoader,
		IManager $notificationManager,
		EventDispatcherInterface $eventDispatcher,
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
			$uid);
	}

	protected function prepareData(array &$data) {
		$data['parent'] = null;
		$data['share_type'] = 7;
		$data['lastscan'] = time();
	}

	protected function insertedUnacceptedShare(array $data) {
		$groupRowId = $this->connection->lastInsertId("*PREFIX*{$this->tableName}");
		$users = \OC::$server->getGroupManager()->findUsersInGroup($data['user']);
		$data['parent'] = $groupRowId;
		$data['share_type'] = 6;
		foreach($users as $item){
			$data['user'] = $item->getUID();
			$query = $this->connection->prepare("
				INSERT INTO `*PREFIX*{$this->tableName}`
					(`remote`, `share_token`, `password`, `name`, `owner`, `user`,
					`mountpoint`, `mountpoint_hash`, `accepted`, `remote_id`, `parent`, `share_type`, `lastscan`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			");
			$query->execute([$data['remote'], $data['share_token'], $data['password'], $data['name'], $data['owner'], $data['user'], 
				$data['mountpoint'], $data['mountpoint_hash'], $data['accepted'], $data['remote_id'], $data['parent'], 
				$data['share_type'], $data['lastscan']]
			);
		}
	}

	protected function executeDeclineShareStatement($id) {
		$removeShare = $this->connection->prepare("
			Update `*PREFIX*{$this->tableName}` set `accepted` = 2 WHERE `id` = ? AND `user` = ?");
		$removeShare->execute([$id, $this->uid]);
	}

	protected function fetchShares($shares) {
		$groupShared = $shares->fetchAll();
		$sharedFiles = array_map(function ($item) {
			$item["share_type"] = "group";
			return $item;
		}, $groupShared);
		return $sharedFiles;
	}
}

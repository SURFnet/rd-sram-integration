<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCA\Files_Sharing\External;

use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IDBConnection;
use OCP\IUser;

/**
 * This class is extended by other applications like OpenCloudMesh.
 */
class AbstractMountProvider implements IMountProvider {
	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	/**
	 * @var callable
	 */
	private $managerProvider;

	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @var string
	 */
	private $tableName;

	/**
	 * @param \OCP\IDBConnection $connection
	 * @param callable $managerProvider due to setup order we need a callable that return the manager instead of the manager itself
	 * @param string $storage
	 * @param string $tableName
	 */
	public function __construct(
		IDBConnection $connection,
		callable $managerProvider,
		string $storage,
		string $tableName
		) {
		$this->connection = $connection;
		$this->managerProvider = $managerProvider;
		$this->storage = $storage;
		$this->tableName = $tableName;
	}

	public function getMount(IUser $user, $data, IStorageFactory $storageFactory) {
		$managerProvider = $this->managerProvider;
		$manager = $managerProvider();
		$data['manager'] = $manager;
		$mountPoint = '/' . $user->getUID() . '/files/' . \ltrim($data['mountpoint'], '/');
		$data['mountpoint'] = $mountPoint;
		$data['certificateManager'] = \OC::$server->getCertificateManager($user->getUID());
		return new Mount($this->storage, $mountPoint, $data, $manager, $storageFactory);
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$query = $this->connection->prepare("
				SELECT `remote`, `share_token`, `password`, `mountpoint`, `owner`
				FROM `*PREFIX*{$this->tableName}`
				WHERE `user` = ? AND `accepted` = ?
			");
		$query->execute([$user->getUID(), 1]);
		$mounts = [];
		while ($row = $query->fetch()) {
			$row['manager'] = $this;
			$row['token'] = $row['share_token'];
			/// FIXME: Use \OCA\FederatedFileSharing\Address in external Storage and Cache
			// Force missing proto to be https
			if (\strpos($row['remote'], '://') === false) {
				$row['remote'] = 'https://' .  $row['remote'];
			}

			$mounts[] = $this->getMount($user, $row, $loader);
		}
		return $mounts;
	}
}

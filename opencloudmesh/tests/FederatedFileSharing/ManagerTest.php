<?php
/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\OpenCloudMesh\Tests\FederatedFileSharing;

use Doctrine\DBAL\Driver\Statement;
use OC\Files\Cache\Cache;
use OC\Files\Mount\Manager as MountManager;
use OC\Files\Storage\Storage;
use OCA\OpenCloudMesh\Files_Sharing\External\Manager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorageFactory;
use OCP\IDBConnection;
use OCP\Notification\IManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ManagerTest extends TestCase {
	/** @var Manager */
	private $manager;

	/** @var IDBConnection | \PHPUnit\Framework\MockObject\MockObject */
	private $connection;

	/** @var MountManager | \PHPUnit\Framework\MockObject\MockObject */
	private $mountManager;

	/** @var IStorageFactory | \PHPUnit\Framework\MockObject\MockObject */
	private $storageFactory;

	/** @var IManager | \PHPUnit\Framework\MockObject\MockObject */
	private $notificationManager;

	/** @var EventDispatcherInterface | \PHPUnit\Framework\MockObject\MockObject */
	private $eventDispatcher;

	private $uid = 'john doe';

	protected function setUp():void {
		$this->connection = $this->createMock(IDBConnection::class);
		$this->mountManager = $this->createMock(MountManager::class);
		$this->storageFactory = $this->createMock(IStorageFactory::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->manager = new Manager(
			$this->connection,
			$this->mountManager,
			$this->storageFactory,
			$this->notificationManager,
			$this->eventDispatcher,
			\OC::$server->getUserManager(),
			\OC::$server->getGroupManager(),
			$this->uid
		);
	}

	public function testRemoveShareForNonExistingShareDispatchNoEvents() {
		$this->eventDispatcher->expects($this->never())->method('dispatch');

		$statement = $this->createMock(Statement::class);
		$statement->method('execute')->willReturnOnConsecutiveCalls(true, false);
		$statement->method('fetch')->willReturn(false);
		$this->connection->method('prepare')->willReturn($statement);

		$cache = $this->createMock(Cache::class);
		$cache->method('getId')->willReturn(99);

		$storage = $this->createMock(Storage::class);
		$storage->method('getCache')->willReturn($cache);

		$mountPoint = $this->createMock(IMountPoint::class);
		$mountPoint->method('getStorage')->willReturn($storage);

		$this->mountManager->method('find')->willReturn($mountPoint);

		$this->manager->removeShare('/neverhood');
	}
}

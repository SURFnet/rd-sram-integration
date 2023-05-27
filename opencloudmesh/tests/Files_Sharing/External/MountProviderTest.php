<?php
/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2022, SURF
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

namespace OCA\OpenCloudMesh\Tests\Files_Sharing\External;

use Doctrine\DBAL\Driver\Statement;
use OCA\OpenCloudMesh\Files_Sharing\External\Mount;
use OCA\OpenCloudMesh\Files_Sharing\External\MountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IDBConnection;
use OCP\IUser;
use Test\TestCase;

/**
 * Class ManagerTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests\External
 */
class MountProviderTest extends TestCase {
	/** @var IDBConnection */
	private $dbConnection;

	/** @var MountProvider */
	private $mountProvider;

	public function setUp(): void {
		parent::setUp();
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->mountProvider = new MountProvider($this->dbConnection, function () {
		});
	}

	public function testCreateMountWithNoProto() {
		$user = $this->createMock(IUser::class);
		$storageFactory = $this->createMock(IStorageFactory::class);
		$statement = $this->createMock(Statement::class);
		$statement->method('fetch')->willReturnOnConsecutiveCalls(
			[
				'remote' => 'domain.tld',
				'share_token' => 'secret',
				'mountpoint' => '/mp'
			],
			false
		);
		$this->dbConnection->method('prepare')->willReturn($statement);

		$mounts = $this->mountProvider->getMountsForUser($user, $storageFactory);
		$mount = $mounts[0];
		$this->assertInstanceOf(Mount::class, $mount);
	}
}

<?php
/**
 * @author Yashar PM <yashar@pondersource.com>
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

namespace OCA\FederatedFileSharing\Tests\appinfo\Migrations;

// FIXME: autoloader fails to load migration
require_once \dirname(\dirname(\dirname(__DIR__))) . "/appinfo/Migrations/Version20221214081843.php";

use Doctrine\DBAL\Schema\Table;
use OCA\OpenCloudMesh\Migrations\Version20221214081843;
use Test\TestCase;
use Doctrine\DBAL\Schema\Schema;

class Version20221214081843Test extends TestCase {
	public function testExecute() {
		$tablePrefix = 'oc_';
		$migration = new Version20221214081843();
		$table = $this->createMock(Table::class);
		$schema = $this->createMock(Schema::class);
		$schema->method('hasTable')->with('oc_share_external_group')
			->willReturn(false);
		$schema->method('createTable')->with('oc_share_external_group')
			->willReturn($table);

		$this->assertNull($migration->changeSchema($schema, ['tablePrefix' => $tablePrefix]));
	}
}

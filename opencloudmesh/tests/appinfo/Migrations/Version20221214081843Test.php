<?php

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

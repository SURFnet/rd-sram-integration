<?php
/**
 * @author Navid Shokri <navid@pondersource.com>
 * @author Michiel de Jong <michiel@pondersource.com>
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

namespace OCA\OpenCloudMesh\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

class Version20221214081843 implements ISchemaMigration {

	/** @var  string */
    private $prefix;

	/** @var  string */
	private $tableName = "share_external_group";

	public function changeSchema(Schema $schema, array $options) {
		$this->prefix = $options['tablePrefix'];

        if (!$schema->hasTable("{$this->prefix}{$this->tableName}")) {
            $table = $schema->createTable("{$this->prefix}{$this->tableName}");
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('parent', 'integer', [
                'unsigned' => false,
                'notnull' => false,
                'default' => -1
            ]);

            $table->addColumn('remote', 'string', [
                'length' => 512,
                'notnull' => true,
            ]);
            
            $table->addColumn('remote_id', 'string', [
                'length' => 255,
                'notnull' => true,
                'default' => '-1'
            ]);

            $table->addColumn('share_token', 'string', [
                'length' => 255,
                'notnull' => true,
                'default' => '-1'
            ]);

            $table->addColumn('password', 'string', [
                'length' => 64,
                'notnull' => false,
                'default' => null
            ]);

            $table->addColumn('name', 'string', [
                'length' => 255,
                'notnull' => true                
            ]);
            
            $table->addColumn('owner', 'string', [
                'length' => 255,
                'notnull' => true                
            ]);

            $table->addColumn('user', 'string', [
                'length' => 255,
                'notnull' => true                
            ]);


            $table->addColumn('mountpoint', 'string', [
                'length' => 4000,
                'notnull' => true                
            ]);

            $table->addColumn('mountpoint_hash', 'string', [
                'length' => 32,
                'notnull' => true                
            ]);

            $table->addColumn('accepted', 'smallint', [
                
                'unsigned' => true,
                'notnull' => true,
                'default' => 0,
            ]);

            $table->addColumn('lastscan', 'bigint', [
                'length' => 11,
                'unsigned' => true,
                'notnull' => false,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user', 'mountpoint_hash'], 'sh_external_group_mp');
        }
    }
}

<?php

namespace OCA\FederatedGroups\Migrations;


use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

class Version20230823171700 implements ISchemaMigration
{

    public function changeSchema(Schema $schema, array $options)
    {
        $prefix = $options['tablePrefix'];
        if (!$schema->hasTable("{$prefix}fg_groups")) {
            $fg_groups_table = $schema->createTable("{$prefix}fg_groups");

            $fg_groups_table->addColumn('gid', 'string', [
                'length' => 512,
                'notnull' => true,
                'comment' => ''
            ]);
        }
        $fg_groups_table->setPrimaryKey(['gid']);


        if (!$schema->hasTable("{$prefix}fg_group_user")) {
            $fg_group_user_table = $schema->createTable("{$prefix}fg_group_user");

            $fg_group_user_table->addColumn('gid', 'string', [
                'length' => 512,
                'notnull' => true,
                'comment' => ''
            ]);

            $fg_group_user_table->addColumn('uid', 'string', [
                'length' => 512,
                'notnull' => true,
                'comment' => ''
            ]);

			$fg_group_user_table->addUniqueIndex(
				['gid', 'uid'],
				`{$prefix}uc_fg_group_user`
			);
        }
    }
}

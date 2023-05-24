<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\OpenCloudMesh\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISqlMigration;

class Version20230523140843 implements ISqlMigration {

    public function sql(IDBConnection $connection) {
		$sql1 = 'CREATE TABLE `fg_groups` (
            `gid` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`gid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
        $sql2 = 'CREATE TABLE `fg_group_user` (
            `gid` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `uid` varchar(256) COLLATE utf8_unicode_ci NOT NULL
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
		return [$sql1, $sql2];
	}
}

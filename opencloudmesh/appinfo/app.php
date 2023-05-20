<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later


$app = new \OCA\OpenCloudMesh\AppInfo\Application();
$app->registerMountProviders();
$app->registerEvents();

\OCP\Util::connectHook('OC_User', 'post_deleteUser', '\OCA\OpenCloudMesh\Files_Sharing\Hooks', 'deleteUser');
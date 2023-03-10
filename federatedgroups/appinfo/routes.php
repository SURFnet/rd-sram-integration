<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

return [
	'routes' => [
		['name' => 'scim#addUserToGroupTest', 'url' => '/scim/Groups', 'verb' => 'GET'],
		['name' => 'scim#addUserToGroup', 'url' => '/scim/Groups/{groupId}', 'verb' => 'PATCH'],
	],
	'resources' => []
];

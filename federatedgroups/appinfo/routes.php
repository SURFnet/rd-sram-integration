<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

return [
	'routes' => [
		// Groups
		['name' => 'scim#getGroups', 'url' => '/scim/Groups', 'verb' => 'GET'],
		['name' => 'scim#createGroup', 'url' => '/scim/Groups', 'verb' => 'POST'],
		
		['name' => 'scim#addUserToGroup', 'url' => '/scim/Groups/{groupId}', 'verb' => 'PATCH'],

		// Users
		['name' => 'scim#getUsers', 'url' => '/scim/Users', 'verb' => 'GET'],
		['name' => 'scim#createUser', 'url' => '/scim/Users', 'verb' => 'POST'],
	],
	'resources' => []
];

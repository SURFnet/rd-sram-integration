<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

$CONTROLLER = "scim";

return [
	'routes' => [
		// Groups
		['name' => "$CONTROLLER#getGroups", 'url' => '/scim/Groups', 'verb' => 'GET'],
		['name' => "$CONTROLLER#createGroup", 'url' => '/scim/Groups', 'verb' => 'POST'],
		['name' => "$CONTROLLER#updateGroup", 'url' => '/scim/Groups/{groupId}', 'verb' => 'PUT'],
		['name' => "$CONTROLLER#getGroup", 'url' => '/scim/Groups/{groupId}', 'verb' => 'GET'],
		['name' => "$CONTROLLER#deleteGroup", 'url' => '/scim/Groups/{groupId}', 'verb' => 'DELETE'],
		// Users
		['name' => "$CONTROLLER#getUsers", 'url' => '/scim/Users', 'verb' => 'GET'],
		['name' => "$CONTROLLER#createUser", 'url' => '/scim/Users', 'verb' => 'POST'],
		['name' => "$CONTROLLER#updateUser", 'url' => '/scim/Users/{userId}', 'verb' => 'PUT'],
	],
	'resources' => []
];

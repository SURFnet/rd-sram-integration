<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

 $ROLE = "RD-API";

if ($ROLE == "RD-API") {
	return [
		'routes' => [
			// Groups
			['name' => 'rdapi#getGroups', 'url' => '/scim/Groups', 'verb' => 'GET'],
			['name' => 'rdapi#createGroup', 'url' => '/scim/Groups', 'verb' => 'POST'],
			['name' => 'rdapi#updateGroup', 'url' => '/scim/Groups', 'verb' => 'PUT'],
			// Users
			['name' => 'rdapi#getUsers', 'url' => '/scim/Users', 'verb' => 'GET'],
			['name' => 'rdapi#createUser', 'url' => '/scim/Users', 'verb' => 'POST'],
			['name' => 'rdapi#updateUser', 'url' => '/scim/Users', 'verb' => 'PUT'],
		],
		'resources' => []
	];
} else {
	return [
		'routes' => [
			['name' => 'scim#createGroup', 'url' => '/scim/Groups', 'verb' => 'POST'],
		],
		'resources' => []
	];
}
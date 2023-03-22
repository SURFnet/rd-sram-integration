<?php

/**
 * @author Michiel de Jong <michiel@pondersource.com>
 *
 * @copyright Copyright (c) 2023, SURF
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\FederatedGroups\Controller;

use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;
use OCP\DB\QueryBuilder\IQueryBuilder;


// use OCP\DB\QueryBuilder\IQueryBuilder;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCA\FederatedFileSharing\Notifications;

const RESPONSE_TO_USER_GET = Http::STATUS_OK;
const RESPONSE_TO_USER_CREATE = Http::STATUS_OK;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;

const RESPONSE_TO_GROUP_GET = Http::STATUS_OK;
const RESPONSE_TO_GROUP_CREATE = Http::STATUS_OK;
const RESPONSE_TO_GROUP_UPDATE = Http::STATUS_OK;

/**
 * Class ScimController
 *
 * @package OCA\FederatedGroups\Controller
 */
class ScimController extends Controller {
	/**
	 * @var IDBConnection
	 */
	private $dbConn;

	/**
	 * @var MixedGroupShareProvider
	 */
	protected $mixedGroupShareProvider;

	/**
	 * OcmController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param MixedGroupShareProvider $mixedGroupShareProvider
	 * @param IDBConnection $dbConn
	 */
	public function __construct(
		$appName,
		IRequest $request,
		Notifications $notifications,
		IDBConnection $dbConn,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IRootFolder $rootFolder
	) {
		parent::__construct($appName, $request);
		error_log("Federated Groups ScimController constructed");
		$federatedGroupsApp = new \OCA\FederatedGroups\AppInfo\Application();
		$this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
		$this->dbConn = $dbConn;
	}

	private function getRegularGroupId($groupId) {
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$cursor = $queryBuilder->select('gid')->from('groups')
			->where($queryBuilder->expr()->eq('gid', $queryBuilder->createNamedParameter($groupId, IQueryBuilder::PARAM_STR)))->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();
		error_log("cursor closed");
		if ($row === false) {
			return false;
		} else {
			error_log(var_export($row, true));
			return $row['gid'];
		}
	}

	private function addToRegularGroup($userId, $regularGroupId) {
		error_log("addToRegularGroup $userId $regularGroupId calling notifyNewRegularGroupMember");
		$this->mixedGroupShareProvider->notifyNewRegularGroupMember($userId, $regularGroupId);
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$result = $queryBuilder->insert('group_user')
			->values([
				'uid' => $queryBuilder->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'gid' => $queryBuilder->createNamedParameter($regularGroupId, IQueryBuilder::PARAM_STR),
			])->execute();
		return true;
	}

	private function  getCustomGroupId($groupId) {
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$cursor = $queryBuilder->select('group_id')->from('custom_group')
			->where($queryBuilder->expr()->eq('uri', $queryBuilder->createNamedParameter($groupId, IQueryBuilder::PARAM_STR)))->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();
		error_log("cursor closed");
		if ($row === false) {
			return false;
		} else {
			return $row['group_id'];
		}
	}

	private function addToCustomGroup($userId, $customGroupId) {
		error_log("addToCustomGroup $userId $customGroupId calling notifyNewCustomGroupMember");
		$this->mixedGroupShareProvider->notifyNewCustomGroupMember($userId, $customGroupId);
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$result = $queryBuilder->insert('custom_group_member')
			->values([
				'user_id' => $queryBuilder->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'group_id' => $queryBuilder->createNamedParameter($customGroupId, IQueryBuilder::PARAM_INT),
				'role' => $queryBuilder->createNamedParameter(1, IQueryBuilder::PARAM_INT),
			])->execute();
		// error_log(var_export($result, true));
		return true;
	}

	private function addMember($userId, $groupId) {
		$regularGroupId = $this->getRegularGroupId($groupId);
		if ($regularGroupId === false) {
			$customGroupId = $this->getCustomGroupId($groupId);
			if ($customGroupId === false) {
				return false;
			}
			error_log("Adding $userId to custom group $customGroupId ('$groupId')");
			return $this->addToCustomGroup($userId, $customGroupId);
		} else {
			error_log("Adding $userId to regular group $regularGroupId ('$groupId')");
			return $this->addToRegularGroup($userId, $regularGroupId);
		}
	}

	private function executeOperation($op, $groupId) {
		if ($op['op'] == 'add' && $op['path'] == 'members') {
			$members = $op['value']['members'];
			for ($i = 0; $i < count($members); $i++) {
				$this->addMember($members[$i]['value'], $groupId);
			}
			return 1;
		}
		return 0;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /users/ requests
	 * @return array
	 */
	public function getUsers() {
		error_log("scim get users ");
		$filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		$qs = explode(" ", $filter);
		list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;

		// TODO: get groups filtered by externalId eq to `externalId` 
		// return new JSONResponse([], Http::STATUS_OK);
		// return new JSONResponse(["schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"]], Http::STATUS_OK);
		error_log("Returning " . RESPONSE_TO_USER_GET);

		return new JSONResponse(
			[
				"schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
				// "id" => "e9e30dba-f08f-4109-8486-d5c6a331660a",
				// "displayName" => "Sales Reps",
				// "members" => [],
				// "meta" => [
				// 	"resourceType" => "Users",
				// 	"created" => "2010-01-23T04:56:22Z",
				// 	"lastModified" => "2011-05-13T04:42:34Z",
				// 	"version" => "W\/\"3694e05e9dff592\"",
				// 	"location" => "https://example.com/v2/Groups/e9e30dba-f08f-4109-8486-d5c6a331660a"
				// ]
			],
			RESPONSE_TO_USER_GET
		);
	}


	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /users/ requests
	 * @return array
	 */
	public function createUser() {
		error_log("scim create user");
		$params = $this->request->getParams();

		$bodyJson = json_encode($params);

		error_log("=========================bodyJson=============================");
		error_log($bodyJson);
		error_log("=========================bodyJson=============================");

		// $filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		// $qs = explode(" ", $filter);
		// list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;

		// TODO: get groups filtered by externalId eq to `externalId` 
		// return new JSONResponse([], Http::STATUS_OK);
		// return new JSONResponse(["schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"]], Http::STATUS_OK);
		error_log("Returning " . RESPONSE_TO_USER_CREATE);
		return new JSONResponse(
			[
				"schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
				// "id" => "e9e30dba-f08f-4109-8486-d5c6a331660a",
				// "displayName" => "Sales Reps",
				// "members" => [],
				// "meta" => [
				// 	"resourceType" => "Users",
				// 	"created" => "2010-01-23T04:56:22Z",
				// 	"lastModified" => "2011-05-13T04:42:34Z",
				// 	"version" => "W\/\"3694e05e9dff592\"",
				// 	"location" => "https://example.com/v2/Groups/e9e30dba-f08f-4109-8486-d5c6a331660a"
				// ]
			],
			RESPONSE_TO_USER_CREATE
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /users/ requests
	 * @return array
	 */
	public function updateUser() {
		error_log("scim update user");
		$params = $this->request->getParams();

		$bodyJson = json_encode($params);

		error_log("=========================bodyJson=============================");
		error_log($bodyJson);
		error_log("=========================bodyJson=============================");

		// $filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		// $qs = explode(" ", $filter);
		// list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;

		// TODO: get groups filtered by externalId eq to `externalId` 
		// return new JSONResponse([], Http::STATUS_OK);
		// return new JSONResponse(["schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"]], Http::STATUS_OK);
		error_log("Returning " . RESPONSE_TO_USER_UPDATE);
		return new JSONResponse(
			[
				"schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
				// "id" => "e9e30dba-f08f-4109-8486-d5c6a331660a",
				// "displayName" => "Sales Reps",
				// "members" => [],
				// "meta" => [
				// 	"resourceType" => "Users",
				// 	"created" => "2010-01-23T04:56:22Z",
				// 	"lastModified" => "2011-05-13T04:42:34Z",
				// 	"version" => "W\/\"3694e05e9dff592\"",
				// 	"location" => "https://example.com/v2/Groups/e9e30dba-f08f-4109-8486-d5c6a331660a"
				// ]
			],
			RESPONSE_TO_USER_UPDATE
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /groups/ requests
	 * @return array
	 */
	public function getGroups() {
		error_log("scim get groups");
		$filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		$qs = explode(" ", $filter);
		list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;

		// TODO: get groups filtered by externalId eq to `externalId` 
		// return new JSONResponse([], Http::STATUS_OK);
		// return new JSONResponse(["schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"]], Http::STATUS_OK);
		error_log("Returning " . RESPONSE_TO_GROUP_GET);
		return new JSONResponse(
			[
				"schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"],
				// "id" => "e9e30dba-f08f-4109-8486-d5c6a331660a",
				// "displayName" => "Sales Reps",
				// "members" => [],
				// "meta" => [
				// 	"resourceType" => "Group",
				// 	"created" => "2010-01-23T04:56:22Z",
				// 	"lastModified" => "2011-05-13T04:42:34Z",
				// 	"version" => "W\/\"3694e05e9dff592\"",
				// 	"location" => "https://example.com/v2/Groups/e9e30dba-f08f-4109-8486-d5c6a331660a"
				// ]
			],
			RESPONSE_TO_GROUP_GET
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /groups/ requests
	 * @return array
	 */
	public function createGroup() {
		error_log("scim create group");
		$params = $this->request->getParams();

		$bodyJson = json_encode($params);

		error_log("=========================bodyJson=============================");
		error_log($bodyJson);
		error_log("=========================bodyJson=============================");
		// $filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		// $qs = explode(" ", $filter);
		// list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;
		
		// TODO: get groups filtered by externalId eq to `externalId` 
		// return new JSONResponse([], Http::STATUS_OK);
		// return new JSONResponse(["schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"]], Http::STATUS_OK);
		error_log("Returning " . RESPONSE_TO_GROUP_CREATE);
		return new JSONResponse(
			[
				"schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
				// "id" => "e9e30dba-f08f-4109-8486-d5c6a331660a",
				// "displayName" => "Sales Reps",
				// "members" => [],
				// "meta" => [
					// 	"resourceType" => "Users",
					// 	"created" => "2010-01-23T04:56:22Z",
					// 	"lastModified" => "2011-05-13T04:42:34Z",
					// 	"version" => "W\/\"3694e05e9dff592\"",
					// 	"location" => "https://example.com/v2/Groups/e9e30dba-f08f-4109-8486-d5c6a331660a"
					// ]
				],
				RESPONSE_TO_GROUP_CREATE
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /groups/ requests
	 * @return array
	 */
	public function updateGroup() {
		error_log("scim update group");
		$params = $this->request->getParams();

		$bodyJson = json_encode($params);

		error_log("=========================bodyJson=============================");
		error_log($bodyJson);
		error_log("=========================bodyJson=============================");
		// $filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		// $qs = explode(" ", $filter);
		// list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;
		
		// TODO: get groups filtered by externalId eq to `externalId` 
		// return new JSONResponse([], Http::STATUS_OK);
		// return new JSONResponse(["schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"]], Http::STATUS_OK);
		error_log("Returning " . RESPONSE_TO_GROUP_UPDATE);
		return new JSONResponse(
			[
				"schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
				// "id" => "e9e30dba-f08f-4109-8486-d5c6a331660a",
				// "displayName" => "Sales Reps",
				// "members" => [],
				// "meta" => [
					// 	"resourceType" => "Users",
					// 	"created" => "2010-01-23T04:56:22Z",
					// 	"lastModified" => "2011-05-13T04:42:34Z",
					// 	"version" => "W\/\"3694e05e9dff592\"",
					// 	"location" => "https://example.com/v2/Groups/e9e30dba-f08f-4109-8486-d5c6a331660a"
					// ]
				],
				RESPONSE_TO_GROUP_UPDATE
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /ocm-provider/ requests
	 * @return array
	 */
	public function addUserToGroup($groupId) {
		// The app framework will parse JSON bodies of POST requests
		// if the Content-Type is set to application/json, and you can
		// we can then just write `addUserToGroup($Operations)` and it would work.
		// However, for PATCH requests the same thing does not work.
		// See https://github.com/pondersource/peppol-php/issues/133#issuecomment-1221297463
		// for a very similar discussion involving PUT requests to a Nextcloud server.
		error_log("scimming $groupId addUserToGroup!");
		try {
			$body = json_decode(file_get_contents('php://input'), true);
			$ops = $body['Operations'];
		} catch (Exception $e) {
			return new JSONResponse(
				["Could not parse operations array"],
				Http::STATUS_BAD_REQUEST
			);
		}

		error_log(var_export($body, true));
		$success = 0;
		for ($i = 0; $i < count($ops); $i++) {
			$success += $this->executeOperation($body['Operations'][$i], $groupId);
		}
		if ($success == count($body['Operations'])) {
			return new JSONResponse(
				[],
				Http::STATUS_OK
			);
		} else {
			return new JSONResponse(
				["Could only execute $success out of " . count($body['Operations']) . " operations"],
				Http::STATUS_BAD_REQUEST
			);
		}
	}
}

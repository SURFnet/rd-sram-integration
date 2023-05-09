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
use OCP\IGroupManager;


// use OCP\DB\QueryBuilder\IQueryBuilder;
use OCA\FederatedGroups\MixedGroupShareProvider;

const RESPONSE_TO_USER_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;

const RESPONSE_TO_GROUP_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_GROUP_UPDATE = Http::STATUS_OK;

const IGNORE_DOMAIN = "sram.surf.nl";

function getOurDomain() {
	return getenv("SITE") . ".pondersource.net";
}

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
	 * @var IGroupManager $groupManager
	 */
	private $groupManager;

	/**
	 * @var MixedGroupShareProvider
	 */
	protected $mixedGroupShareProvider;

	/**
	 * OcmController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IGroupManager $groupManager

	 */
	public function __construct($appName, IRequest $request, IGroupManager $groupManager) {
		parent::__construct($appName, $request);
		$federatedGroupsApp = new \OCA\FederatedGroups\AppInfo\Application();
		$this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
		$this->groupManager = $groupManager;
	}

	private function checkNeedToSend($newUser, $existingUsers) {
		error_log("checkNeedToSend($newUser, " . var_export($existingUsers, true) . ")");
		$newUserParts = explode("#", $newUser);
		if (count($newUserParts) == 1) return false; // local user

		if (count($newUserParts) == 2) { // remote user
			if (str_contains($newUserParts[1], '#') && !str_contains($newUserParts[1], getOurDomain())) {
				return false;
			}
			$newDomain = $newUserParts[1];
			foreach ($existingUsers as $existingUser) {
				error_log("Considering $existingUser");
				$existingUserParts = explode("#", $existingUser);
				if (count($existingUserParts) == 2) {
					error_log("Comparing $newDomain to " . var_export($existingUserParts, true));
					if ($existingUserParts[1] == $newDomain) {
						error_log("Already have a user there!");
						return false;
					}
				}
			}
			error_log("This is the first user in this group from $newDomain");
			return $newDomain;
		}
		error_log("WARNING: could not parse $newUser");
		return false;
	}

	private function handleUpdateGroup(string $groupId, $obj) {
		// $group = $this->validateGroupId($groupId);
		$group = $this->groupManager->get($groupId);

		error_log("Got group");
		$sharedWithGroup = $this->mixedGroupShareProvider->getSharesToRegularGroup($groupId);
		$backend = $group->getBackend();
		error_log("Got backend");
		$currentMembers = $backend->usersInGroup($groupId);
		error_log("Got current group members");
		error_log(var_export($currentMembers, true));
		$newMembers = [];
		foreach ($obj["members"] as $member) {
			$userIdParts = explode("@", $member["value"]); // "test_u@pondersource.net"  => ["test_u", "pondersource.net"] 
			error_log("A: " . var_export($userIdParts, true));
			if (count($userIdParts) == 3) {
				$userIdParts = [$userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
			}
			error_log("B: " . var_export($userIdParts, true));
			if (count($userIdParts) != 2) {
				throw new Exception("cannot parse OCM user " . $member["value"]);
			}
			error_log("C: " . var_export($userIdParts, true));
			$newMember = $userIdParts[0];
			error_log("D: " . var_export($newMember, true));
			if ($userIdParts[1] === getOurDomain()) {
				error_log("User is local to " . getOurDomain());
			} else {
				error_log("User is foreign to " . getOurDomain());
				$newMember .= "#" . $userIdParts[1];
			}
			if ($userIdParts[1] === IGNORE_DOMAIN) {
				continue;
			}
			error_log("E: " . var_export($newMember, true));
			$newMembers[] = $newMember;
		}

		error_log("Got new group members");
		error_log(var_export($newMembers, true));

		for ($i = 0; $i < count($currentMembers); $i++) {
			if (!in_array($currentMembers[$i], $newMembers)) { // meaning current member is not in new update, so remove it
				error_log("Removing from $groupId: " . $currentMembers[$i]);
				$backend->removeFromGroup($currentMembers[$i], $groupId);
			}
		}

		for ($i = 0; $i < count($newMembers); $i++) {
			if (!in_array($newMembers[$i], $currentMembers)) { // meaning new member is not in current update, so let add it to group and 

				// if (str_contains($newMembers[$i], '#') and !str_contains($newMembers[$i], 'oc1.docker')) { // getOurDomain() meaning User is foreign to our domain
				// 	[$userName, $remote] = explode('#', $newMembers[$i]);
				// 	if (!empty($sharedWithGroup)) {
				// 		foreach ($sharedWithGroup as $share) {
				// 			$this->mixedGroupShareProvider->sendOcmInvite($share, $remote);
				// 		}
				// 	}
				// }

				$newDomain = $this->checkNeedToSend($newMembers[$i], $currentMembers);

				if ($newDomain !== false) {
					error_log("New domain $newDomain in group $groupId");
					try {
						$this->mixedGroupShareProvider->newDomainInGroup($newDomain, $groupId);
					} catch (\Throwable $th) {
						throw $th;
					}
				}

				error_log("Adding to $groupId: " . $newMembers[$i]);
				$backend->addToGroup($newMembers[$i], $groupId);
			}
		}
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function createGroup() {
		error_log("scim create group");
		$body = json_decode(file_get_contents("php://input"), true);
		$groupId = $body["id"];

		$this->groupManager->createGroup($groupId);

		error_log("=========================bodyJson=============================");
		error_log(var_export($body, true));
		error_log("=========================bodyJson=============================");
		// expect group to already exist
		// we are probably receiving this create due to 
		// https://github.com/SURFnet/rd-sram-integration/commit/38c6289fd85a92b7fce5d4fbc9ea3170c5eed5d5
		$this->handleUpdateGroup($groupId, $body);
		return new JSONResponse(
			$body,
			RESPONSE_TO_GROUP_CREATE
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function updateGroup($groupId) {
		error_log("scim update group $groupId");
		// $this->validateGroupId($groupId);
		$body = json_decode(file_get_contents("php://input"), true);

		error_log("=========================bodyJson=============================");
		error_log(json_encode($body));
		error_log("=========================bodyJson=============================");

		try {
			$this->handleUpdateGroup($groupId, $body);
		} catch (\Throwable $th) {
			return new JSONResponse([
				'status' => 'error',
				'data' => [
					'message' => "Falure in sending ocm invites"
				]
			], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(
			$body,
			RESPONSE_TO_GROUP_CREATE
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function deleteGroup($groupId) {
		error_log("scim delete group");
		$group = $this->groupManager->get(\urldecode($groupId));
		if ($group) {
			$deleted = $group->delete();
			if ($deleted) {
				return new JSONResponse(
					[
						'status' => 'success',
						'data' => [
							'message' => "Succesfully deleted group: {$groupId}"
						]
					],
					Http::STATUS_OK
				);
			}
		} else {
			return new JSONResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => "Could not find Group with the given identifier: {$groupId}"
					],
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function getGroups() {
		error_log("scim get groups");
		// work around #129

		$_groups = [];
		$assignableGroups = [];
		$removableGroups = [];

		foreach ($this->groupManager->getBackends() as $backend) {
			$groups = $backend->getGroups();
			\array_push($_groups, ...$groups);
			if ($backend->implementsActions($backend::ADD_TO_GROUP)) {
				\array_push($assignableGroups, ...$groups);
			}
			if ($backend->implementsActions($backend::REMOVE_FROM_GROUP)) {
				\array_push($removableGroups, ...$groups);
			}
		}

		return new JSONResponse([
			"totalResults" => 0,
			"Resources" => [
				'_groups' => $_groups,
				'assignableGroups' => $assignableGroups,
				'removableGroups' => $removableGroups,
			],
		], Http::STATUS_OK);
	}
	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function getGroup($groupId) {
		error_log("scim get group");
		// work around #129
		$group = $this->groupManager->get(\urldecode($groupId));
		if ($group) {
			$id = $group->getGID();
			$displayName = $group->getDisplayName();
			$members = $group->getUsers();

			$groupBackend = $group->getBackend();
			$usersInGroup = $groupBackend->usersInGroup($groupId);

			$members = array_map(function ($item) {
				return [
					"value" => $item,
					"ref" => $item,
					"displayName" => $item,
				];
			}, $usersInGroup);

			return new JSONResponse([
				"totalResults" => 0,
				"Resources" => [
					"id" => $id,
					"displayName" => $displayName,
					'usersInGroup' => $usersInGroup,
					'members' => $members,
					"schemas" => [
						// "urn:ietf:params:scim:schemas:core:2.0:Group",
						// "urn:ietf:params:scim:schemas:cyberark:1.0:Group"
					],
					"meta" => [
						"resourceType" => "Group",
						// "created" => "2022-04-12T09:21:40.2319276Z",
						// "lastModified" => "2022-04-12T09:21:40.2319276Z",
						// "location" => "https://aax5785.my.idaptive.qa/Scim/v2/Group/8"

					],
					"urn:ietf:params:scim:schemas:cyberark:1.0:Group" => [
						// "directoryType" => "Vault"
					]
				],
			], Http::STATUS_OK);
		} else {
			return new JSONResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => "Could not find Group with the given identifier: {$groupId}"
					],
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}
}

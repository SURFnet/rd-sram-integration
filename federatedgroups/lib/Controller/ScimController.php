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
use OCA\FederatedFileSharing\Notifications;

const RESPONSE_TO_USER_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;

const RESPONSE_TO_GROUP_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_GROUP_UPDATE = Http::STATUS_OK;

const OUR_DOMAIN = "almere.pondersource.net";
const IGNORE_DOMAIN = "sram.surf.nl";

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
	public function __construct(
		$appName,
		IRequest $request,
		IGroupManager $groupManager
	) {
		parent::__construct($appName, $request);
		// error_log("Federated Groups ScimController constructed");
		$federatedGroupsApp = new \OCA\FederatedGroups\AppInfo\Application();
		$this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
		$this->groupManager = $groupManager;
	}

	private function checkNeedToSend($newUser, $existingUsers) {
		error_log("checkNeedToSend($newUser, $existingUsers)");
		$newUserParts = explode("#", $newUser);
		if (count($newUserParts) == 1) {
			error_log("This user is local");
			return false;
		}
		if (count($newUserParts) == 2) {
			$newDomain = $newUserParts[1];
			foreach($existingUsers as $existingUser) {
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

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function createGroup() {
		error_log("scim create group");
		$obj = json_decode(file_get_contents("php://input"), true);

		error_log("=========================bodyJson=============================");
		error_log(var_export($obj, true));
		error_log("=========================bodyJson=============================");
		$groupId = $obj["id"];
		$group = $this->groupManager->get($groupId);
		error_log("Got group");
		$backend = $group->getBackend();
		error_log("Got backend");
		$currentMembers = $backend->usersInGroup($groupId);
		error_log("Got current group members");
		error_log(var_export($currentMembers, true));
    $newMembers = [];
		foreach ($obj["members"] as $member) {
			$userIdParts = explode("@", $member["value"]);
			error_log("A: " . var_export($userIdParts, true));
			if (count($userIdParts) == 3) {
        $userIdParts = [ $userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
			}
			error_log("B: " . var_export($userIdParts, true));
			if (count($userIdParts) != 2) {
				throw new Exception("cannot parse OCM user " . $member["value"]);
			}
			error_log("C: " . var_export($userIdParts, true));
			$newMember = $userIdParts[0];
			error_log("D: " . var_export($newMember, true));
			if ($userIdParts[1] !== OUR_DOMAIN) {
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
			if (! in_array($currentMembers[$i], $newMembers)) {
				error_log("Removing from $groupId: " . $currentMembers[$i]);
				$backend->removeFromGroup($currentMembers[$i], $groupId);
			}
		}
		for ($i = 0; $i < count($newMembers); $i++) {
			if (! in_array($newMembers[$i], $currentMembers)) {
				$newDomain = $this->checkNeedToSend($newMembers[$i], $currentMembers);
				if ($newDomain !== false) {
					error_log("New domain $newDomain in group $groupId");
					$this->mixedGroupShareProvider->newDomainInGroup($newDomain, $groupId);					
				}
				error_log("Adding to $groupId: " . $newMembers[$i]);
				$backend->addToGroup($newMembers[$i], $groupId);
			}
		}		
		return new JSONResponse(
			$obj,
			RESPONSE_TO_GROUP_CREATE
		);
	}
}

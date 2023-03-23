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

const DEMO_DOMAIN = "pondersource.net";

const SCIM_ENDPOINTS = [
	     "almere.pondersource.net" =>      "https://almere.pondersource.net/index.php/apps/federatedgroups/scim",
 	"bergambacht.pondersource.net" => "https://bergambacht.pondersource.net/index.php/apps/federatedgroups/scim",
  	"castricum.pondersource.net" =>   "https://castricum.pondersource.net/index.php/apps/federatedgroups/scim"
];

/**
 * Class RdapiController
 *
 * @package OCA\FederatedGroups\Controller
 */
class RdapiController extends Controller {
	private $users;
	private $groups;

	/**
	 * OcmController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 */
	public function __construct(
		$appName,
		IRequest $request
	) {
		parent::__construct($appName, $request);
		$stored = file_get_contents('./users.json');
		$this->users = json_decode($stored, true);

		$stored = file_get_contents('./groups.json');
		$this->groups = json_decode($stored, true);
	}

	private function saveUsers() {
		error_log("Saving users.json");
		file_put_contents('./users.json', json_encode($this->users, JSON_PRETTY_PRINT));
	}
	
	private function saveGroups() {
		error_log("Saving groups.json");
		file_put_contents('./groups.json', json_encode($this->groups, JSON_PRETTY_PRINT));
	}

	private function getServersInvolved($groupObj) {
		$ret = [];
		foreach($groupObj["members"] as $member) {
      $parts = explode("@", $member["value"]);
			$ret[$parts[1]] = true;
			error_log("Server involved: " . $parts[1]);
		}
		error_log("Returning:");
		error_log(var_export(array_keys($ret), true));
		return array_keys($ret);
	}

	private function forwardToServers($method, $path, $data, $servers) {
		error_log("forwardToServers($method, $path, ...)");

    foreach($servers as $host) {
			if (isset(SCIM_ENDPOINTS[$host])) {
				$context  = stream_context_create(array(
					'http' => array(
						'header'  => "Content-type: application/json\r\n",
						'method'  => $method,
					'content' => http_build_query($data)
					)
				));
				$url = SCIM_ENDPOINTS[$host] . $path;
				$result = file_get_contents($url, false, $context);
				if ($result === FALSE) { 
					error_log("Could not $method to " . $url);
				}	else {
					error_log("Succesfully forwarded SCIM $method to " . $url);
				}
			} else {
				error_log("No known SCIM endpoint for $host");
			}
		}
	}

	private function lookupUser($user) {
		$emailAddress = $user["emails"][0]["value"];
		error_log(var_export($user["emails"], true));
		error_log("Email address is $emailAddress");
		$splitAtSign = explode("@", $emailAddress);
		$splitPlusSign = explode("+", $splitAtSign[0]);
		if (count($splitPlusSign) == 2) {
			error_log("user is recognized: " . $splitPlusSign[0] . "@" . $splitPlusSign[1] . "." . DEMO_DOMAIN);
			return $splitPlusSign[0] . "@" . $splitPlusSign[1] . "." . DEMO_DOMAIN;
		}
		error_log("user is not recognized: " . $user["externalId"]);
		return $user["externalId"];
	}

	private function lookupGroup($group) {
		return $group["displayName"];
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function getUsers() {
		error_log("scim get users ");
		$filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		$qs = explode(" ", $filter);
		list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;
		$id = json_decode($value); // strip quotes
		if (isset($this->users[$id])) {
			error_log("User $value exists!");
			return new JSONResponse([
				"totalResults" => 1,
				"Resources" => [
					$this->users[$id],
				],
			], Http::STATUS_OK);
		} else {
			error_log("User $value exists not!");
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
	}


	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function createUser() {
		error_log("scim create user");
		$body = file_get_contents("php://input");
		$obj = json_decode($body, true);

		error_log("=========================bodyJson=============================");
		error_log(var_export($obj, true));
		error_log("=========================bodyJson=============================");
		$obj["id"] = $this->lookupUser($obj);
    $this->users[$obj["externalId"]] = $obj;
		error_log("User added, saving!");
		$this->saveUsers();
		return new JSONResponse(
			$obj,
			Http::STATUS_CREATED
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function updateUser() {
		error_log("scim update user");
		$body = file_get_contents("php://input");
		$obj = json_decode($body, true);

		error_log("=========================bodyJson=============================");
		error_log(var_export($obj, true));
		error_log("=========================bodyJson=============================");
		$obj["id"] = $this->lookupUser($obj);
    $this->users[$obj["externalId"]] = $obj;
		error_log("User updated, saving!");
		$this->saveUsers();
		return new JSONResponse(
			$obj,
			Http::STATUS_CREATED
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function getGroups() {
		error_log("scim get groups");
		$filter = $this->request->getParam('filter', null); // externalId eq "1dad78c9-c74b-4f7d-9f98-eab912cbfd07@sram.surf.nl"
		$qs = explode(" ", $filter);
		list($field, $condition, $value) = $qs; // [$field, $condition, $value] = $qs;
		$id = json_decode($value); // strip quotes
		if (isset($this->groups[$id])) {
			error_log("Group $value exists!");
			return new JSONResponse([
				"totalResults" => 1,
				"Resources" => [
					$this->groups[$id],
				],
			], Http::STATUS_OK);
		} else {
			error_log("Group $value exists not!");
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
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
		$obj["id"] = $this->lookupGroup($obj);
    $this->groups[$obj["externalId"]] = $obj;
		$this->saveGroups();
		error_log("Forwarding this SCIM message");
		$this->forwardToServers("POST", "/Groups", $obj, $this->getServersInvolved($obj));
		return new JSONResponse(
			$obj,
			Http::STATUS_CREATED
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function updateGroup() {
		error_log("scim update group");
		$obj = json_decode(file_get_contents("php://input"), true);

		error_log("=========================bodyJson=============================");
		error_log(var_export($obj, true));
		error_log("=========================bodyJson=============================");
		$obj["id"] = $this->lookupGroup($obj);
    $this->groups[$obj["externalId"]] = $obj;
		$this->saveGroups();
		error_log("Forwarding this SCIM message");
		$this->forwardToServers("PUT", "/Groups", $obj, $this->getServersInvolved($obj));
		return new JSONResponse(
			$obj,
			Http::STATUS_CREATED
		);
	}

}

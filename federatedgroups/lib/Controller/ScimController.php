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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class ScimController
 *
 * @package OCA\FederatedGroups\Controller
 */
class ScimController extends Controller {
	/* @var IDBConnection */
	private $dbConn;
  public function __construct(
		$appName,
		IRequest $request,
		IDBConnection $dbConn
	) {
		parent::__construct($appName, $request);
		error_log("Federated Groups ScimController constructed");
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
		$queryBuilder = $this->dbConn->getQueryBuilder();
		$result = $queryBuilder->insert('group_user')
			->values([
				'uid' => $queryBuilder->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'gid' => $queryBuilder->createNamedParameter($regularGroupId, IQueryBuilder::PARAM_STR),
			])->execute();
			// error_log(var_export($result, true));
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
    error_log("scimming $groupId!");
		try {
			$body = json_decode(file_get_contents('php://input'), true);
			$ops = $body['Operations'];
		} catch (Exception $e) {
			return new JSONResponse(
				[ "Could not parse operations array"],
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
				[ "Could only execute $success out of " . count($body['Operations']) . " operations"],
				Http::STATUS_BAD_REQUEST
			);
		}
  }
}
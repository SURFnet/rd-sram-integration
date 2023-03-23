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

const RESPONSE_TO_USER_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;

const RESPONSE_TO_GROUP_CREATE = Http::STATUS_CREATED;
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

	private $users;
	private $groups;

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
		// error_log("Federated Groups ScimController constructed");
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
		
		
		return new JSONResponse(
			$obj,
			RESPONSE_TO_GROUP_CREATE
		);
	}
}

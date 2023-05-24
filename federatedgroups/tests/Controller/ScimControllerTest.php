<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

// use OCA\OpenCloudMesh\AppInfo\Application;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCA\FederatedGroups\Controller\ScimController;
use OCP\AppFramework\Http;
use Test\TestCase;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IDBConnection;
use OCP\Files\IRootFolder;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\FederatedFileSharing\AddressHandler;



const RESPONSE_TO_USER_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;
const RESPONSE_TO_GROUP_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_GROUP_UPDATE = Http::STATUS_OK;
const IGNORE_DOMAIN = "sram.surf.nl";

function getOurDomain() {
	return getenv("SITE") . ".pondersource.net";
}

/**
 * @group DB
 */
class ScimControllerTest extends TestCase {

	/**
	 * @var IGroupManager $groupManager
	 */
	private $groupManager;
	/**
	 * @var MixedGroupShareProvider
	 */
	protected $mixedGroupShareProvider;

	/**
	 * @var IDBConnection
	 */
	private $dbConnection;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var GroupNotifications
	 */
	private $groupNotifications;

	/**
	 * @var TokenHandler
	 */
	private $tokenHandler;

	/**
	 * @var AddressHandler
	 */
	private $addressHandler;

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var ScimController
	 */
	private $controller;
	/**
	 * @var IRequest
	 */
	private $request;



	protected function setUp(): void {
		parent::setUp();
		// $this->request = new IRequest();
		$this->request = $this->createMock(IRequest::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->controller = new ScimController(
			"federatedGroups",
			$this->request,
			$this->groupManager,
		);

		$this->dbConnection = \OC::$server->getDatabaseConnection();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->groupNotifications = $this->createMock(GroupNotifications::class);
		$this->tokenHandler = $this->createMock(TokenHandler::class);
		$this->tokenHandler->method("generateToken")->willReturn("someToken");
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->l = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->mixedGroupShareProvider = new MixedGroupShareProvider(
			$this->dbConnection,
			$this->userManager,
			$this->groupManager,
			$this->rootFolder,
			$this->groupNotifications,
			$this->tokenHandler,
			$this->addressHandler,
			$this->l,
			$this->logger
		);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	// private function checkNeedToSend($newUser, $existingUsers) {
	// 	$newUserParts = explode("#", $newUser);
	// 	if (count($newUserParts) == 1) return false; // local user

	// 	if (count($newUserParts) == 2) { // remote user
	// 		if (str_contains($newUserParts[1], '#') && !str_contains($newUserParts[1], getOurDomain())) {
	// 			return false;
	// 		}
	// 		$newDomain = $newUserParts[1];
	// 		foreach ($existingUsers as $existingUser) {
	// 			$existingUserParts = explode("#", $existingUser);
	// 			if (count($existingUserParts) == 2) {
	// 				if ($existingUserParts[1] == $newDomain) {
	// 					return false;
	// 				}
	// 			}
	// 		}
	// 		return $newDomain;
	// 	}
	// 	return false;
	// }


	// public function test_createGroup() {
	// 	$body = $this->createGroupData();
	// 	$groupId = $body["id"];
	// 	$currentMembers = ["currentMember"];
	// 	$newMembers = [];
	// 	$group = $this->createMock(\OCP\IGroup::class);

	// 	$groupBackend = $this->createMock(\OC\Group\Backend::class);

	// 	$this->groupManager->expects($this->once())->method("createGroup")->with($groupId);

	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);

	// 	$group->expects($this->once())->method('getBackend')->willReturn($groupBackend);

	// 	$groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

	// 	foreach ($body["members"] as $member) {
	// 		$userIdParts = explode("@", $member["value"]); // "test_u@pondersource.net"  => ["test_u", "pondersource.net"] 
	// 		if (count($userIdParts) == 3) {
	// 			$userIdParts = [$userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
	// 		}
	// 		if (count($userIdParts) != 2) {
	// 			throw new \Exception("cannot parse OCM user " . $member["value"]);
	// 		}
	// 		$newMember = $userIdParts[0];
	// 		if ($userIdParts[1] !== getOurDomain()) {
	// 			$newMember .= "#" . $userIdParts[1];
	// 		}
	// 		if ($userIdParts[1] === IGNORE_DOMAIN) {
	// 			continue;
	// 		}
	// 		$newMembers[] = $newMember;
	// 	}

	// 	for ($i = 0; $i < count($currentMembers); $i++) {
	// 		if (!in_array($currentMembers[$i], $newMembers)) {
	// 			$groupBackend->expects($this->once())->method('removeFromGroup');
	// 		}
	// 	}
	// 	for ($i = 0; $i < count($newMembers); $i++) {
	// 		if (!in_array($newMembers[$i], $currentMembers)) {
	// 			$newDomain = $this->checkNeedToSend($newMembers[$i], $currentMembers);
	// 			if ($newDomain !== false) {
	// 				try {
	// 					$this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);
	// 				} catch (\Throwable $th) {
	// 					throw $th;
	// 				}
	// 			}
	// 			$groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[$i], $groupId);
	// 		}
	// 	}

	// 	$expected = $this->createGroupData();

	// 	$this->assertEquals($body, $expected);
	// }
	// public function test_updateGroup() {
	// 	$body = $this->updateGroupData();

	// 	$currentMembers = ["currentMember"];
	// 	$newMembers = [];
	// 	$group = $this->createMock(\OCP\IGroup::class);

	// 	$groupBackend = $this->createMock(\OC\Group\Backend::class);

	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);

	// 	$group->expects($this->once())->method('getBackend')->willReturn($groupBackend);

	// 	$groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

	// 	foreach ($body["members"] as $member) {
	// 		$userIdParts = explode("@", $member["value"]); // "test_u@pondersource.net"  => ["test_u", "pondersource.net"] 
	// 		if (count($userIdParts) == 3) {
	// 			$userIdParts = [$userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
	// 		}
	// 		if (count($userIdParts) != 2) {
	// 			throw new \Exception("cannot parse OCM user " . $member["value"]);
	// 		}
	// 		$newMember = $userIdParts[0];
	// 		if ($userIdParts[1] !== getOurDomain()) {
	// 			$newMember .= "#" . $userIdParts[1];
	// 		}
	// 		if ($userIdParts[1] === IGNORE_DOMAIN) {
	// 			continue;
	// 		}
	// 		$newMembers[] = $newMember;
	// 	}

	// 	for ($i = 0; $i < count($currentMembers); $i++) {
	// 		if (!in_array($currentMembers[$i], $newMembers)) {
	// 			$groupBackend->expects($this->once())->method('removeFromGroup');
	// 		}
	// 	}
	// 	for ($i = 0; $i < count($newMembers); $i++) {
	// 		if (!in_array($newMembers[$i], $currentMembers)) {
	// 			$newDomain = $this->checkNeedToSend($newMembers[$i], $currentMembers);
	// 			if ($newDomain !== false) {
	// 				try {
	// 					$this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);
	// 				} catch (\Throwable $th) {
	// 					throw $th;
	// 				}
	// 			}
	// 			$groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[$i], $groupId);
	// 		}
	// 	}

	// 	$expected = $this->updateGroupData();

	// 	$this->assertEquals($body, $expected);
	// }


	// private function updateGroupData() {
	// 	return [
	// 		"members" => [
	// 			[
	// 				"value" => "test_user@oc2.docker",
	// 				"ref" => "",
	// 				"displayName" => ""
	// 			]
	// 		]
	// 	];
	// }
	// private function createGroupData() {
	// 	return [
	// 		"id" => "test_group",
	// 		"members" => [
	// 			[
	// 				"value" => "test_user@oc2.docker",
	// 				"ref" => "",
	// 				"displayName" => ""
	// 			]
	// 		]
	// 	];
	// }


	// deleteGroup START
	public function test_it_can_delete_created_group() {
		$groupId = 'test-group';
		$members = [["value" => "test_user@oc2.docker"]];
		$group = $this->createMock(\OCP\IGroup::class);
		$groupBackend = $this->createMock(\OC\Group\Backend::class);

		$this->groupManager->expects($this->once())->method("createGroup")->with($groupId);

		$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);

		$group->expects($this->once())->method("getBackend")->willReturn($groupBackend);

		// Create First
		$createResponse = $this->controller->createGroup($groupId, $members);

		// $this->assertEquals(Http::STATUS_CREATED, $createResponse->getStatus());


		// $group = $this->createMock(\OCP\IGroup::class);

		// $this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);

		// $group->expects($this->once())->method("delete");

		// $deleteResponse = $this->controller->deleteGroup($groupId);

		// $this->assertEquals(Http::STATUS_OK, $deleteResponse->getStatus());
	}

	// public function test_it_returns_204_when_delete_non_existing_group() {
	// 	$groupId = 'test-group';

	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId);

	// 	$deleteResponse = $this->controller->deleteGroup($groupId);

	// 	$this->assertEquals(Http::STATUS_NO_CONTENT, $deleteResponse->getStatus());
	// }
	// deleteGroup END


	// getGroups START
	// public function test_it_can_get_list_of_groups() {

	// 	$response = $this->controller->getGroups();

	// 	$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	// }
	// getGroups END


	// getGroup START
	// public function test_it_returns_404_if_groups_not_exists() {
	// 	$groupId = 'test-group';

	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId);

	// 	$getResponse = $this->controller->getGroup($groupId);

	// 	$this->assertEquals(Http::STATUS_NOT_FOUND, $getResponse->getStatus());
	// }

	// public function test_it_can_get_group_if_exists() {
	// 	$groupId = 'test-group';
	// 	$displayName = 'test-group';
	// 	$members = [["value" => "test_user@oc2.docker"]];

	// 	$createResponse = $this->controller->createGroup($groupId, $members);

	// 	// $this->assertEquals(Http::STATUS_CREATED, $createResponse->getStatus());

	// 	$group = $this->createMock(\OCP\IGroup::class);
	// 	$groupBackend = $this->createMock(\OC\Group\Backend::class);

	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);

	// 	$group->expects($this->once())->method("getGID")->willReturn($groupId);
	// 	$group->expects($this->once())->method("getDisplayName")->willReturn($displayName);
	// 	$group->expects($this->once())->method("getBackend")->willReturn($groupBackend);

	// 	$groupBackend->expects($this->once())->method("usersInGroup")->with($groupId);

	// 	$getResponse = $this->controller->getGroup($groupId);

	// 	$this->assertEquals(Http::STATUS_OK, $getResponse->getStatus());
	// }
	// getGroup END


	#region updateGroup
	// public function test_it_can_update_group_if_exists() {

	// 	$groupId = 'test-group';
	// 	$currentMembers = ["currentMember"];
	// 	$members = [["value" => "test_user@oc2.docker"]];
	// 	$newMembers = ["test_user#oc2.docker"];

	// 	// Create First
	// 	$createResponse = $this->controller->createGroup($groupId, $members);


	// 	$group = $this->createMock(\OCP\IGroup::class);
	// 	$groupBackend = $this->createMock(\OC\Group\Backend::class);


	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);
	// 	$group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
	// 	$groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

	// 	$newDomain = explode("#", $newMembers[0]);
	// 	// $groupBackend->expects($this->once())->method('removeFromGroup');
	// 	$this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);

	// 	$groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[0], $groupId);

	// 	$response = $this->controller->updateGroup($groupId, $members);

	// 	$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	// }
	#endregion updateGroup

	#region createGroup
	// public function test_it_can_create_group() {

	// 	$groupId = 'test-group';
	// 	$members = [["value" => "test_user@oc2.docker"]];

	// 	$this->groupManager->expects($this->once())->method("createGroup")->with($groupId);



	// 	$currentMembers = ["currentMember"];
	// 	$newMembers = ["test_user#oc2.docker"];


	// 	$group = $this->createMock(\OCP\IGroup::class);
	// 	$groupBackend = $this->createMock(\OC\Group\Backend::class);


	// 	$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);
	// 	$group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
	// 	$groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

	// 	$newDomain = explode("#", $newMembers[0]);
	// 	$groupBackend->expects($this->once())->method('removeFromGroup');
	// 	$this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);

	// 	$groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[0], $groupId);

	// 	$response = $this->controller->createGroup($groupId, $members);

	// 	$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	// }
	#endregion createGroup
}

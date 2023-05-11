<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

use OCA\FederatedGroups\Controller\ScimController;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCP\AppFramework\Http;
use Test\TestCase;

const RESPONSE_TO_USER_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;
const RESPONSE_TO_GROUP_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_GROUP_UPDATE = Http::STATUS_OK;
const IGNORE_DOMAIN = "sram.surf.nl";

function getOurDomain() {
	return getenv("SITE") . ".pondersource.net";
}

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

	private function checkNeedToSend($newUser, $existingUsers) {
		$newUserParts = explode("#", $newUser);
		if (count($newUserParts) == 1) return false; // local user

		if (count($newUserParts) == 2) { // remote user
			if (str_contains($newUserParts[1], '#') && !str_contains($newUserParts[1], getOurDomain())) {
				return false;
			}
			$newDomain = $newUserParts[1];
			foreach ($existingUsers as $existingUser) {
				$existingUserParts = explode("#", $existingUser);
				if (count($existingUserParts) == 2) {
					if ($existingUserParts[1] == $newDomain) {
						return false;
					}
				}
			}
			return $newDomain;
		}
		return false;
	}
	public function test_createGroup() {
		$body = [
			"id" => "test_group",
			"members" => [
				[
					"value" => "fed_user_2@oc2.docker",
					"ref" => "",
					"displayName" => ""
				]
			]
		];

		$groupId = $body["id"];
		$currentMembers = ["currentMember"];
		$newMembers = [];
		$groupMock = $this->createMock(\OCP\IGroup::class);

		$groupBackend = $this->createMock(\OC\Group\Backend::class);

		$this->groupManager->expects($this->once())->method("createGroup")->with($groupId);

		$this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($groupMock);

		$groupMock->expects($this->once())->method('getBackend')->willReturn($groupBackend);

		$groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

		foreach ($body["members"] as $member) {
			$userIdParts = explode("@", $member["value"]); // "test_u@pondersource.net"  => ["test_u", "pondersource.net"] 
			if (count($userIdParts) == 3) {
				$userIdParts = [$userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
			}
			if (count($userIdParts) != 2) {
				throw new \Exception("cannot parse OCM user " . $member["value"]);
			}
			$newMember = $userIdParts[0];
			if ($userIdParts[1] !== getOurDomain()) {
				$newMember .= "#" . $userIdParts[1];
			}
			if ($userIdParts[1] === IGNORE_DOMAIN) {
				continue;
			}
			$newMembers[] = $newMember;
		}

		for ($i = 0; $i < count($currentMembers); $i++) {
			if (!in_array($currentMembers[$i], $newMembers)) {
				$groupBackend->expects($this->once())->method('removeFromGroup');
			}
		}
		for ($i = 0; $i < count($newMembers); $i++) {
			if (!in_array($newMembers[$i], $currentMembers)) {
				$newDomain = $this->checkNeedToSend($newMembers[$i], $currentMembers);
				if ($newDomain !== false) {
					try {
						$this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);
					} catch (\Throwable $th) {
						throw $th;
					}
				}
				$groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[$i], $groupId);
			}
		}

		$expected = [
			"id" => "test_group",
			"members" => [
				[
					"value" => "fed_user_2@oc2.docker",
					"ref" => "",
					"displayName" => ""
				]
			]
		];

		$this->assertEquals($body, $expected);
	}
}

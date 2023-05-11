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
			// $this->l = $this->createMock(IL10N::class);
			// $this->logger = $this->createMock(ILogger::class);
			// $federatedGroupsApp = $this->getMockBuilder(\OCA\FederatedGroups\AppInfo\Application::class);
			// $this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
			// $this->dbConnection = \OC::$server->getDatabaseConnection();
			// $this->userManager = $this->createMock(IUserManager::class);
			// $this->rootFolder = $this->createMock(IRootFolder::class);
			// $this->groupNotifications = $this->createMock(GroupNotifications::class);
			// $this->tokenHandler = $this->createMock(TokenHandler::class);
			// $this->addressHandler = $this->createMock(AddressHandler::class);
		);
	}

	public function tearDown(): void {
		// $this->dbConnection->getQueryBuilder()->delete('share')->execute();
		// $this->dbConnection->getQueryBuilder()->delete('filecache')->execute();

		parent::tearDown();
	}


	public function testGetGroups() {
		// $expected = [
		// 	'totalResults' => 2,
		// 	'Resources' => [
		// 		[
		// 			'id' => 'admin',
		// 			'displayName' => 'admin',
		// 			'members' => [
		// 				'value' => 'admin_user',
		// 				'ref' => '',
		// 				'displayName' => '',
		// 			]
		// 		],
		// 		[
		// 			'id' => 'federalists',
		// 			'displayName' => 'federalists',
		// 			'members' => [
		// 				'value' => 'federalist_user',
		// 				'ref' => '',
		// 				'displayName' => '',
		// 			]
		// 		]
		// 	]
		// ];
		// json_encode($expected);

		[$adminGroupBackendMock, $federalistsGroupBackendMock] = $this->getGroupBackendMocks();
		[$groupMockAdmin, $groupMockFederalists] = $this->getGroupMocks();


		// $adminGroupBackendMock = $this->createMock(\OCP\GroupInterface::class)->expects(self::once())
		// 	->method("getGroups")->willReturn(["admin"])
		// 	->method("usersInGroup")->with("admin")->willReturn(["admin_user"]);

		// $federalistsGroupBackendMock = $this->createMock(\OCP\GroupInterface::class)->expects(self::once())
		// 	->method("getGroups")->willReturn(["federalists"])
		// 	->method("usersInGroup")->with("federalists")->willReturn(["federalist_user"]);


		// $groupMockAdmin = $this->createMock(\OCP\IGroup::class)
		// 	->method("getGID")->willReturn("admin")
		// 	->method("getDisplayName")->willReturn("admin")
		// 	->method("getBackend")->willReturn($adminGroupBackendMock);

		// $groupMockFederalists = $this->createMock(\OCP\IGroup::class)
		// 	->method("getGID")->willReturn("federalists")
		// 	->method("getDisplayName")->willReturn("federalists")
		// 	->method("getBackend")->willReturn($federalistsGroupBackendMock);



		// $groupBackend = $this->createMock(Backend::class)->expects(self::once())
		// 	->method("usersInGroup")->willReturn(
		// 		[
		// 			"user1#".self::GROUP_NAME."@host1.co",
		// 			"user2#".self::GROUP_NAME."@host2.co",
		// 			"local_user"
		// 		]
		// 	);


		// $this->groupManager->expects($this->once())->method("getBackends")->willReturn([
		// 	[$adminGroupBackendMock, $federalistsGroupBackendMock]
		// ]);




		// $this->groupManager->expects($this->exactly(2))->method("get");


		$groups = $this->controller->getGroups();
		
	}

	protected function getGroupBackendMocks() {
		$adminGroupBackendMock = $this->createMock(\OCP\GroupInterface::class)->expects(self::once())
			->method("getGroups")->willReturn(["admin"])
			->method("usersInGroup")->with("admin")->willReturn(["admin_user"]);

		$federalistsGroupBackendMock = $this->createMock(\OCP\GroupInterface::class)->expects(self::once())
			->method("getGroups")->willReturn(["federalists"])
			->method("usersInGroup")->with("federalists")->willReturn(["federalist_user"]);

		return [$adminGroupBackendMock, $federalistsGroupBackendMock];
	}
	protected function getGroupMocks() {
		[$adminGroupBackendMock, $federalistsGroupBackendMock] = $this->getGroupBackendMocks();
		$groupMockAdmin = $this->createMock(\OCP\IGroup::class)
			->method("getGID")->willReturn("admin")
			->method("getDisplayName")->willReturn("admin")
			->method("getBackend")->willReturn($adminGroupBackendMock);

		$groupMockFederalists = $this->createMock(\OCP\IGroup::class)
			->method("getGID")->willReturn("federalists")
			->method("getDisplayName")->willReturn("federalists")
			->method("getBackend")->willReturn($federalistsGroupBackendMock);

		return [$groupMockAdmin, $groupMockFederalists];
	}
}

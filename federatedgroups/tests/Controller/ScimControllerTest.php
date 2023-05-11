<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

// use OCA\OpenCloudMesh\Tests\FederatedFileSharing\TestCase;

use OCA\FederatedGroups\MixedGroupShareProvider;
use Test\TestCase;
use OCP\AppFramework\Http;

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
	 * @var MixedGroupShareProviderTest
	 */
	private $mixGroupProvider;
	/**
	 * @var ScimController
	 */
	private $scimController;
	/**
	 * @var IRequest
	 */
	private $request;

	protected function setUp(): void {
		parent::setUp();
		// $federatedGroupsApp = $this->getMockBuilder(\OCA\FederatedGroups\AppInfo\Application::class);
		// $this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();

		$this->dbConnection = \OC::$server->getDatabaseConnection();

		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->groupNotifications = $this->createMock(GroupNotifications::class);
		$this->tokenHandler = $this->createMock(TokenHandler::class);
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


		$this->scimController = new ScimController( "federatedGroups",$this->request,$this->groupManager,
);


	}

	public function tearDown(): void {
		// $this->dbConnection->getQueryBuilder()->delete('share')->execute();
		// $this->dbConnection->getQueryBuilder()->delete('filecache')->execute();

		parent::tearDown();
	}


	public function it_will_return_groups() {
		$expectedResult = [
			'totalResults' => 2,
			'Resources' => [
			  [
				'id' => 'admin',
				'displayName' => 'admin',
				'members' => [
				  'value' => 'admin_user',
				  'ref' => '',
				  'displayName' => '',
				]
			  ],
			  [
				'id' => 'federalists',
				'displayName' => 'federalists',
				'members' => [
				  'value' => 'federalist_user',
				  'ref' => '',
				  'displayName' => '',
				]
			  ]
			]
		  ];

		  json_encode($expectedResult);

		$groupBackendMock1 = $this->createMock(\OCP\GroupInterface::class)->expects(self::once())
			->method("getGroups")->willReturn(["admin"])
			->method("usersInGroup")->with("admin")->willReturn(["admin_user"]);
			
		$groupBackendMock2 = $this->createMock(\OCP\GroupInterface::class)->expects(self::once())
			->method("getGroups")->willReturn(["federalists"])
			->method("usersInGroup")->with("federalists")->willReturn(["federalist_user"]);


		$groupMockAdmin = $this->createMock(\OCP\IGroup::class)
			->method("getGID")->willReturn("admin")
			->method("getDisplayName")->willReturn("admin")
			->method("getBackend")->willReturn($groupBackendMock1);
		
		$groupMockFederalists = $this->createMock(\OCP\IGroup::class)
			->method("getGID")->willReturn("federalists")
			->method("getDisplayName")->willReturn("federalists")
			->method("getBackend")->willReturn($groupBackendMock2);

		
		$this->scimController->getGroups();

		// $groupBackend = $this->createMock(Backend::class)->expects(self::once())
		// 	->method("usersInGroup")->willReturn(
		// 		[
		// 			"user1#".self::GROUP_NAME."@host1.co",
		// 			"user2#".self::GROUP_NAME."@host2.co",
		// 			"local_user"
		// 		]
		// 	);
		

		$this->groupManager->expects($this->once())->method("getBackends")->willReturn([
			[$groupBackendMock1, $groupBackendMock2]
		]);




		$this->groupManager->expects($this->exactly(2))->method("get");



	}
}

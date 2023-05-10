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

	protected function setUp(): void {
		parent::setUp();
		// $federatedGroupsApp = $this->getMockBuilder(\OCA\FederatedGroups\AppInfo\Application::class);
		// $this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();

		$this->dbConnection = \OC::$server->getDatabaseConnection();
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
	}

	public function tearDown(): void {
		// $this->dbConnection->getQueryBuilder()->delete('share')->execute();
		// $this->dbConnection->getQueryBuilder()->delete('filecache')->execute();

		parent::tearDown();
	}


	public function it_will_return_groups() {
		// $this->groupManager->expects($this->once())->method("get")
		// 	->willReturn($group);
	}
}

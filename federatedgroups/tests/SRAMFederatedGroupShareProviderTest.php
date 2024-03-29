<?php

namespace OCA\FederatedGroups\Tests;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\FederatedGroups\ShareProviderFactory;
use OCA\FederatedGroups\SRAMFederatedGroupShareProvider;
use OCA\Files_External\Command\Config;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCA\OpenCloudMesh\Files_Sharing\External\Manager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IProviderFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @group DB
 */
class SRAMFederatedGroupShareProviderTest extends \Test\TestCase {

	/**
	 * @var IDBConnection
	 */
	private $dbConnection;

	/**
	 * @var EventDispatcherInterface
	 */
	private $eventDispatcher;

	/**
	 * @var AddressHandler
	 */
	private $addressHandler;

	/**
	 * @var GroupNotifications
	 */
	private $groupNotification;

	/**
	 * @var TokenHandler
	 */
	private $tokenHandler;

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ShareProviderFactory
	 */
	private $shareProviderFactory;

	/**
	 * @var SRAMFederatedGroupShareProvider
	 */
	private $sramShareProvider;

	public function setUp() : void{
		$this->dbConnection = \OC::$server->getDatabaseConnection();
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->groupNotification = $this->createMock(GroupNotifications::class);
		$this->tokenHandler = $this->createMock(TokenHandler::class);
		$this->l = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->shareProviderFactory = $this->createMock(IProviderFactory::class);
		parent::setUp();

		$this->sramShareProvider = new SRAMFederatedGroupShareProvider(
			$this->dbConnection,
			$this->eventDispatcher,
			$this->addressHandler,
			$this->groupNotification,
			$this->tokenHandler,
			$this->l,
			$this->logger,
			$this->rootFolder,
			$this->config,
			$this->userManager,
			$this->shareProviderFactory,
			function (){
				return $this->createMock(Manager::class);
			}
		);
	}

	public function tearDown(): void {
		$this->dbConnection->getQueryBuilder()->delete('share')->execute();
		$this->dbConnection->getQueryBuilder()->delete('filecache')->execute();
		parent::tearDown();
	}

	public function shareTypeIndicator(){
		return[
			[Share::SHARE_TYPE_GROUP, true],
			[Share::SHARE_TYPE_REMOTE_GROUP, true],
		];
	}
	
	/**
	 * @dataProvider shareTypeIndicator
	 */
	public function testGetShareById($shareType, $expected){
		$qb = $this->dbConnection->getQueryBuilder();
		$insertedIds =[];
		$qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal($shareType),
				'share_with'    => $qb->expr()->literal('password'),
				'uid_owner'     => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(42),
				'file_target'   => $qb->expr()->literal('myTarget'),
				'permissions'   => $qb->expr()->literal(13),
				'token'         => $qb->expr()->literal('secrettoken'),
				'share_name'          => $qb->expr()->literal('some_name'),
			]);
		$qb->execute();
		$insertedId = "{$qb->getLastInsertId()}";
		
		$share = $this->sramShareProvider->getShareById($insertedId);
		$itemFound = !empty($share);
		$this->assertEquals($expected, $itemFound);
	}


	public function testGetShareByIdOnInvalidId(){
		$qb = $this->dbConnection->getQueryBuilder();
		$this->expectException(ShareNotFound::class);
		$this->sramShareProvider->getShareById("invalideId");
		
	}

	public function testGetShareByIdOnWrongId(){
		$qb = $this->dbConnection->getQueryBuilder();
		$this->expectException(ShareNotFound::class);
		$this->sramShareProvider->getShareById("100");
		
	}
}
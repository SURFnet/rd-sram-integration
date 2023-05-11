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
use OCP\Share\IProviderFactory;
use Predis\Command\Argument\Server\To;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

		$sramShareProvider = new SRAMFederatedGroupShareProvider(
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

	public function getShareByIdTest(){
		$qb = $this->dbConnection->getQueryBuilder();
		$insertedIds =[];
		$qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(Share::SHARE_TYPE_GROUP),
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
		$insertedIds[] = $qb->getLastInsertId();

		$qb->insert('share')->values(
			[
				'share_type'    => $qb->expr()->literal(Share::SHARE_TYPE_REMOTE_GROUP),
				'share_with'    => $qb->expr()->literal('another_password'),
				'uid_owner'     => $qb->expr()->literal('another_shareOwner'),
				'uid_initiator' => $qb->expr()->literal('another_sharedBy'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(43),
				'file_target'   => $qb->expr()->literal('another_Target'),
				'permissions'   => $qb->expr()->literal(13),
				'token'         => $qb->expr()->literal('anothersecrettoken'),
				'share_name'    => $qb->expr()->literal('another_name'),
			]);
		$qb->execute();
		$insertedIds[] = $qb->getLastInsertId();

		foreach ($insertedIds as $id){
			$share = $this->sramShareProvider->getShareById($id);
			$this->assertNotEmpty($share);
		}
	}
}
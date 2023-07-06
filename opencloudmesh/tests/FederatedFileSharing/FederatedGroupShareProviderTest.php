<?php
namespace OCA\OpenCloudMesh\Tests\FederatedFileSharing;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\OpenCloudMesh\FederatedFileSharing\FedGroupShareManager;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\IProviderFactory;

/**
 * Class FedUserShareManagerTest
 *
 * @package OCA\OpenCloudMesh\FederatedFileSharing\Tests
 * @group DB
 */
class FederatedGroupShareProviderTest extends TestCase{

    /** @var FederatedGroupShareProvider*/
    private $federatedGroupShareProvider; 

    /** @var IDBConnection */
    private $connection;
	
    /** @var EventDispatcherInterface */ 
    private $eventDispatcher; 
		
    /** @var AddressHandler*/
    private  $addressHandler;

	/** @var GroupNotifications */
    private $notifications;

	/** @var TokenHandler*/ 
    private $tokenHandler;
	
    /** @var IL10N */ 
    private  $l10n; 

	/** @var ILogger */
    private $logger; 

	/** @var IRootFolder */
    private $rootFolder;
    
    /** @var IConfig */ 
    private $config; 

	/** @var IUserManager */
    private $userManager;

	/** @var IProviderFactory */
    private $shareProviderFactory;

	/** @var callable */
    private $externalManager;

    protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->rootFolder = $this->createMock(IRootFolder::class);
        $this->userManager = $this->createMock(IUserManager::class);
		
		$this->addressHandler = $this->createMock(AddressHandler::class);

		$this->config = $this->createMock(IConfig::class);

        $this->notifications = $this->createMock(GroupNotifications::class);

		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->tokenHandler = $this->createMock(TokenHandler::class);
        $this->connection = \OC::$server->getDatabaseConnection();
        $this->shareProviderFactory = $this->createMock(IProviderFactory::class);
        $this->externalManager = $this->createMock(FedGroupShareManager::class);

		$this->federatedGroupShareProvider = new FederatedGroupShareProvider(
            $this->connection,
            $this->eventDispatcher,
            $this->addressHandler,
            $this->notifications,
            $this->tokenHandler,
            $this->l10n,
            $this->logger,
            $this->rootFolder,
            $this->config,
            $this->userManager,
            $this->shareProviderFactory,
            function(){
                return $this->externalManager;
            }
        );
	}

    public function testUnshare(){

        $this->federatedGroupShareProvider->unshare(10,"abc");
    }

}
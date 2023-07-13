<?php
namespace OCA\OpenCloudMesh\Tests\FederatedFileSharing;

use Error;
use Exception;
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

    private $tableName = "share_external_group";
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

        $group_share = [
			'token'         => 'abc',
			'password'		=> 'mypass',
			'name'			=> 'share_name',
			'owner'			=> 'share_owner',
			'user'			=> 'group',
			'accepted'		=> 1,
			'remote_id'		=> 5,
            'remote'        => 'oc1.docker',
            'parent'        => -1
        ];
        $parentId = $this->fillDB($group_share);
        $user_share = [
			'token'         => 'abc',
			'password'		=> 'mypass',
			'name'			=> 'share_name',
			'owner'			=> 'share_owner',
			'user'			=> 'user1',
			'accepted'		=> 1,
			'remote_id'		=> 5,
            'remote'        => 'oc1.docker',
            'parent'        => $parentId
        ];
        
        $var = $this->fillDB($user_share);
        $count = $this->getRowCount();
        $this->assertEquals(2,$count);
    
        $this->federatedGroupShareProvider->unshare(5,"abc");
        
        $count = $this->getRowCount();
        $this->assertEquals(0,$count);
    }

    private function getRowCount(){
        $result = $this->connection->executeQuery(
			'SELECT COUNT(*) AS `count` FROM `*PREFIX*share_external_group` Where `remote_id` = 5 and `share_token` = "abc"',
		)->fetch();
		return $result['count'];
    } 

    private function fillDB($share){
        $tmpMountPointName = '{{TemporaryMountPointName#' . $share['name'] . '}}';
		$mountPoint = $tmpMountPointName;
		$hash = \md5($tmpMountPointName);
		
        $query = $this->connection->getQueryBuilder();
		$i = 1;
		
        do{
            $query->insert($this->tableName)->values(
                [
                    'remote'		=> $query->expr()->literal($share['remote']),
                    'parent'        => $query->expr()->literal($share['parent']),
                    'share_token'	=> $query->expr()->literal($share['token']),
                    'password'		=> $query->expr()->literal($share['password']),
                    'name'			=> $query->expr()->literal($share['name']),
                    'owner'			=> $query->expr()->literal($share['owner']),
                    'user'			=> $query->expr()->literal($share['user']),
                    'mountpoint'	=> $query->expr()->literal($mountPoint),
                    'mountpoint_hash'	=> $query->expr()->literal($hash),
                    'accepted'		=> $query->expr()->literal($share['accepted']),
                    'remote_id'		=> $query->expr()->literal($share['remote_id']),
                ]
            );
            $data['mountpoint'] = $tmpMountPointName . '-' . $i;
			$data['mountpoint_hash'] = \md5($data['mountpoint']);
			$i++;
        } while(!$query->execute());
        
        return $query->getLastInsertId($this->tableName);
    }
}
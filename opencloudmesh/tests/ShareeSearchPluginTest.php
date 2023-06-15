<?php 
namespace OCA\OpenCloudMesh\Tests;

use OCA\OpenCloudMesh\ShareeSearchPlugin;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IRemoteShareesSearch;
use OCP\Share;
use OCP\Contacts\IManager;
use OCP\Util\UserSearch;
use OCP\IUser;

class ShareeSearchPluginTest extends \Test\TestCase{

    /**
     * @var ShareeSearchPlugin 
     */
    private $shareeSearchPluguin;


    protected $shareeEnumeration;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $userId = 'user_id';

	/** @var IManager */
	protected $contactsManager;

	/** @var UserSearch*/
	protected $userSearch;

    /** @var IUserSession*/
    protected $userSession;


    /** @var IUser */
    protected $user; 

    public function setUp() : void{
        parent::setUp();

        $this->userManager = $this->createMock(IUserManager::class);
        $this->config = $this->createMock(IConfig::class);
        $this->config->method('getAppValue')
			->willReturn('yes');
        $this->contactsManager = $this->createMock(IManager::class);
        $this->userSearch = $this->createMock(UserSearch::class); 
        
        $this->user = $this->createMock(IUser::class); 
        $this->user->method("getUID")->willReturn($this->userId);

        $this->userSession = $this->createMock(IUserSession::class);
        $this->userSession->method("getUser")->willReturn($this->user);

        

    }


    public function test_search_when_contact_manager_has_CLOUD_item(){

        $this->contactsManager->expects($this->once())
            ->method("search")->willReturn([
                [
                    "CLOUD"=>"someCloudId@someCloud.com", 
                    "uid" =>"someCloudId",
                    "FN" => "someone"
                ]]);
        $this->shareeSearchPluguin = new ShareeSearchPlugin($this->config, $this->userManager, 
            $this->userSession, $this->contactsManager, $this->userSearch
        );

        $actual = $this->shareeSearchPluguin->search("someCloudId@someCloud.com");
        $this->assertCount(1, $actual);
        $hasRemote = array_reduce($actual, function($carry,$item){
            return $carry || $item["value"]["shareType"] == Share::SHARE_TYPE_REMOTE;
        });

        $hasRemoteGroup = array_reduce($actual, function($carry,$item){
            return $carry && $item["value"]["shareType"] != Share::SHARE_TYPE_REMOTE_GROUP;
        });

        $this->assertTrue($hasRemote);
        $this->assertFalse($hasRemoteGroup);
    }

}
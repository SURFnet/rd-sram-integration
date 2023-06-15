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
			->willReturnCallback(function ($appname, $configKey) {
                if ($appname === 'core' && $configKey ==='shareapi_allow_share_dialog_user_enumeration')
                    return "yes";
                if ($appname === 'dav' && $configKey ==='remote_search_properties')
                    return 'CLOUD,FN';
                else {
                    return null;
                }
            });
        //$this->config->method('getAppValue')->with('core', 'shareapi_allow_share_dialog_user_enumeration')
		//	->willReturn('CLOUD,FN');
            
        $this->contactsManager = $this->createMock(IManager::class);
        $this->userSearch = $this->createMock(UserSearch::class); 
        $this->userSearch->method("isSearchable")->willReturn(true); 
        
        $this->user = $this->createMock(IUser::class); 
        $this->user->method("getUID")->willReturn($this->userId);

        $this->userSession = $this->createMock(IUserSession::class);
        $this->userSession->method("getUser")->willReturn($this->user);
        $this->shareeSearchPluguin = new ShareeSearchPlugin($this->config, $this->userManager, 
            $this->userSession, $this->contactsManager, $this->userSearch
        );

    }


    public function test_search_when_contact_manager_has_CLOUD_item(){

        $this->contactsManager->expects($this->once())
            ->method("search")->willReturn([
                [
                    "CLOUD"=>"somecloudId@somecloud.com", 
                    "uid" =>"somecloudId",
                    "FN" => "someone"
                ]]);

        $actual = $this->shareeSearchPluguin->search("somecloudId@somecloud.com");
        $this->assertCount(1, $actual);
        $hasRemote = array_reduce($actual, function($carry,$item){
            return $carry || $item["value"]["shareType"] === Share::SHARE_TYPE_REMOTE;
        });

        $this->assertTrue($hasRemote);
    }

    public function test_search_for_local_entities(){

        $this->contactsManager->expects($this->once())
            ->method("search")->willReturn([
                []]);

        $actual = $this->shareeSearchPluguin->search("someLocalId");
        $this->assertEmpty($actual);
    }

    public function test_search_for_not_local_nor_contact_entities(){

        $this->contactsManager->expects($this->once())
            ->method("search")->willReturn([
                []]);
                
        $actual = $this->shareeSearchPluguin->search("someLocalId@cloud");

        $this->assertCount(2, $actual);
        $hasRemote = array_reduce($actual, function($carry,$item){
            return $carry || $item["value"]["shareType"] === Share::SHARE_TYPE_REMOTE;
        });

        $hasRemoteGroup = array_reduce($actual, function($carry,$item){
            return $carry || $item["value"]["shareType"] === Share::SHARE_TYPE_REMOTE_GROUP;
        });
        $this->assertTrue($hasRemote);
        $this->assertTrue($hasRemoteGroup);
    }

    public function test_search_when_contact_manager_has_CLOUD_and_searchfields(){

        $this->contactsManager->expects($this->once())
            ->method("search")->willReturn([
                [
                    "CLOUD"=>"someone@somecloud.com", 
                    "uid" =>"someCloudId",
                    "FN" => "someuser"
                ], 
                [
                    "CLOUD"=>"another_user@anothercloud", 
                    "uid" =>"someCloudId",
                    "FN" => "someone@somecloud.com"
                ]
            
            ]);

        $actual = $this->shareeSearchPluguin->search("someone@somecloud.com");
        $this->assertCount(2, $actual);
       
        $hasRemote = array_reduce($actual, function($carry,$item){
            return $carry || $item["value"]["shareType"] === Share::SHARE_TYPE_REMOTE;
        });

        $hasRemoteGroup = array_reduce($actual, function($carry, $item){
            return $carry || $item["value"]["shareType"] === Share::SHARE_TYPE_REMOTE_GROUP;
        });

        $this->assertTrue($hasRemote);
        $this->assertFalse($hasRemoteGroup);
    }
}
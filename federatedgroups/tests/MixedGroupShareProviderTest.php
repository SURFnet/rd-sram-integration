<?php
namespace OCA\FederatedGroups\Tests;

use OC\Group\Backend;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUser;
use OCP\Files\Folder;
use OCP\Share\IShare;
use OCP\Share;
use OCP\Share\IAttributes as IShareAttributes;
use phpDocumentor\Reflection\Types\This;
use function PHPUnit\Framework\any;
use function PHPUnit\Framework\once;
use \OCA\FederatedFileSharing\Address;

/**
 * @group DB
 */
class MixedGroupShareProviderTest extends \Test\TestCase {

	/**
	 * @var IDBConnection
	 */
	private $dbConnection;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

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

	const GROUP_NAME = "mixed_group";
	public function setUp(): void {
		parent::setUp();
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

		$this->mixGroupProvider = new MixedGroupShareProvider(
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
		$this->dbConnection->getQueryBuilder()->delete('share')->execute();
		$this->dbConnection->getQueryBuilder()->delete('filecache')->execute();

		parent::tearDown();
	}

	public function mockShareAttributes() {
		$formattedShareAttributes = 
			[
				[
					"scope" => "permissions",
					"key" => "download",
					"enabled" => true
				]
			];

		$shareAttributes = $this->createMock(IShareAttributes::class);
		$shareAttributes->method('toArray')->willReturn($formattedShareAttributes);
		$shareAttributes->method('getAttribute')->with('permissions', 'download')->willReturn(true);

		// send both IShare attributes class and expected json string
		return [$shareAttributes, \json_encode($formattedShareAttributes)];
	}

	private function getMockFileFolder() {
		$file = $this->createMock('\OCP\Files\File');
		$folder = $this->createMock('\OCP\Files\Folder');
		$parent = $this->createMock('\OCP\Files\Folder');

		$file->method('getMimeType')->willReturn('myMimeType');
		$folder->method('getMimeType')->willReturn('myFolderMimeType');

		$file->method('getPath')->willReturn('file');
		$folder->method('getPath')->willReturn('folder');

		$parent->method('getId')->willReturn(1);
		$folder->method('getId')->willReturn(2);
		$file->method('getId')->willReturn(3);

		$file->method('getParent')->willReturn($parent);
		$folder->method('getParent')->willReturn($parent);

		$cache = $this->createMock('OCP\Files\Cache\ICache');
		$cache->method('getNumericStorageId')->willReturn(100);
		$storage = $this->createMock('\OCP\Files\Storage');
		$storage->method('getId')->willReturn('storageId');
		$storage->method('getCache')->willReturn($cache);

		$file->method('getStorage')->willReturn($storage);
		$folder->method('getStorage')->willReturn($storage);

		return [$file, $folder];
	}

	public function getShareObject(){
		list($file, $folder) = $this->getMockFileFolder();
		list($shareAttributes, $shareAttributesReturnJson) = $this->mockShareAttributes();
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(Share::SHARE_TYPE_GROUP)
			->setSharedWith(self::GROUP_NAME)
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setAttributes($shareAttributes)
			->setNode($file)
			->setShareTime(new \DateTime('2023-05-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42);
		return [
			[$share]
		];
	}

	/**
	 * @dataProvider getShareObject
	 */
	public function test_create_with_group_share_type(Share\IShare $share){

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(3);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['initiator', $userFolder],
				['owner', $ownerFolder],
			]));

		$userFolder->method('getById')
			->with(3)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(3)
			->willReturn([$path]);

		$groupBackend = $this->createMock(Backend::class);
		$groupBackend->expects(self::once())
			->method("usersInGroup")->willReturn(
				[
					"user1#host1.co",
					"user2#host2.co",
					"local_user"
				]
			);

		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method("getBackend") ->willReturn($groupBackend);

		$this->groupManager =  $this->createMock(IGroupManager::class);
		$this->groupManager->expects($this->once())->method("get")
			->willReturn($group);

		$this->addressHandler->method("getLocalUserFederatedAddress")
			->willReturn(new Address("someone@somehost"));
		
		$this->groupNotifications->expects(self::exactly(2))
			->method("sendRemoteShare")
			->with($this->logicalOr(
				$this->equalTo(new Address(self::GROUP_NAME."@host1.co")),
				$this->equalTo(new Address(self::GROUP_NAME."@host2.co"))
			))
			->willReturn(true);
		
		$this->mixGroupProvider = new MixedGroupShareProvider(
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
		
		$result = $this->mixGroupProvider->create($share);
		$this->assertNotEmpty($result->getToken(), "token should not be empty or null in the result object");

	}


	public function getLinkShareObject(){
		list($file, $folder) = $this->getMockFileFolder();
		list($shareAttributes, $shareAttributesReturnJson) = $this->mockShareAttributes();
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(Share::SHARE_TYPE_LINK)
			->setSharedWith(self::GROUP_NAME)
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setAttributes($shareAttributes)
			->setNode($file)
			->setShareTime(new \DateTime('2023-05-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42);
		return [
			[$share]
		];
	}

	/**
	 * @dataProvider getLinkShareObject
	 */
	public function test_create_with_link_share_type(Share\IShare $share){

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(3);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['initiator', $userFolder],
				['owner', $ownerFolder],
			]));

		$userFolder->method('getById')
			->with(3)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(3)
			->willReturn([$path]);

		$groupBackend = $this->createMock(Backend::class);
		$groupBackend->expects($this->never())
			->method("usersInGroup");

		$group = $this->createMock(IGroup::class);
		$group->expects($this->never())
			->method("getBackend");

		$this->groupManager =  $this->createMock(IGroupManager::class);
		$this->groupManager->expects($this->never())->method("get");
		$this->addressHandler->method("getLocalUserFederatedAddress")
			->willReturn(new Address("someone@somehost"));
		
		$this->groupNotifications->expects($this->never())
			->method("sendRemoteShare");
		
		$this->mixGroupProvider = new MixedGroupShareProvider(
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
		
		$result = $this->mixGroupProvider->create($share);
		$this->assertNotNull($result, "result should not be empty or null in the result object");
		$this->assertNotNull($result->getId(), "share should be persisted inside db with a new Id");

	}

	public function testGetShareByToken() {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(Share::SHARE_TYPE_GROUP),
				'share_with'    => $qb->expr()->literal('shared_With'),
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
		$id = $qb->getLastInsertId();

		$file = $this->createMock(File::class);

		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->mixGroupProvider->getShareByToken('secrettoken');
		$this->assertEquals($id, $share->getId());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertSame('secrettoken', $share->getToken());
		$this->assertSame('shared_With', $share->getSharedWith());
		$this->assertSame('some_name', $share->getName());
	}

	public function testDelete(){
		$share = $this->createMock(IShare::class); 
		$share->expects($this->exactly(2))->method("getShareType")->willReturn(1);
		$share->expects($this->exactly(2))->method("getToken")->willReturn("t0ken");
		$share->expects($this->exactly(4))->method("getId")->willReturn(10);


		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(3);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['initiator', $userFolder],
				['owner', $ownerFolder],
			]));

		$userFolder->method('getById')
			->with(3)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(3)
			->willReturn([$path]);

		$groupBackend = $this->createMock(Backend::class);
		$groupBackend->expects(self::once())
			->method("usersInGroup")->willReturn(
				[
					"user1#host1.co",
					"user2#host2.co",
					"local_user"
				]
			);

		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method("getBackend") ->willReturn($groupBackend);

		$this->groupManager =  $this->createMock(IGroupManager::class);
		$this->groupManager->expects($this->once())->method("get")
			->willReturn($group);

		$this->addressHandler->method("getLocalUserFederatedAddress")
			->willReturn(new Address("someone@somehost"));
		
		$this->groupNotifications->expects(self::exactly(2))
			->method("sendRemoteUnshare")
			->with($this->logicalOr(
				$this->equalTo("host1.co"),
				$this->equalTo("host2.co")),10, "t0ken")
			->willReturn(true);
		
		$this->mixGroupProvider = new MixedGroupShareProvider(
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
		
		$result = $this->mixGroupProvider->delete($share);

	}

}
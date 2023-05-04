<?php
/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\OpenCloudMesh\Tests\FederatedFileSharing;

use OC\Files\Filesystem;
use OCA\FederatedFileSharing\Address;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
use OCA\OpenCloudMesh\FederatedFileSharing\FedGroupShareManager;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as ActivityManager;
use OCP\IUserManager;
use OCP\Notification\IAction;
use OCP\Notification\IManager as NotificationManager;
use OCP\Notification\INotification;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\Traits\UserTrait;

/**
 * Class FedGroupShareManagerTest
 *
 * @package OCA\OpenCloudMesh\FederatedFileSharing\Tests
 * @group DB
 */
class FedGroupShareManagerTest extends \Test\TestCase {
	use UserTrait;

	public const TEST_FILES_SHARING_API_USER1 = "test-share-user1";
	public const TEST_FILES_SHARING_API_USER2 = "test-share-user2";

	/** @var FederatedGroupShareProvider | \PHPUnit\Framework\MockObject\MockObject */
	private $federatedShareProvider;

	/** @var GroupNotifications | \PHPUnit\Framework\MockObject\MockObject */
	private $notifications;

	/** @var IUserManager | \PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var ActivityManager | \PHPUnit\Framework\MockObject\MockObject */
	private $activityManager;

	/** @var NotificationManager | \PHPUnit\Framework\MockObject\MockObject */
	private $notificationManager;

	/** @var FedGroupShareManager | \PHPUnit\Framework\MockObject\MockObject */
	private $fedShareManager;

	/** @var AddressHandler | \PHPUnit\Framework\MockObject\MockObject */
	private $addressHandler;

	/** @var Permissions | \PHPUnit\Framework\MockObject\MockObject */
	private $permissions;

	/** @var EventDispatcherInterface | \PHPUnit\Framework\MockObject\MockObject */
	private $eventDispatcher;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// reset backend
		\OC_User::clearBackends();
		\OC::$server->getGroupManager()->clearBackends();
	}

	protected function setUp(): void {
		parent::setUp();

		$classes = array_filter(get_declared_classes(), function($c) {
			return strpos($c, 'OpenCloudMesh');
		});
		error_log(json_encode($classes));
		
		$this->createUser(self::TEST_FILES_SHARING_API_USER1, self::TEST_FILES_SHARING_API_USER1);
		$this->createUser(self::TEST_FILES_SHARING_API_USER2, self::TEST_FILES_SHARING_API_USER2);

		//login as user1
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->federatedShareProvider = $this->getMockBuilder(
			FederatedGroupShareProvider::class
		)->disableOriginalConstructor()->getMock();
		$this->notifications = $this->getMockBuilder(GroupNotifications::class)
			->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$this->activityManager = $this->getMockBuilder(ActivityManager::class)
			->getMock();
		$this->notificationManager = $this->getMockBuilder(NotificationManager::class)
			->getMock();
		$this->addressHandler = $this->getMockBuilder(AddressHandler::class)
			->disableOriginalConstructor()->getMock();

		$this->permissions = $this->createMock(Permissions::class);

		$this->eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
			->getMock();

		$this->fedShareManager = $this->getMockBuilder(FedGroupShareManager::class)
			->setConstructorArgs(
				[
					$this->federatedShareProvider,
					$this->notifications,
					$this->userManager,
					$this->activityManager,
					$this->notificationManager,
					$this->addressHandler,
					$this->permissions,
					$this->eventDispatcher
				]
			)
			->setMethods(['getFile'])
			->getMock();
	}

	public function testCreateShare() {
		$shareWith = 'Bob';
		$owner = 'Alice';
		$ownerFederatedId = 'server2';
		$sharedByFederatedId = 'server3';
		$sharedBy = 'Steve';
		$ownerAddress = new Address("$owner@$ownerFederatedId");
		$sharedByAddress = new Address("$sharedBy@$sharedByFederatedId");
		$remoteId = 42;
		$name = 'file.ext';
		$token = 'idk';

		$event = $this->getMockBuilder(IEvent::class)->getMock();
		$event->method($this->anything())
			->willReturnSelf();
		$event->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);

		$action = $this->getMockBuilder(IAction::class)->getMock();
		$action->method($this->anything())->willReturnSelf();
		$notification = $this->getMockBuilder(INotification::class)->getMock();
		$notification->method('createAction')->willReturn($action);
		$notification->method($this->anything())
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$this->fedShareManager->createShare(
			$ownerAddress,
			$sharedByAddress,
			$shareWith,
			$remoteId,
			$name,
			$token
		);
	}

	public function testAcceptShare() {
		$this->fedShareManager->expects($this->once())
			->method('getFile')
			->willReturn(['/file','http://file']);

		$node = $this->getMockBuilder(\OCP\Files\File::class)
			->disableOriginalConstructor()->getMock();
		$node->expects($this->once())
			->method('getId')
			->willReturn(42);

		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);

		$event = $this->getMockBuilder(IEvent::class)->getMock();
		$event->method($this->anything())
			->willReturnSelf();
		$event->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);

		$this->fedShareManager->acceptShare($share);
	}

	public function testDeclineShare() {
		$this->fedShareManager->expects($this->once())
			->method('getFile')
			->willReturn(['/file','http://file']);

		$node = $this->getMockBuilder(\OCP\Files\File::class)
			->disableOriginalConstructor()->getMock();
		$node->expects($this->once())
			->method('getId')
			->willReturn(42);

		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);
		$share->method('getShareOwner')
			->willReturn('Alice');
		$share->method('getSharedBy')
			->willReturn('Bob');

		$this->notifications->expects($this->once())
			->method('sendDeclineShare');

		$event = $this->getMockBuilder(IEvent::class)->getMock();
		$event->method($this->anything())
			->willReturnSelf();
		$event->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);

		$this->fedShareManager->declineShare($share);
	}

	public function testUnshare() {
		$shareRow = [
			'id' => 42,
			'remote' => 'peer',
			'remote_id' => 142,
			'share_token' => 'abc',
			'password' => '',
			'name' => 'McGee',
			'owner' => 'Alice',
			'user' => 'Bob',
			'mountpoint' => '/mount/',
			'accepted' => 1
		];
		$this->federatedShareProvider
			->method('unshare')
			->willReturn($shareRow);

		$notification = $this->getMockBuilder(INotification::class)->getMock();
		$notification->method($this->anything())
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$event = $this->getMockBuilder(IEvent::class)->getMock();
		$event->method($this->anything())
			->willReturnSelf();
		$event->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);

		$this->fedShareManager->unshare($shareRow['id'], $shareRow['share_token']);
	}

	public function testReshareUndo() {
		$share = $this->getMockBuilder(IShare::class)
			->disableOriginalConstructor()->getMock();
		$this->federatedShareProvider->expects($this->once())
			->method('removeShareFromTable')
			->with($share);
		$this->fedShareManager->undoReshare($share);
	}

	public function testIsFederatedReShare() {
		$shareInitiator = 'user';
		$share = $this->getMockBuilder(IShare::class)
			->disableOriginalConstructor()->getMock();
		$share->expects($this->any())
			->method('getSharedBy')
			->willReturn($shareInitiator);

		$nodeMock = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()->getMock();
		$share->expects($this->once())
			->method('getNode')
			->willReturn($nodeMock);

		$otherShare = $this->getMockBuilder(IShare::class)
			->disableOriginalConstructor()->getMock();
		$otherShare->expects($this->any())
			->method('getSharedWith')
			->willReturn($shareInitiator);

		$this->federatedShareProvider->expects($this->once())
			->method('getSharesByPath')
			->willReturn([$share, $otherShare]);

		$isFederatedShared = $this->fedShareManager->isFederatedReShare($share);
		$this->assertEquals(
			true,
			$isFederatedShared
		);
	}

	public static function tearDownAfterClass(): void {
		// cleanup users
		$user = \OC::$server->getUserManager()->get(self::TEST_FILES_SHARING_API_USER1);
		if ($user !== null) {
			$user->delete();
		}
		$user = \OC::$server->getUserManager()->get(self::TEST_FILES_SHARING_API_USER2);
		if ($user !== null) {
			$user->delete();
		}

		\OC_Util::tearDownFS();
		\OC_User::setUserId('');
		Filesystem::tearDown();

		// reset backend
		\OC_User::clearBackends();
		\OC_User::useBackend('database');
		\OC::$server->getGroupManager()->clearBackends();
		\OC::$server->getGroupManager()->addBackend(new \OC_Group_Database());

		parent::tearDownAfterClass();
	}

	/**
	 * @param string $user
	 */
	protected static function loginHelper($user) {
		self::resetStorage();

		\OC_Util::tearDownFS();
		\OC::$server->getUserSession()->setUser(null);
		\OC\Files\Filesystem::tearDown();
		\OC::$server->getUserSession()->login($user, $user);
		\OC::$server->getUserFolder($user);

		\OC_Util::setupFS($user);
	}

	/**
	 * reset init status for the share storage
	 */
	protected static function resetStorage() {
		$storage = new \ReflectionClass('\OCA\Files_Sharing\SharedStorage');
		$isInitialized = $storage->getProperty('initialized');
		$isInitialized->setAccessible(true);
		$isInitialized->setValue($storage, false);
		$isInitialized->setAccessible(false);
	}
}

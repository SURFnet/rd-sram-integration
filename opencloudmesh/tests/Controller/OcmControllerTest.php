<?php
/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 * @author Yashar PM <yashar@pondersource.com>
 *
 * @copyright Copyright (c) 2023, SURF
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.

 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenCloudMesh\Tests\Controller;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
use OCA\OpenCloudMesh\FederatedFileSharing\FedGroupShareManager;
use OCA\OpenCloudMesh\FederatedFileSharing\FedUserShareManager;
use OCA\OpenCloudMesh\Controller\OcmController;
use OCA\FederatedFileSharing\Middleware\OcmMiddleware;
use OCP\Share\Exceptions\ShareNotFound;
use OCA\FederatedFileSharing\Ocm\Exception\BadRequestException;
use OCA\FederatedFileSharing\Ocm\Exception\ForbiddenException;
use OCA\FederatedFileSharing\Ocm\Exception\NotImplementedException;
use OCA\FederatedFileSharing\Ocm\Notification\FileNotification;
use OCA\OpenCloudMesh\Tests\FederatedFileSharing\TestCase;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\IShare;

/**
 * Class OcmControllerTest
 *
 * @package OCA\OpenCloudMesh\Tests
 * @group DB
 */
class OcmControllerTest extends TestCase {
	/**
	 * @var IRequest | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * @var OcmMiddleware | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $ocmMiddleware;

	/**
	 * @var IURLGenerator | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $urlGenerator;

	/**
	 * @var IAppManager | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $appManager;

	/**
	 * @var IUserManager | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $userManager;

	/**
	 * @var AddressHandler | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $addressHandler;

	/**
	 * @var FedGroupShareManager | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $fedGroupShareManager;

	/**
	 * @var FedUserShareManager | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $fedUserShareManager;

	/**
	 * @var ILogger | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $logger;

	/**
	 * @var IConfig | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $config;

	/**
	 * @var OcmController
	 */
	private $ocmController;

	/**
	 * @var string
	 */
	private $shareToken = 'abc';

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->ocmMiddleware = $this->createMock(OcmMiddleware::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->fedGroupShareManager = $this->createMock(FedGroupShareManager::class);
		$this->fedUserShareManager = $this->createMock(FedUserShareManager::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->config = $this->createMock(IConfig::class);

		$this->ocmController = new OcmController(
			'federatedfilesharing',
			$this->request,
			$this->ocmMiddleware,
			$this->urlGenerator,
			$this->userManager,
			$this->addressHandler,
			$this->fedGroupShareManager,
			$this->fedUserShareManager,
			$this->logger,
			$this->config
		);
	}

	public function testShareIsNotCreatedWhenSharingIsDisabled() {
		$this->ocmMiddleware->method('assertIncomingSharingEnabled')
			->willThrowException(new NotImplementedException());
		$response = $this->ocmController->createShare(
			'bob@localhost',
			'example.txt',
			'just a file',
			'70',
			null,
			'incognito',
			'sender@remote',
			'some sender',
			'user',
			FileNotification::RESOURCE_TYPE_FILE,
			[
				'options' => [
					'sharedSecret' => ''
				]
			]
		);
		$this->assertEquals(Http::STATUS_NOT_IMPLEMENTED, $response->getStatus());
	}

	public function testCreateShareWithMissingParam() {
		$response = $this->ocmController->createShare(
			'bob@localhost',
			'example.txt',
			'just a file',
			'70',
			null,
			'incognito',
			'sender@remote',
			'some sender',
			'user',
			FileNotification::RESOURCE_TYPE_FILE,
			[
				'options' => [
					'sharedSecret' => ''
				]
			]
		);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateShareForNotExistingUser() {
		$this->fedGroupShareManager->expects($this->once())
			->method('localShareWithExists')
			->with('scientists')
			->willReturn(false);
		$response = $this->ocmController->createShare(
			'scientists@localhost',
			'example.txt',
			'just a file',
			'70',
			'steve@another',
			'incognito',
			'sender@remote',
			'some sender',
			'user',
			FileNotification::RESOURCE_TYPE_FILE,
			[
				'name' => 'webdav',
				'options' => [
					'sharedSecret' => ''
				]
			]
		);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateShareException() {
		$this->fedGroupShareManager->expects($this->once())
			->method('localShareWithExists')
			->with('scientists')
			->willReturn(true);

		$this->fedGroupShareManager->expects($this->once())
			->method('createShare')
			->willThrowException(new \Exception('blocked by test'));

		$response = $this->ocmController->createShare(
			'scientists@localhost',
			'example.txt',
			'just a file',
			'70',
			'steve@another',
			'incognito',
			'sender@remote',
			'some sender',
			'group',
			FileNotification::RESOURCE_TYPE_FILE,
			[
				'name' => 'webdav',
				'options' => [
					'sharedSecret' => ''
				]
			]
		);
		$this->assertEquals(
			Http::STATUS_INTERNAL_SERVER_ERROR,
			$response->getStatus()
		);
	}

	public function testCreateShareSuccess() {
		$this->fedGroupShareManager->expects($this->once())
			->method('localShareWithExists')
			->with('scientists')
			->willReturn(true);

		$this->fedGroupShareManager->expects($this->once())
			->method('createShare')
			->willReturn(null);

		$response = $this->ocmController->createShare(
			'scientists@localhost',
			'example.txt',
			'just a file',
			'70',
			'steve@another',
			'incognito',
			'sender@remote',
			'some sender',
			'user',
			FileNotification::RESOURCE_TYPE_FILE,
			[
				'name' => 'webdav',
				'options' => [
					'sharedSecret' => ''
				]
			]
		);
		$this->assertEquals(
			Http::STATUS_CREATED,
			$response->getStatus()
		);
	}

	public function testProcessNotificationWithMissingParam() {
		$this->ocmMiddleware->expects($this->once())
			->method('assertNotNull')
			->willThrowException(new BadRequestException());
		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_ACCEPTED,
			FileNotification::RESOURCE_TYPE_FILE,
			null,
			[]
		);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testProcessUnknownFileNotificationType() {
		$response = $this->ocmController->processNotification(
			'something strange',
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => $this->shareToken
			]
		);
		$this->assertEquals(Http::STATUS_NOT_IMPLEMENTED, $response->getStatus());
	}

	public function testProcessAcceptShareNotificationForInvalidShare() {
		$shareMock = $this->getValidShareMock($this->shareToken);
		$this->fedGroupShareManager->expects($this->once())
			->method('getShareById')
			->willReturn($shareMock);
		$this->fedGroupShareManager->expects($this->once())
			->method('isOutgoingServer2serverShareEnabled')
			->willReturn(true);

		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_ACCEPTED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => "broken{$this->shareToken}"
			]
		);
		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testProcessAcceptShareSuccess() {
		$shareMock = $this->getValidShareMock($this->shareToken);
		$this->fedGroupShareManager->expects($this->once())
			->method('getShareById')
			->willReturn($shareMock);
		$this->fedGroupShareManager->expects($this->once())
			->method('isOutgoingServer2serverShareEnabled')
			->willReturn(true);

		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_ACCEPTED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => $this->shareToken
			]
		);
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testProcessDeclineShareNotificationForInvalidShare() {
		$shareMock = $this->getValidShareMock($this->shareToken);
		$this->fedGroupShareManager->expects($this->once())
			->method('getShareById')
			->willReturn($shareMock);
		$this->fedGroupShareManager->expects($this->once())
			->method('isOutgoingServer2serverShareEnabled')
			->willReturn(true);

		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_DECLINED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => "broken{$this->shareToken}"
			]
		);
		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testProcessDeclineShareSuccess() {
		$shareMock = $this->getValidShareMock($this->shareToken);
		$this->fedGroupShareManager->expects($this->once())
			->method('getShareById')
			->willReturn($shareMock);
		$this->fedGroupShareManager->expects($this->once())
			->method('isOutgoingServer2serverShareEnabled')
			->willReturn(true);

		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_DECLINED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => $this->shareToken
			]
		);
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testProcessUnshareNotificationSuccessfully(){
		$this->fedUserShareManager->expects($this->never())
		->method("unshare"); 
		$this->fedGroupShareManager->expects($this->once())
			->method('unshare');
		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_UNSHARED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => $this->shareToken
			]
		);
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testProcessUnshareNotificationForRemoteUserSharedFile(){
		$this->fedUserShareManager->expects($this->once())
			->method("unshare"); 
		$this->fedGroupShareManager->expects($this->once())
			->method('unshare')->will($this->throwException(new ShareNotFound()));
		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_UNSHARED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => $this->shareToken
			]
		);
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testProcessUnshareNotificationforWrongProviderId(){
		$this->fedUserShareManager->expects($this->once())
			->method("unshare")->will($this->throwException(new ShareNotFound())); 
		$this->fedGroupShareManager->expects($this->once())
			->method('unshare')->will($this->throwException(new ShareNotFound()));
		$response = $this->ocmController->processNotification(
			FileNotification::NOTIFICATION_TYPE_SHARE_UNSHARED,
			FileNotification::RESOURCE_TYPE_FILE,
			'90',
			[
				'sharedSecret' => $this->shareToken
			]
		);
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	protected function getValidShareMock($token) {
		$share = $this->createMock(IShare::class);
		$share->expects($this->any())
			->method('getToken')
			->willReturn($token);
		$share->expects($this->any())
			->method('getShareType')
			->willReturn(FederatedGroupShareProvider::SHARE_TYPE_REMOTE_GROUP);
		return $share;
	}
}

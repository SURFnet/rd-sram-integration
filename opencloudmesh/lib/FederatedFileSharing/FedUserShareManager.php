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

namespace OCA\OpenCloudMesh\FederatedFileSharing;

use OCA\OpenCloudMesh\FederatedFileSharing\AbstractFedShareManager;
use OCA\FederatedFileSharing\Address;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\OpenCloudMesh\FederatedUserShareProvider;
use OCA\Files_Sharing\Activity;
use OCP\Activity\IManager as ActivityManager;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCP\Share\Exceptions\ShareNotFound;

/**
 * Class FedUserShareManager holds the share logic
 *
 * @package OCA\OpenCloudMesh\FederatedFileSharing
 */
class FedUserShareManager extends AbstractFedShareManager {
	/**
	 * FedShareManager constructor.
	 *
	 * @param FederatedUserShareProvider $federatedUserShareProvider
	 * @param UserNotifications $notifications
	 * @param IUserManager $userManager
	 * @param ActivityManager $activityManager
	 * @param NotificationManager $notificationManager
	 * @param AddressHandler $addressHandler
	 * @param Permissions $permissions
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct(
		FederatedUserShareProvider $federatedUserShareProvider,
		UserNotifications $notifications,
		IUserManager $userManager,
		ActivityManager $activityManager,
		NotificationManager $notificationManager,
		AddressHandler $addressHandler,
		Permissions $permissions,
		EventDispatcherInterface $eventDispatcher
	) {
		parent::__construct(
			$federatedUserShareProvider,
			$notifications,
			$userManager,
			$activityManager,
			$notificationManager,
			$addressHandler,
			$permissions,
			$eventDispatcher
		);
	}

	public function isSupportedShareType($shareType) {
		// TODO: make it a constant
		return $shareType === 'user';
	}

	public function localShareWithExists($localShareWith) {
		return $this->userManager->userExists($localShareWith);
	}
}

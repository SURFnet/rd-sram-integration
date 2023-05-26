<?php
/**
 * @author Yashar PM <yashar@pondersource.com>
 * @author Michiel de Jong <michiel@pondersource.com>
 *
 * @copyright Copyright (c) 2022, SURF
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

namespace OCA\OpenCloudMesh\FederatedFileSharing;

use OCA\OpenCloudMesh\FederatedFileSharing\AbstractFedShareManager;
use OCA\FederatedFileSharing\Address;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
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
 * Class FedGroupShareManager holds the share logic
 *
 * @package OCA\OpenCloudMesh\FederatedFileSharing
 */
class FedGroupShareManager extends AbstractFedShareManager {
	/**
	 * FedShareManager constructor.
	 *
	 * @param FederatedGroupShareProvider $federatedGroupShareProvider
	 * @param GroupNotifications $notifications
	 * @param IUserManager $userManager
	 * @param ActivityManager $activityManager
	 * @param NotificationManager $notificationManager
	 * @param AddressHandler $addressHandler
	 * @param Permissions $permissions
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct(
		FederatedGroupShareProvider $federatedGroupShareProvider,
		GroupNotifications $notifications,
		IUserManager $userManager,
		ActivityManager $activityManager,
		NotificationManager $notificationManager,
		AddressHandler $addressHandler,
		Permissions $permissions,
		EventDispatcherInterface $eventDispatcher
	) {
		parent::__construct(
			$federatedGroupShareProvider,
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
		return $shareType === 'group';
	}

	public function localShareWithExists($localShareWith) {
		return \OC::$server->getGroupManager()->groupExists($localShareWith);
	}
}

<?php
/**
 * @author Yashar PM <yashar@pondersource.com>
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

namespace OCA\OpenCloudMesh\Files_Sharing;

use OC\Files\Filesystem;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

class Hooks {
	/**
	 * @var EventDispatcher
	 */
	private $eventDispatcher;

	/**
	 * @var \OCA\OpenCloudMesh\Files_Sharing\External\Manager
	 */
	private $externalManager;

	/**
	 * Hooks constructor.
	 *
	 * @param EventDispatcher $eventDispatcher
	 */
	public function __construct(
		EventDispatcher $eventDispatcher
	) {
		$this->eventDispatcher = $eventDispatcher;

		$this->externalManager = new \OCA\OpenCloudMesh\Files_Sharing\External\Manager(
			\OC::$server->getDatabaseConnection(),
			\OC\Files\Filesystem::getMountManager(),
			\OC\Files\Filesystem::getLoader(),
			\OC::$server->getNotificationManager(),
			\OC::$server->getEventDispatcher(),
			\OC::$server->getUserManager(),
			\OC::$server->getGroupManager(),
			null
		);
	}

	public static function deleteUser($params) {
		$this->externalManager->removeUserShares($params['uid']);
	}

	public function registerListeners() {
		$this->eventDispatcher->addListener(
			'group.postRemoveUser',
			function (GenericEvent $event) {
				$user = $event->getArgument('user');
				$group = $event->getSubject();
				$this->externalManager->userRemovedFromGroup($user->getUID(), $group->getGID());
			}
		);

		// $this->eventDispatcher->addListener(
		// 	'group.postDelete',
		// 	function (GenericEvent $event) {
		// 		$group = $event->getSubject();
		// 		// Group deleted
		// 	}
		// );
	}
}

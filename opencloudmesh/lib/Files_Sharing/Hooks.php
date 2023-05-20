<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCA\OpenCloudMesh\Files_Sharing;

use OC\Files\Filesystem;
use OCP\IURLGenerator;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcher;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCP\Activity\IManager as ActivityManager;

class Hooks {
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var IUserSession|null
	 */
	private $userSession;

	/**
	 * @var EventDispatcher
	 */
	private $eventDispatcher;

	/**
	 * @var \OCP\Share\IManager
	 */
	private $shareManager;

	/**
	 * @var ActivityManager
	 */
	private $activityManager;

	/**
	 * @var \OCA\OpenCloudMesh\Files_Sharing\External\Manager
	 */
	private $externalManager;

	/**
	 * Hooks constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IURLGenerator $urlGenerator
	 * @param EventDispatcher $eventDispatcher
	 * @param \OCP\Share\IManager $shareManager
	 * @param ActivityManager $activityManager
	 * @param IUserSession|null $userSession
	 */
	public function __construct(
		IRootFolder $rootFolder,
		IUrlGenerator $urlGenerator,
		EventDispatcher $eventDispatcher,
		\OCP\Share\IManager $shareManager,
		ActivityManager $activityManager,
		$userSession
	) {
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
		$this->urlGenerator = $urlGenerator;
		$this->eventDispatcher = $eventDispatcher;
		$this->shareManager = $shareManager;
		$this->activityManager = $activityManager;

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

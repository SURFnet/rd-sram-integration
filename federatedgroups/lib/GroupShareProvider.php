<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author phisch <git@philippschaffrath.de>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Piotr Mrowczynski <piotr@owncloud.com>
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
namespace OCA\FederatedGroups;

use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OC\Share20\Share;
use OC\Share20\DefaultShareProvider;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\TokenHandler;

use OCP\Files\File;
use OCP\Share\IAttributes;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IDBConnection;
use OCA\FederatedFileSharing;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * Class GroupShareProvider
 *
 * @package OC\Share20
 */
class GroupShareProvider extends FederatedShareProvider implements IShareProvider {
	// For representing foreign group members
	// e.g. 'marie#oc2.docker'
	public const SEPARATOR = '#';

	/** @var IGroupManager */
	private $groupManager;

	/** @var FederatedShareProvider */
	private $federatedProvider;

	/**
	 * DefaultShareProvider constructor.
	 *
	 * @param IDBConnection $connection
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IRootFolder $rootFolder
	 */
	public function __construct(
		IDBConnection $connection,
		EventDispatcherInterface $eventDispatcher,
		AddressHandler $addressHandler,
		Notifications $notifications,
		TokenHandler $tokenHandler,
		IL10N $l10n,
		ILogger $logger,
		IRootFolder $rootFolder,
		IConfig $config,
		IUserManager $userManager,
		IGroupManager $groupManager
		/*IDBConnection $connection,
		IUserManager $userManager,
		IGroupManager $groupManager,
		AddressHandler $addressHandler,
		Notifications $notifications,
		TokenHandler $tokenHandler,
		IRootFolder $rootFolder,
		EventDispatcherInterface $eventDispatcher,
		IL10N $l10n,
		ILogger $logger,
		IConfig $config*/
	) {
		parent::__construct(
			 $connection,
		 $eventDispatcher,
		 $addressHandler,
		 $notifications,
		 $tokenHandler,
		 $l10n,
		 $logger,
		 $rootFolder,
		 $config,
		 $userManager
		);
		// $this->dbConn = $connection;
		$this->groupManager = $groupManager;
    $this->federatedProvider = new FederatedShareProvider(
			$connection,
			$eventDispatcher,
			$addressHandler,
			$notifications,
			$tokenHandler,
			$l10n,
			$logger,
			$rootFolder,
			$config,
			$userManager
		);
		// error_log("FederatedGroups GroupShareProvider!");
	}

	/**
	 * Share a path
	 *
	 * @param \OCP\Share\IShare $share
	 * @return \OCP\Share\IShare The share object
	 * @throws ShareNotFound
	 * @throws InvalidArgumentException if the share validation failed
	 * @throws \Exception
	 */
	public function create(\OCP\Share\IShare $share) {
		// error_log("GroupShareProvider create calling parent");
		// Create group share locally
		$created = parent::create($share);
		// Send OCM invites to remote group members
		error_log("Sending OCM invites");
		error_log($share->getSharedWith());
		$group = $this->groupManager->get($share->getSharedWith());
		// error_log("Got group");
		$backend = $group->getBackend();
		// error_log("Got backend");
		$recipients = $backend->usersInGroup($share->getSharedWith());
		// error_log("Got recipients");
		error_log(var_export($recipients, true));
		foreach($recipients as $k => $v) {
			$parts = explode(self::SEPARATOR, $v);
			if (count($parts) == 2) {
				error_log("Sending OCM invite: " . $parts[0] . " at " . $parts[1]);
				// $this->sendOcmInvite($share->getSharedBy(), $share->getShareOwner(), $share->getSharedWith(), $share->getNode()->getName());
			} else {
				error_log("Local user: $v");
			}
		}
	}
	public function getAllSharedWith($userId, $node){
		error_log("you `getAllSharedWith` me on GroupShareProvider...");
		return parent::getAllSharedWith($userId, $node);
	}

	public function getSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0){
		error_log("you `getSharedWith` on GroupShareProvider...");
		return parent::getSharedWith($userId, $shareType, $node, $limit, $offset);
	}

	
}

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

namespace OCA\OpenCloudMesh;

use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OC\Share20\Share;
use OC\Share20\DefaultShareProvider;
use OCA\FederatedFileSharing\Address;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\OpenCloudMesh\FederatedFileSharing\AbstractFederatedShareProvider;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\OpenCloudMesh\FederatedFileSharing\UserNotifications;
use OCA\OpenCloudMesh\Files_Sharing\External\Manager;
use OCP\Files\File;
use OCP\Share\IAttributes;
use OCP\Share\IProviderFactory;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IDBConnection;
use OCA\FederatedFileSharing;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class FederatedUserShareProvider
 *
 * @package OC\Share20
 */
class FederatedUserShareProvider extends AbstractFederatedShareProvider {
	const SHARE_TYPE_REMOTE = 6;

	/**
	 * FederatedGroupShareProvider constructor.
	 *
	 * @param IDBConnection $connection
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param AddressHandler $addressHandler
	 * @param UserNotifications $notifications
	 * @param TokenHandler $tokenHandler
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param IRootFolder $rootFolder
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IProviderFactory $shareProviderFactory
	 * @param callable $externalManagerProvider
	 */
	public function __construct(
		IDBConnection $connection,
		EventDispatcherInterface $eventDispatcher,
		AddressHandler $addressHandler,
		UserNotifications $notifications,
		TokenHandler $tokenHandler,
		IL10N $l10n,
		ILogger $logger,
		IRootFolder $rootFolder,
		IConfig $config,
		IUserManager $userManager,
		IProviderFactory $shareProviderFactory,
		callable $externalManagerProvider
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
			'share_external',
			self::SHARE_TYPE_REMOTE,
			$userManager,
			$shareProviderFactory,
			$externalManagerProvider
		);
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocFederatedSharing';
	}
}

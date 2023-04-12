<?php

// Copyright (c) 2018, ownCloud GmbH
// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\OpenCloudMesh;

use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OC\Share20\Share;
use OC\Share20\DefaultShareProvider;
use OCA\FederatedFileSharing\Address;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\AbstractFederatedShareProvider;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCA\FederatedFileSharing\Ocm\Permissions;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\OpenCloudMesh\FederatedFileSharing\Notifications;
use OCA\OpenCloudMesh\Files_Sharing\External\Manager;
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
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IDBConnection;
use OCA\FederatedFileSharing;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class FederatedGroupShareProvider
 *
 * @package OC\Share20
 */
class FederatedGroupShareProvider extends AbstractFederatedShareProvider {
	const SHARE_TYPE_REMOTE_GROUP = 7;

	/**
	 * FederatedGroupShareProvider constructor.
	 *
	 * @param IDBConnection $connection
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param AddressHandler $addressHandler
	 * @param Notifications $notifications
	 * @param TokenHandler $tokenHandler
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param IRootFolder $rootFolder
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param callable $externalManagerProvider
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
			'share_external_group',
			self::SHARE_TYPE_REMOTE_GROUP,
			$userManager,
			$externalManagerProvider
		);
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocGroupFederatedSharing';
	}
}

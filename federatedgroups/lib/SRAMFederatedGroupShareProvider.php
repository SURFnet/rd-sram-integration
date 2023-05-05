<?php

// Copyright (c) 2018, ownCloud GmbH
// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\FederatedGroups;

use OC\Share20\Exception\InvalidShare;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\OpenCloudMesh\FederatedGroupShareProvider;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\OpenCloudMesh\FederatedFileSharing\GroupNotifications;
use OCP\Share\IProviderFactory;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IDBConnection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class SRAMFederatedGroupShareProvider
 *
 * @package OCA\FederatedGroups
 */
class SRAMFederatedGroupShareProvider extends FederatedGroupShareProvider {

	/** @var IDBConnection */
	private $dbConnection;

	/**
	 * FederatedGroupShareProvider constructor.
	 *
	 * @param IDBConnection $connection
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param AddressHandler $addressHandler
	 * @param GroupNotifications $notifications
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
		GroupNotifications $notifications,
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
			$userManager,
			$shareProviderFactory,
			$externalManagerProvider
		);
		$this->dbConnection = $connection;
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocGroupFederatedSharing';
	}

	/**
	 * @inheritdoc
	 */
	public function getShareById($id, $recipientId = null) {
		if (!ctype_digit($id)) {
			// share id is defined as a field of type integer
			// if someone calls the API asking for a share id like "abc" or "42.1"
			// then there is no point trying to query the database,
			// and, depending on the database, the query may throw an exception
			// with a message like "invalid input syntax for type integer"
			// So throw ShareNotFound now.
			throw new ShareNotFound();
		}
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from("share")
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP)),
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE_GROUP))
				)
			);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		try {
			$share = $this->createShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		return $share;
	}

}

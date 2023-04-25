<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

use OCA\OpenCloudMesh\FederatedFileSharing\AbstractNotifications;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use GuzzleHttp\Exception\ClientException;

class GroupNotifications extends AbstractNotifications {
	/**
	 * @param AddressHandler $addressHandler
	 * @param IClientService $httpClientService
	 * @param DiscoveryManager $discoveryManager
	 * @param IJobList $jobList
	 * @param IConfig $config
	 */
	public function __construct(
		AddressHandler $addressHandler,
		IClientService $httpClientService,
		DiscoveryManager $discoveryManager,
		NotificationManager $notificationManager,
		IJobList $jobList,
		IConfig $config
	) {
		parent::__construct(
			$addressHandler,
			$httpClientService,
			$discoveryManager,
			$notificationManager,
			$jobList,
			$config,
			'user'
		);
	}
}

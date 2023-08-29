<?php
/**
 * @author Yashar PM <yashar@pondersource.com>
 * @author Michiel de Jong <michiel@pondersource.com>
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

 namespace OCA\OpenCloudMesh\FederatedFileSharing;

use OCA\OpenCloudMesh\FederatedFileSharing\AbstractNotifications;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\Ocm\NotificationManager;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use GuzzleHttp\Exception\ClientException;

class UserNotifications extends AbstractNotifications {
	/**
	 * @param AddressHandler $addressHandler
	 * @param IClientService $httpClientService
	 * @param DiscoveryManager $discoveryManager
	 * @param IJobList $jobList
	 * @param IConfig $config
 	 * @param ILogger $logger
	 */
	public function __construct(
		AddressHandler $addressHandler,
		IClientService $httpClientService,
		DiscoveryManager $discoveryManager,
		NotificationManager $notificationManager,
		IJobList $jobList,
		IConfig $config,
		ILogger $logger
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

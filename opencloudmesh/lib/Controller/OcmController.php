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

namespace OCA\OpenCloudMesh\Controller;

use OCA\FederatedFileSharing\Address;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Middleware\OcmMiddleware;
use OCA\FederatedFileSharing\Ocm\Exception\BadRequestException;
use OCA\FederatedFileSharing\Ocm\Exception\NotImplementedException;
use OCA\FederatedFileSharing\Ocm\Notification\FileNotification;
use OCP\AppFramework\Http\JSONResponse;
use OCA\FederatedFileSharing\FedShareManager;
use OCA\FederatedFileSharing\Ocm\Exception\OcmException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;

/**
 * Class OcmController
 *
 * @package OCA\OpenCloudMesh\Controller
 */
class OcmController extends \OCA\OpenCloudMesh\FederatedFileSharing\Controller\OcmController {
	/**
	 * OcmController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param OcmMiddleware $ocmMiddleware
	 * @param IURLGenerator $urlGenerator
	 * @param IUserManager $userManager
	 * @param AddressHandler $addressHandler
	 * @param FedShareManager $fedShareManager
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(
		$appName,
		IRequest $request,
		OcmMiddleware $ocmMiddleware,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		AddressHandler $addressHandler,
		FedShareManager $fedShareManager,
		ILogger $logger,
		IConfig $config
	) {
		parent::__construct(...func_get_args());
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $shareWith identifier of the user or group
	 * 							to share the resource with
	 * @param string $name name of the shared resource
	 * @param string $description share description (optional)
	 * @param string $providerId Identifier of the resource at the provider side
	 * @param string $owner identifier of the user that owns the resource
	 * @param string $ownerDisplayName display name of the owner
	 * @param string $sender Provider specific identifier of the user that wants
	 *							to share the resource
	 * @param string $senderDisplayName Display name of the user that wants
	 * 									to share the resource
	 * @param string $shareType Share type ('user' is supported atm)
	 * @param string $resourceType only 'file' is supported atm
	 * @param array $protocol
	 * 		[
	 * 			'name' => (string) protocol name. Only 'webdav' is supported atm,
	 * 			'options' => [
	 * 				protocol specific options
	 * 				only `webdav` options are supported atm
	 * 				e.g. `uri`,	`access_token`, `password`, `permissions` etc.
	 *
	 * 				For backward compatibility the webdav protocol will use
	 * 				the 'sharedSecret" as username and password
	 * 			]
	 *
	 * @return JSONResponse
	 */
	public function createShare(
		$shareWith,
		$name,
		$description,
		$providerId,
		$owner,
		$ownerDisplayName,
		$sender,
		$senderDisplayName,
		$shareType,
		$resourceType,
		$protocol
	) {
		error_log("Our createShare!");
		error_log(var_export(func_get_args(), true));
		return parent::createShare(...func_get_args());
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $notificationType notification type (SHARE_REMOVED, etc)
	 * @param string $resourceType only 'file' is supported atm
	 * @param string $providerId Identifier of the resource at the provider side
	 * @param array $notification
	 * 		[
	 * 			optional additional parameters, depending on the notification
	 * 				and the resource type
	 * 		]
	 *
	 * @return JSONResponse
	 */
	public function processNotification(
		$notificationType,
		$resourceType,
		$providerId,
		$notification
	) {
		error_log("Our processNotification!");
		error_log(var_export(func_get_args(), true));
		return parent::processNotification(...func_get_args());
	}

}

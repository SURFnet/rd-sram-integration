<?php
/**
 * @author Michiel de Jong <michiel@pondersource.com>
 *
 * @copyright Copyright (c) 2023, SURF
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

namespace OCA\FederatedGroups\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Class ScimController
 *
 * @package OCA\FederatedGroups\Controller
 */
class ScimController extends Controller {
  public function __construct(
		$appName,
		IRequest $request
	) {
		parent::__construct($appName, $request);
		error_log("Federated Groups ScimController constructed");
	}

  /**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * EndPoint discovery
	 * Responds to /ocm-provider/ requests
 	 * @return array
	 */

  public function addUserToGroup() {
		// The app framework will parse JSON bodies of POST requests
		// if the Content-Type is set to application/json, and you can
		// we can then just write `addUserToGroup($Operations)` and it would work.
		// However, for PATCH requests the same thing does not work.
		// See https://github.com/pondersource/peppol-php/issues/133#issuecomment-1221297463
		// for a very similar discussion involving PUT requests to a Nextcloud server.
		$body = json_decode(file_get_contents('php://input'), true);
    error_log("scimming!");
		error_log(var_export($body['Operations'], true));
		if ($body['Operations'][0]['op'] == 'add' && $body['Operations'][0]['path'] == 'members') {
			new JSONResponse(
				[],
				Http::STATUS_CREATED
			);
		} else {
			new JSONResponse(
				[],
				Http::STATUS_BAD_REQUEST
			);
		}
  }
}
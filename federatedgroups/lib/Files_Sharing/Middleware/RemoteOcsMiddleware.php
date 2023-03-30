<?php
/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 * @author Yashar PourMohamad <yasharpm@gmail.com>
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

namespace OCA\FederatedGroups\Files_Sharing\Middleware;

use OCA\Files_Sharing\External\Manager;
use OCA\FederatedGroups\Files_Sharing\External\Manager as GroupManager;

/**
 * Checks whether the "sharing check" is enabled
 *
 * @package OCA\Files_Sharing\Middleware
 */
class RemoteOcsMiddleware implements IRemoteOcsMiddleware {
	/** @var Manager */
	private $externalManager;

	/** @var GroupManager */
	private $externalGroupManager;

	/***
	 * @param Manager $externalManager
	 * @param GroupManager $externalGroupManager
	 */
	public function __construct(
		Manager $externalManager,
        GroupManager $externalGroupManager
	) {
		$this->externalManager = $externalManager;
		$this->externalGroupManager = $externalGroupManager;
	}

	public function getAcceptedShares() {
		return \array_merge(
            $this->externalManager->getAcceptedShares(),
            $this->externalGroupManager->getAcceptedShares()
        );
	}

	public function getOpenShares() {
		return \array_merge(
            $this->externalManager->getOpenShares(),
            $this->externalGroupManager->getOpenShares()
        );
	}
}

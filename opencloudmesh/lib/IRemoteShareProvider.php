<?php
/**
 * @author Yashar PM <yashar@pondersource.com>
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

namespace OCA\OpenCloudMesh;

use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

interface IRemoteShareProvider extends IShareProvider {
    /**
	 * get remote share from the local table but exclude mounted link shares
	 *
	 * @param IShare $share
	 * @return array
	 * @throws ShareNotFound
	 */
    public function getShareFromLocalTable(IShare $share);
}
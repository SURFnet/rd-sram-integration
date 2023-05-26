<?php
/**
 * @author Michiel de Jong <michiel@pondersource.com>
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

namespace OCA\OpenCloudMesh\Files_Sharing\External;

use OCP\Files\Storage\IStorageFactory;
use OCP\IDBConnection;

class MountProvider extends AbstractMountProvider {
	public const STORAGE = '\OCA\OpenCloudMesh\Files_Sharing\External\Storage';

	/**
	 * @param \OCP\IDBConnection $connection
	 * @param callable $managerProvider due to setup order we need a callable that return the manager instead of the manager itself
	 */
	public function __construct(IDBConnection $connection, callable $managerProvider) {
		parent::__construct($connection, $managerProvider, self::STORAGE, 'share_external_group');
	}
}

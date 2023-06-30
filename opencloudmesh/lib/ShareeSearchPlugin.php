<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Michiel de Jong <michiel@pondersource.com>
 * @author Navid Shokri <navid@pondersource.com>
 * @author Reza Soltani <reza@pondersource.com>
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

use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IRemoteShareesSearch;
use OCP\Share;
use OCP\Contacts\IManager;
use OCP\Util\UserSearch;

class ShareeSearchPlugin implements IRemoteShareesSearch {
	protected $shareeEnumeration;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $userId = '';

	/** @var IManager */
	protected $contactsManager;

	/** @var UserSearch*/
	protected $userSearch;

	/** @var int */
	private $limit = 10;

	/** @var int */
	private $offset = 0;

	private $result = [];

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession, IManager $contactsManager, UserSearch $userSearch) {
		$this->config = $config;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->contactsManager = $contactsManager;
		$this->userSearch = $userSearch;
	}

	public function search($search) {
		// copied from https://github.com/owncloud/core/blob/v10.11.0/apps/files_sharing/lib/Controller/ShareesController.php#L385-L503
    	// just doubling up every result so it appears once with share type Share::SHARE_TYPE_REMOTE
		// and once with share type Share::SHARE_TYPE_REMOTE_GROUP

		// Fetch remote search properties from app config
		/**
		 * @var array $searchProperties
		 */
		$searchProperties = \explode(',', $this->config->getAppValue('dav', 'remote_search_properties', 'CLOUD,FN'));
		// Search in contacts
		$matchMode = $this->config->getSystemValue('accounts.enable_medial_search', true) === true
			? 'ANY'
			: 'START';
		$addressBookContacts = $this->contactsManager->search(
			$search,
			$searchProperties,
			[ 'matchMode' => $matchMode ],
			$this->limit,
			$this->offset
		);
		$foundRemoteById = false;
		foreach ($addressBookContacts as $contact) {
			if (isset($contact['isLocalSystemBook'])) {
				// We only want remote users
				continue;
			}
			if (!isset($contact['CLOUD'])) {
				// we need a cloud id to setup a remote share
				continue;
			}

			// we can have multiple cloud domains, always convert to an array
			$cloudIds = $contact['CLOUD'];
			if (!\is_array($cloudIds)) {
				$cloudIds = [$cloudIds];
			}

			$lowerSearch = \strtolower($search);
			foreach ($cloudIds as $cloudId) {
				list(, $serverUrl) = $this->splitUserRemote($cloudId);

				if (\strtolower($cloudId) === $lowerSearch) {
					$foundRemoteById = true;
					// Save this as an exact match and continue with next CLOUD
					$this->result['exact']['remotes'][] = [
						'label' => $contact['FN'],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $cloudId,
							'server' => $serverUrl,
						]];
					continue;
				}

				// CLOUD matching is done above
				unset($searchProperties[array_search('CLOUD',$searchProperties)]);
				foreach ($searchProperties as $property) {
					// do we even have this property for this contact/
					if (!isset($contact[$property])) {
						// Skip this property since our contact doesnt have it
						continue;
					}
					// check if we have a match
					$values = $contact[$property];
					if (!\is_array($values)) {
						$values = [$values];
					}
					foreach ($values as $value) {
						// check if we have an exact match
						if (\strtolower($value) === $lowerSearch) {
							$this->result['exact']['remotes'][] = [
								'label' => $contact['FN'],
								'value' => [
									'shareType' => Share::SHARE_TYPE_REMOTE,
									'shareWith' => $cloudId,
									'server' => $serverUrl,
								],
							];

							// Now skip to next CLOUD
							continue 3;
						}
					}
				}

				// If we get here, we didnt find an exact match, so add to other matches
				if ($this->userSearch->isSearchable($search)) {
					$this->result['remotes'][] = [
						'label' => $contact['FN'],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $cloudId,
							'server' => $serverUrl,
						],
					];
				}
			}
		}

		// remove the exact user results if we dont allow autocomplete
		if (!$this->shareeEnumeration) {
			$this->result['remotes'] = [];
		}
		
		if (!$foundRemoteById && \substr_count($search, '@') >= 1
			&& $this->offset === 0 && $this->userSearch->isSearchable($search)
			
			// if an exact local user is found, only keep the remote entry if
			// its domain does not match the trusted domains
			// (if it does, it is a user whose local login domain matches the ownCloud
			// instance domain)
			&& (empty($this->result['exact']['users'])
				|| !$this->isInstanceDomain($search))
		) {
			$this->result['exact']['remotes'][] = [
				'label' => "$search",
				'value' => [
					'shareType' => Share::SHARE_TYPE_REMOTE,
					'shareWith' => $search,
				],
			];
			$this->result['exact']['remotes'][] = [
				'label' => "$search",
				'value' => [
					'shareType' => Share::SHARE_TYPE_REMOTE_GROUP,
					'shareWith' => $search,
				],
			];
		}
		if (isset($this->result) && count($this->result) > 0 )
			return $this->result['exact']['remotes'];
		return [];
	}

	/**
	 * Checks whether the given target's domain part matches one of the server's
	 * trusted domain entries
	 *
	 * @param string $target target
	 * @return true if one match was found, false otherwise
	 */
	protected function isInstanceDomain($target) {
		if (\strpos($target, '/') !== false) {
			// not a proper email-like format with domain name
			return false;
		}
		$parts = \explode('@', $target);
		if (\count($parts) === 1) {
			// no "@" sign
			return false;
		}
		$domainName = $parts[\count($parts) - 1];
		$trustedDomains = $this->config->getSystemValue('trusted_domains', []);

		return \in_array($domainName, $trustedDomains, true);
	}

	/**
	 * split user and remote from federated cloud id
	 *
	 * @param string $address federated share address
	 * @return array [user, remoteURL]
	 * @throws \Exception
	 */
	private function splitUserRemote($address) {
		if (\strpos($address, '@') === false) {
			throw new \Exception('Invalid Federated Cloud ID');
		}

		// Find the first character that is not allowed in user names
		$id = \str_replace('\\', '/', $address);
		$posSlash = \strpos($id, '/');
		$posColon = \strpos($id, ':');

		if ($posSlash === false && $posColon === false) {
			$invalidPos = \strlen($id);
		} elseif ($posSlash === false) {
			$invalidPos = $posColon;
		} elseif ($posColon === false) {
			$invalidPos = $posSlash;
		} else {
			$invalidPos = \min($posSlash, $posColon);
		}

		// Find the last @ before $invalidPos
		$pos = $lastAtPos = 0;
		while ($lastAtPos !== false && $lastAtPos <= $invalidPos) {
			$pos = $lastAtPos;
			$lastAtPos = \strpos($id, '@', $pos + 1);
		}

		if ($pos !== false) {
			$user = \substr($id, 0, $pos);
			$remote = \substr($id, $pos + 1);
			$remote = $this->fixRemoteURL($remote);
			if (!empty($user) && !empty($remote)) {
				return [$user, $remote];
			}
		}

		throw new \Exception('Invalid Federated Cloud ID');
	}
	/**
	 * Strips away a potential file names and trailing slashes:
	 * - http://localhost
	 * - http://localhost/
	 * - http://localhost/index.php
	 * - http://localhost/index.php/s/{shareToken}
	 *
	 * all return: http://localhost
	 *
	 * @param string $remote
	 * @return string
	 */
	protected function fixRemoteURL($remote) {
		$remote = \str_replace('\\', '/', $remote);
		if ($fileNamePosition = \strpos($remote, '/index.php')) {
			$remote = \substr($remote, 0, $fileNamePosition);
		}
		$remote = \rtrim($remote, '/');

		return $remote;
	}
}

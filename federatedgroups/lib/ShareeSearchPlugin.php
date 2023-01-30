<?php

namespace OCA\FederatedGroups;

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

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession, IManager $contactsManager, UserSearch $userSearch) {
		error_log("constructing ShareeSearchPlugin");
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
		error_log("searching $search");
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
						],
						'label' => 'G_' . $contact['FN'],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE_GROUP,
							'shareWith' => $cloudId,
							'server' => $serverUrl,
						],
					];
					continue;
				}

				// CLOUD matching is done above
				unset($searchProperties['CLOUD']);
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
								'label' => 'G_' . $contact['FN'],
								'value' => [
									'shareType' => Share::SHARE_TYPE_REMOTE_GROUP,
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
						'label' => 'G_' . $contact['FN'],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE_GROUP,
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
		$this->result['exact']['remotes'] = [];
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
				'label' => $search,
				'value' => [
					'shareType' => Share::SHARE_TYPE_REMOTE,
					'shareWith' => $search,
				],
			];
			$this->result['exact']['remotes'][] = [
				'label' => 'G_' . $search,
				'value' => [
					'shareType' => Share::SHARE_TYPE_REMOTE_GROUP,
					'shareWith' => $search,
				],
			];
		}
		return $this->result['exact']['remotes'];
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
}

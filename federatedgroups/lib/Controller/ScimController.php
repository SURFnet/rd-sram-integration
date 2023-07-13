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

use Exception;
use OCA\FederatedGroups\helpers\IDomainHelper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\FederatedGroups\GroupManagerProxy;
use OCA\FederatedGroups\AppInfo\Application;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCP\ILogger;

/**
 * Class ScimController
 *
 * @package OCA\FederatedGroups\Controller
 */
class ScimController extends Controller {
    private GroupManagerProxy $groupManagerProxy;
    protected MixedGroupShareProvider $mixedGroupShareProvider;
    private ILogger $logger;
    private IDomainHelper $domainHelper;


    public function __construct(
        $appName,
        IRequest $request,
        GroupManagerProxy $groupManagerProxy,
        ILogger $logger,
        IDomainHelper $domainHelper
    ) {
        parent::__construct($appName, $request);
        $federatedGroupsApp = new Application();
        $this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
        $this->groupManagerProxy = $groupManagerProxy;
        $this->logger = $logger;
        $this->domainHelper = $domainHelper;
    }

    private function getNewDomainIfNeeded($newMember, $currentMembers) {
        $newMemberParts = explode("#", $newMember);
        if (count($newMemberParts) == 1)
            return null;

        if (count($newMemberParts) == 2) {
            $newDomain = $newMemberParts[1];
            if (str_contains($newDomain, $this->domainHelper->getOurDomain()))
                return null;
            // if we have a member with same domain, we have sent the invite before, so no need to send it again
            foreach ($currentMembers as $currentMember) {
                $currentMemberParts = explode("#", $currentMember);
                if (count($currentMemberParts) == 2) {
                    $currentMemberDomain = $currentMemberParts[1];
                    if ($currentMemberDomain == $newDomain)
                        return null;
                }
            }
            return $newDomain;
        }
    }

    private function handleUpdateGroup(string $groupId, $obj) {
        $group = $this->groupManagerProxy->get($groupId);

        if ($group === null) {
            throw new Exception("cannot find the given group " . $groupId);
        }
        $backend = $group->getBackend();

        $currentMembers =  $backend->usersInGroup($groupId);

        $newMembers     = [];
        foreach ($obj["members"] as $member) {
            $userIdParts = explode("@", $member["value"]);
            if (count($userIdParts) == 3) {
                $userIdParts = [$userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
            }
            if (count($userIdParts) != 2) {
                throw new Exception("cannot parse OCM user " . $member["value"]);
            }
            $newMember = $userIdParts[0];
            if ($userIdParts[1] !== $this->domainHelper->getOurDomain()) {
                $newMember .= "#" . $userIdParts[1];
            }
            $newMembers[] = $newMember;
        }
        foreach ($currentMembers as $currentMember) {
            if (!in_array($currentMember, $newMembers)) {
                $backend->removeFromGroup($currentMember, $groupId);
            }
        }

        foreach ($newMembers as $newMember) {
            if (!in_array($newMember, $currentMembers)) {
                $backend->addToGroup($newMember, $groupId);

                $newDomain = $this->getNewDomainIfNeeded($newMember, $currentMembers);
                if ($newDomain) {
                    $this->mixedGroupShareProvider->sendOcmInviteForExistingShares($newDomain, $groupId);
                }
            }
        }
    }


    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function createGroup($id, $members) {
        if (!$id) {
            return new JSONResponse(['status' => 'error', 'message' => "Missing param: id", 'data' => null], Http::STATUS_BAD_REQUEST);
        } else if (!is_array($members)) {
            return new JSONResponse(['status' => 'error', 'message' => "Missing param: members", 'data' => null], Http::STATUS_BAD_REQUEST);
        }

        $this->logger->info('Create Group ' . $id . ' with members: ' . print_r($members, true));

        $body = ["id" => $id, "members" => $members];

        $this->groupManagerProxy->createGroup($id);
        // if (!$this->groupManagerProxy->get($id)) {
        //     // $this->groupBackend->createGroup($id);
        // }

        try {
            $this->handleUpdateGroup($id, $body);
        } catch (\Exception $ex) {
            return new JSONResponse(['status' => 'error', 'message' => $ex->getMessage(), 'data' => null], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse(
            [
                'status'  => 'success',
                'message' => null,
                'data'    => $body
            ],
            Http::STATUS_CREATED
        );
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function updateGroup($groupId, $members) {
        if (!$groupId) {
            return new JSONResponse(['status' => 'error', 'message' => "Missing param: groupId", 'data' => null], Http::STATUS_BAD_REQUEST);
        } else if (!is_array($members)) {
            return new JSONResponse(['status' => 'error', 'message' => "Missing param: members", 'data' => null], Http::STATUS_BAD_REQUEST);
        }

        $this->logger->info('Update Group ' . $groupId . ' with members: ' . print_r($members, true));

        $body = ["members" => $members];

        try {
            $this->handleUpdateGroup($groupId, $body);
        } catch (\Exception $ex) {
            return new JSONResponse(['status' => 'error', 'message' => $ex->getMessage(), 'data' => null], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse(['status' => 'success', 'message' => null, 'data' => $body], Http::STATUS_OK);
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function deleteGroup($groupId) {
        $group = $this->groupManagerProxy->get(\urldecode($groupId));
        if ($group) {

            $this->logger->info('Delete Group ' . $groupId);

            $deleted = $group->delete();
            if ($deleted) {
                return new JSONResponse(['status' => 'success', 'message' => "Succesfully deleted group: {$groupId}", 'data' => null], Http::STATUS_NO_CONTENT);
            } else {
                return new JSONResponse(['status' => 'error', 'message' => "Falure in deleting group: {$groupId}", 'data' => null], Http::STATUS_BAD_REQUEST);
            }
        } else {
            return new JSONResponse(['status' => 'error', 'message' => "Could not find Group with the given identifier: {$groupId}", 'data' => null], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function getGroups() {
        $groups = [];
        $groupDataArr    = [];

        foreach ($this->groupManagerProxy->getBackends() as $backend) {
            array_push($groups, ...$backend->getGroups());
        }

        foreach ($groups as $groupId) {
            $groupDataArr[] = $this->handleGetGroupData($groupId);
        }

        return new JSONResponse(
            [
                'status'  => 'success',
                'message' => null,
                'data'    => ["totalResults" => count($groups), "Resources" => $groupDataArr]
            ],
            Http::STATUS_OK
        );
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function getGroup($groupId) {
        $this->logger->info('Get Group ' . $groupId);

        $groupData = $this->handleGetGroupData($groupId);
        if ($groupData) {
            return new JSONResponse(
                [
                    'status'  => 'success',
                    'message' => null,
                    'data'    => $groupData
                ],
                Http::STATUS_OK
            );
        } else {
            return new JSONResponse(
                [
                    'status'  => 'error',
                    'message' => "Could not find Group with the given identifier: {$groupId}",
                    'data'    => null
                ],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    private function handleGetGroupData($groupId) {
        $group = $this->groupManagerProxy->get($groupId);
        if ($group) {
            $id = $group->getGID();
            $displayName = $group->getDisplayName();
            $usersInGroup = $group->getBackend()->usersInGroup($groupId);
            $members = array_map(fn ($item) => ["value" => $item, "ref" => "", "displayName" => ""], $usersInGroup);

            return ["id" => $id, "displayName" => $displayName, 'members' => $members,];
        }
    }
}

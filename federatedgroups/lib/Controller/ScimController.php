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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCA\FederatedGroups\AppInfo\Application;
use OCA\FederatedGroups\MixedGroupShareProvider;

const IGNORE_DOMAIN = "sram.surf.nl";

function getOurDomain()
{
//    return getenv("SITE") . ".pondersource.net";
//    return $_SERVER['HTTP_HOST'];
    return "oc1.docker";
}

/**
 * Class ScimController
 *
 * @package OCA\FederatedGroups\Controller
 */
class ScimController extends Controller
{
    private IGroupManager $groupManager;
    protected MixedGroupShareProvider $mixedGroupShareProvider;

    public function __construct(string $appName, IRequest $request, IGroupManager $groupManager)
    {
        parent::__construct($appName, $request);
        $federatedGroupsApp = new Application();
        $this->mixedGroupShareProvider = $federatedGroupsApp->getMixedGroupShareProvider();
        $this->groupManager = $groupManager;
    }

    private function getNewDomainIfNeeded($newMember, $currentMembers)
    {
        $newMemberParts = explode("#", $newMember);
        if (count($newMemberParts) == 1) return null;

        if (count($newMemberParts) == 2) {
            $newDomain = $newMemberParts[1];
            if (str_contains($newDomain, getOurDomain())) return null;
            // if we have a member with same domain, we have sent the invite before, so no need to send it again
            foreach ($currentMembers as $currentMember) {
                $currentMemberParts = explode("#", $currentMember);
                if (count($currentMemberParts) == 2) {
                    $currentMemberDomain = $currentMemberParts[1];
                    if ($currentMemberDomain == $newDomain) return null;
                }
            }
            return $newDomain;
        }
    }

    private function handleUpdateGroup(string $groupId, $obj)
    {
        $group = $this->groupManager->get($groupId);
        $backend = $group->getBackend();
        $currentMembers = $backend->usersInGroup($groupId);
        $newMembers = [];
        foreach ($obj["members"] as $member) {
            $userIdParts = explode("@", $member["value"]);
            if (count($userIdParts) == 3) {
                $userIdParts = [$userIdParts[0] . "@" . $userIdParts[1], $userIdParts[2]];
            }
            if (count($userIdParts) != 2) {
                throw new Exception("cannot parse OCM user " . $member["value"]);
            }
            $newMember = $userIdParts[0];
            if ($userIdParts[1] !== getOurDomain()) {
                $newMember .= "#" . $userIdParts[1];
            }
            if ($userIdParts[1] === IGNORE_DOMAIN) {
                continue;
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
    public function createGroup($id, $members)
    {
        $body = ["id" => $id, "members" => $members];

        $this->groupManager->createGroup($id);

        // expect group to already exist
        // we are probably receiving this create due to
        // https://github.com/SURFnet/rd-sram-integration/commit/38c6289fd85a92b7fce5d4fbc9ea3170c5eed5d5
        $this->handleUpdateGroup($id, $body);
        return new JSONResponse(
            $body,
            Http::STATUS_CREATED
        );
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function updateGroup($groupId, $members)
    {
        $body = ["members" => $members];

        try {
            $this->handleUpdateGroup($groupId, $body);
        } catch (\Throwable $th) {
            return new JSONResponse([
                'status' => 'error',
                'data' => [
                    'message' => "Falure in sending ocm invites"
                ]
            ], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse(
            $body,
            Http::STATUS_OK
        );
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function deleteGroup($groupId)
    {
        $group = $this->groupManager->get(\urldecode($groupId));
        if ($group) {
            $deleted = $group->delete();
            if ($deleted) {
                return new JSONResponse(
                    [
                        'status' => 'success',
                        'data' => [
                            'message' => "Succesfully deleted group: {$groupId}"
                        ]
                    ],
                    Http::STATUS_NO_CONTENT
                );
            } else {
                return new JSONResponse(
                    [
                        'status' => 'error',
                        'data' => [
                            'message' => "Falure in deleting group: {$groupId}"
                        ]
                    ],
                    Http::STATUS_BAD_GATEWAY
                );
            }
        } else {
            return new JSONResponse(
                [
                    'status' => 'error',
                    'data' => [
                        'message' => "Could not find Group with the given identifier: {$groupId}"
                    ],
                ],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function getGroups()
    {
        $groups = [];
        $res = [];

        foreach ($this->groupManager->getBackends() as $backend) {
            $_groups = $backend->getGroups();
            \array_push($groups, ...$_groups);
        }

        foreach ($groups as $groupId) {
            $group = $this->groupManager->get(\urldecode($groupId));
            $groupObj = [];

            $groupObj["id"] = $group->getGID();
            $groupObj["displayName"] = $group->getDisplayName();

            $groupBackend = $group->getBackend();
            $usersInGroup = $groupBackend->usersInGroup($groupId);

            $groupObj["members"] = array_map(function ($item) {
                return [
                    "value" => $item,
                    "ref" => "",
                    "displayName" => "",
                ];
            }, $usersInGroup);

            $res[] = $groupObj;
        }

        return new JSONResponse([
            "totalResults" => count($groups),
            "Resources" => $res,
        ], Http::STATUS_OK);
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function getGroup($groupId)
    {
        // work around #129
        $group = $this->groupManager->get(\urldecode($groupId));
        if ($group) {
            $id = $group->getGID();
            $displayName = $group->getDisplayName();

            $groupBackend = $group->getBackend();
            $usersInGroup = $groupBackend->usersInGroup($groupId);

            $members = array_map(function ($item) {
                return [
                    "value" => $item,
                    "ref" => "",
                    "displayName" => "",
                ];
            }, $usersInGroup);

            return new JSONResponse([
                "id" => $id,
                "displayName" => $displayName,
                // 'usersInGroup' => $usersInGroup,
                'members' => $members,
                "schemas" => [
                    // "urn:ietf:params:scim:schemas:core:2.0:Group",
                    // "urn:ietf:params:scim:schemas:cyberark:1.0:Group"
                ],
                "meta" => [
                    "resourceType" => "Group",
                    // "created" => "2022-04-12T09:21:40.2319276Z",
                    // "lastModified" => "2022-04-12T09:21:40.2319276Z",
                    // "location" => "https://aax5785.my.idaptive.qa/Scim/v2/Group/8"

                ],
                "urn:ietf:params:scim:schemas:cyberark:1.0:Group" => [
                    // "directoryType" => "Vault"
                ],
            ], Http::STATUS_OK);
        } else {
            return new JSONResponse(
                [
                    'status' => 'error',
                    'data' => [
                        'message' => "Could not find Group with the given identifier: {$groupId}"
                    ],
                ],
                Http::STATUS_NOT_FOUND
            );
        }
    }
}
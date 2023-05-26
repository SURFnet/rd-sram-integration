<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

use OCA\FederatedGroups\Controller\ScimController;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCP\AppFramework\Http;
use OCP\IGroup;
use Test\TestCase;
use OCP\IRequest;
use OCP\IGroupManager;
use Test\Util\Group\Dummy;


/**
 * @group DB
 */
class ScimControllerTest extends TestCase {
    private IGroupManager $groupManager;

    private ScimController $controller;
    private MixedGroupShareProvider $mixedGroupShareProvider;

    protected function setUp(): void {
        parent::setUp();
        $request = $this->createMock(IRequest::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->controller = new ScimController("federatedGroups", $request, $this->groupManager);
        $this->mixedGroupShareProvider = $this->createMock(MixedGroupShareProvider::class);
    }


    // deleteGroup START
    public function test_it_can_delete_created_group() {
        $groupId = 'test-group';
        $members = [["value" => "test_user@oc2.docker"]];
        $deleted = true;
        $currentMembers = ["admin"];

        $group = $this->createMock(IGroup::class);

        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManager->expects($this->once())->method("createGroup")->with($groupId);
        $this->groupManager->expects($this->any())->method("get")->with($groupId)->willReturn($group);

        $group->expects($this->once())->method("getBackend")->willReturn($groupBackend);

        $groupBackend->expects($this->once())->method("usersInGroup")->willReturn($currentMembers);
        $groupBackend->expects($this->once())->method("removeFromGroup");

        $createResponse = $this->controller->createGroup($groupId, $members);

        $this->assertEquals(Http::STATUS_CREATED, $createResponse->getStatus());

        $group->expects($this->once())->method("delete")->willReturn($deleted);

        $deleteResponse = $this->controller->deleteGroup($groupId);

        $this->assertEquals(Http::STATUS_NO_CONTENT, $deleteResponse->getStatus());
    }

    public function test_it_returns_404_when_delete_non_existing_group() {
        $groupId = 'test-group';

        $this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn(null);

        $deleteResponse = $this->controller->deleteGroup($groupId);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $deleteResponse->getStatus());
    }
    // deleteGroup END


    // getGroups START
    public function test_it_can_get_list_of_groups() {
        $groups = ["admin", "federalists"];
        $backend = $this->createMock(Dummy::class);
        $backends = [$backend];

        $group = $this->createMock(IGroup::class);
        $groups = [$group];

        $this->groupManager->expects($this->once())->method("getBackends")->willReturn($backends);

        $groupItem = $this->createMock(IGroup::class);

        $this->groupManager->expects($this->once())->method("get")->willReturn($groupItem);

        $backend->expects($this->any())->method("getGroups")->willReturn($groups);

        $this->groupManager->expects($this->any())->method("get")->with($group);
        $groupBackend = $this->createMock(\OC\Group\Backend::class);
        $groupItem->expects($this->any())->method("getBackend")->willReturn($groupBackend);
        $usersInGroup = ["admin"];

        $groupBackend->expects($this->any())->method("usersInGroup")->with($group)->willReturn($usersInGroup);

        $response = $this->controller->getGroups();
        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }
    // getGroups END

    // getGroup START
    public function test_it_returns_404_if_groups_not_exists() {
        $groupId = 'test-group';

        $this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn(null);

        $getResponse = $this->controller->getGroup($groupId);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $getResponse->getStatus());
    }

    public function test_it_can_get_group_if_exists() {
        $groupId = 'test-group';

        $usersInGroup = ["admin"];

        $group = $this->createMock(IGroup::class);

        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn($group);

        $group->expects($this->once())->method("getBackend")->willReturn($groupBackend);

        $groupBackend->expects($this->once())->method("usersInGroup")->willReturn($usersInGroup);

        $responce = $this->controller->getGroup($groupId);

        $this->assertEquals(Http::STATUS_OK, $responce->getStatus());
    }
    // getGroup END


    // updateGroup START
    public function test_it_can_update_group_if_exists() {
        $groupId = 'test-group';
        $members = [["value" => "test_user@oc2.docker"]];
        $newMembers = ["test_user#oc2.docker"];
        $currentMembers = ["current_member"];

        $group = $this->createMock(IGroup::class);
        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManager->expects($this->any())->method("get")->with($groupId)->willReturn($group);
        $group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
        $groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);
        $groupBackend->expects($this->once())->method('removeFromGroup')->with($currentMembers[0], $groupId);
        $groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[0], $groupId);

        $newDomain = explode("#", $newMembers[0]);
        $this->mixedGroupShareProvider->expects($this->any())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);

        $response = $this->controller->updateGroup($groupId, $members);
        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }
    // updateGroup END

    // createGroup START
    public function test_it_can_create_group() {
        $groupId = 'test-group';
        $members = [["value" => "test_user@oc2.docker"]];
        $newMembers = ["test_user#oc2.docker"];
        $currentMembers = [];

        $group = $this->createMock(IGroup::class);
        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManager->expects($this->any())->method("createGroup")->with($groupId);
        $this->groupManager->expects($this->any())->method("get")->with($groupId)->willReturn($group);
        $group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
        $groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);
        $groupBackend->expects($this->any())->method('addToGroup')->with($newMembers[0], $groupId);

        $response = $this->controller->createGroup($groupId, $members);
        $this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
    }
    // createGroup END
}

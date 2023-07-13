<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

use OCA\FederatedGroups\Controller\ScimController;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCP\AppFramework\Http;
use OCP\IGroup;
use Test\TestCase;
use OCP\IRequest;
use Test\Util\Group\Dummy;
use OCA\FederatedGroups\GroupManagerProxy;
use OCP\ILogger;


/**
 * @group DB
 */
class ScimControllerTest extends TestCase {
    private ScimController $controller;
    private MixedGroupShareProvider $mixedGroupShareProvider;
    private ILogger $logger;
    private GroupManagerProxy $groupManagerProxy;

    protected function setUp(): void {
        parent::setUp();
        $request = $this->createMock(IRequest::class);
        $this->groupManagerProxy = $this->createMock(GroupManagerProxy::class);
        $this->logger = $this->createMock(ILogger::class);
        $this->controller = new ScimController("federatedGroups", $request, $this->groupManagerProxy, $this->logger);
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

        $this->groupManagerProxy->expects($this->once())->method("createGroup")->with($groupId);
        $this->groupManagerProxy->expects($this->any())->method("get")->with($groupId)->willReturn($group);

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

        $this->groupManagerProxy->expects($this->once())->method("get")->with($groupId)->willReturn(null);

        $deleteResponse = $this->controller->deleteGroup($groupId);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $deleteResponse->getStatus());
    }
    // deleteGroup END


    // getGroups START
    public function test_it_can_get_list_of_groups() {
        $groups = ["admin", "federalists"];
        $usersInGroup = ["admin"];

        $backend = $this->createMock(Dummy::class);
        $backends = [$backend];

        $group = $this->createMock(IGroup::class);
        $groups = [$group];

        $this->groupManagerProxy->expects($this->once())->method("getBackends")->willReturn($backends);
        $backend->expects($this->any())->method("getGroups")->willReturn($groups);


        $this->groupManagerProxy->expects($this->any())->method("get")->with($group)->willReturn($group);
        $group->expects($this->any())->method("getGID");
        $group->expects($this->any())->method("getDisplayName");

        $groupBackend = $this->createMock(\OC\Group\Backend::class);
        $group->expects($this->any())->method("getBackend")->willReturn($groupBackend);

        $groupBackend->expects($this->any())->method("usersInGroup")->with($group)->willReturn($usersInGroup);

        $response = $this->controller->getGroups();
        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }
    // getGroups END

    // getGroup START
    public function test_it_returns_404_if_groups_not_exists() {
        $groupId = 'test-group';

        $this->groupManagerProxy->expects($this->once())->method("get")->with($groupId)->willReturn(null);

        $getResponse = $this->controller->getGroup($groupId);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $getResponse->getStatus());
    }

    public function test_it_can_get_group_if_exists() {
        $groupId = 'test-group';

        $usersInGroup = ["admin"];

        $group = $this->createMock(IGroup::class);

        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManagerProxy->expects($this->once())->method("get")->with($groupId)->willReturn($group);

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

        $this->groupManagerProxy->expects($this->any())->method("get")->with($groupId)->willReturn($group);
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

        $this->groupManagerProxy->expects($this->any())->method("createGroup")->with($groupId);
        $this->groupManagerProxy->expects($this->any())->method("get")->with($groupId)->willReturn($group);
        $group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
        $groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);
        $groupBackend->expects($this->any())->method('addToGroup')->with($newMembers[0], $groupId);

        $response = $this->controller->createGroup($groupId, $members);
        error_log(json_encode($response->getData()));
        $this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
    }
    // createGroup END
}

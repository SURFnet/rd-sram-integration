<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

use OCA\FederatedGroups\Controller\ScimController;
use OCP\AppFramework\Http;
use OCP\GroupInterface;
use OCP\IGroup;
use Test\TestCase;
use OCP\IRequest;
use OCP\IGroupManager;
use Test\Util\Group\Dummy;


/**
 * @group DB
 */
class ScimControllerTest extends TestCase
{

    /**
     * @var IGroupManager $groupManager
     */
    private $groupManager;
    /**
     * @var ScimController
     */
    private $controller;
    /**
     * @var IRequest
     */
    private $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->createMock(IRequest::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->controller = new ScimController("federatedGroups", $this->request, $this->groupManager);
    }


    // deleteGroup START
    public function test_it_can_delete_created_group()
    {
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

        // Create First
        $createResponse = $this->controller->createGroup($groupId, $members);

        $this->assertEquals(Http::STATUS_CREATED, $createResponse->getStatus());

        $group->expects($this->once())->method("delete")->willReturn($deleted);

        $deleteResponse = $this->controller->deleteGroup($groupId);

        $this->assertEquals(Http::STATUS_NO_CONTENT, $deleteResponse->getStatus());
    }

    public function test_it_returns_404_when_delete_non_existing_group()
    {
        $groupId = 'test-group';

        $this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn(null);

        $deleteResponse = $this->controller->deleteGroup($groupId);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $deleteResponse->getStatus());
    }
    // deleteGroup END


    // getGroups START
//    public function test_it_can_get_list_of_groups()
//    {
//
////        $stub = $this->getMockBuilder(GroupInterface::class)->getMock()->method("")->willReturn();
//
//        $groups = ["admin", "federalists"];
//        $backend = $this->createMock(GroupInterface::class);
//        $backends = [$backend];
////        $backend = $this->createMock(\OCP\GroupInterface::class);
////        $backends = [$backend];
////        $group_1 = $this->createMock(IGroup::class);
////        $group_2 = $this->createMock(IGroup::class);
//        $this->groupManager->expects($this->once())->method("getBackends")->willReturn([$backend]);
//        $backend->expects($this->any())->method("getGroups");
//        $this->groupManager->expects($this->any())->method("get")->with($groups[0]);
//        $response = $this->controller->getGroups();
//        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
//    }
    // getGroups END

    // getGroup START
    public function test_it_returns_404_if_groups_not_exists()
    {
        $groupId = 'test-group';

        $this->groupManager->expects($this->once())->method("get")->with($groupId)->willReturn(null);

        $getResponse = $this->controller->getGroup($groupId);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $getResponse->getStatus());
    }

    public function test_it_can_get_group_if_exists()
    {
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
    public function test_it_can_update_group_if_exists()
    {

        $groupId = 'test-group';
        $currentMembers = ["currentMember"];
        $members = [["value" => "test_user@oc2.docker"]];
        $newMembers = ["test_user#oc2.docker"];

        $group = $this->createMock(IGroup::class);
        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManager->expects($this->any())->method("get")->with($groupId)->willReturn($group);
        $group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
        $groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

        $groupBackend->expects($this->once())->method('removeFromGroup');
//        $newDomain = explode("#", $newMembers[0]);
//        $this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);

        $groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[0], $groupId);

        $response = $this->controller->updateGroup($groupId, $members);

        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }
    // updateGroup END

    // createGroup START
    public function test_it_can_create_group()
    {
        $groupId = 'test-group';
        $currentMembers = ["currentMember"];
        $members = [["value" => "test_user@oc2.docker"]];
        $newMembers = ["test_user#oc2.docker"];

        $group = $this->createMock(IGroup::class);
        $groupBackend = $this->createMock(Dummy::class);

        $this->groupManager->expects($this->any())->method("createGroup")->with($groupId);
        $this->groupManager->expects($this->any())->method("get")->with($groupId)->willReturn($group);
        $group->expects($this->once())->method('getBackend')->willReturn($groupBackend);
        $groupBackend->expects($this->once())->method('usersInGroup')->with($groupId)->willReturn($currentMembers);

        $groupBackend->expects($this->once())->method('removeFromGroup');
//        $newDomain = explode("#", $newMembers[0]);
//        $this->mixedGroupShareProvider->expects($this->once())->method('sendOcmInviteForExistingShares')->with($newDomain, $groupId);

        $groupBackend->expects($this->once())->method('addToGroup')->with($newMembers[0], $groupId);

        $response = $this->controller->updateGroup($groupId, $members);

        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }
    // createGroup END
}
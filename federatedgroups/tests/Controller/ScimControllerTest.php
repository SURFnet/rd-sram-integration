<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

use OCA\FederatedGroups\Controller\ScimController;
use OCP\AppFramework\Http;
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
    }

    public function tearDown(): void
    {
        parent::tearDown();
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


        $this->controller = new ScimController("federatedGroups", $this->request, $this->groupManager);

        // Create First
        $createResponse = $this->controller->createGroup($groupId, $members);

        $this->assertEquals(Http::STATUS_CREATED, $createResponse->getStatus());

        $group->expects($this->once())->method("delete")->willReturn($deleted);

        $deleteResponse = $this->controller->deleteGroup($groupId);

        $this->assertEquals(Http::STATUS_OK, $deleteResponse->getStatus());
    }

}
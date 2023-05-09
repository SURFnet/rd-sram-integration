<?php

declare(strict_types=1);

namespace OCA\FederatedGroups\Tests\Controller;

// use OCA\OpenCloudMesh\Tests\FederatedFileSharing\TestCase;
use Test\TestCase;
use OCP\AppFramework\Http;

const RESPONSE_TO_USER_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_USER_UPDATE = Http::STATUS_OK;

const RESPONSE_TO_GROUP_CREATE = Http::STATUS_CREATED;
const RESPONSE_TO_GROUP_UPDATE = Http::STATUS_OK;
const IGNORE_DOMAIN = "sram.surf.nl";

function getOurDomain() {
	return getenv("SITE") . ".pondersource.net";
}

class ScimControllerTest extends TestCase {

	/**
	 * @var IGroupManager $groupManager
	 */
	private $groupManager;
	/**
	 * @var MixedGroupShareProvider
	 */
	protected $mixedGroupShareProvider;

	protected function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->getMockBuilder(IGroupManager::class);

		// $this->get
		// $this->object = new stdClass();
	}

	public function it_will_return_groups() {
	}
}

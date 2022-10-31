<?php
/**
 *
 */

namespace OCA\FederatedGroups;

use OCP\AppFramework\App;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('federatedgroups', $urlParams);
	}
}

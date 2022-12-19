<?php

// SPDX-FileCopyrightText: 2022 SURF
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 *
 */

namespace OCA\FederatedGroups;

use OCP\AppFramework\App;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('federatedgroups', $urlParams);
	}
	public static function createShare() {
		error_log("Federated Groups app creating share!");
	}
	public static function processNotification() {
		error_log("Federated Groups app processing notification!");
	}
} 

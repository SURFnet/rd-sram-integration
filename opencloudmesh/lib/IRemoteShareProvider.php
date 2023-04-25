<?php

namespace OCA\OpenCloudMesh;

use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

interface IRemoteShareProvider extends IShareProvider {
    /**
	 * get remote share from the local table but exclude mounted link shares
	 *
	 * @param IShare $share
	 * @return array
	 * @throws ShareNotFound
	 */
    public function getShareFromLocalTable(IShare $share);
}
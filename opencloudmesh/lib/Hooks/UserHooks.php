<?php

namespace OCA\OpenCloudMesh\Hooks;

use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IConfig;
use OCA\OpenCloudMesh\Files_Sharing\External\Manager;

class UserHooks
{
    private IConfig $config;
    private IUserSession $userSession;
    private IUserManager $userManager;
    private IGroupManager $groupManager;
    private Manager $externalManager;

    public function __construct(
        IConfig $config,
        IUserSession $userSession,
        IUserManager $userManager,
        IGroupManager $groupManager,
        Manager $externalManager
    ) {
        $this->config = $config;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->externalManager = $externalManager;
    }

    public function register()
    {
        $globalAutoAcceptValue = $this->config->getAppValue('federatedfilesharing', 'auto_accept_trusted', 'no');
        if ($globalAutoAcceptValue === 'yes') {
            $this->userSession->listen('\OC\User', 'preLogin', function ($user) {
                $user = $this->userManager->get($user);
                $userId = $user->getUID();

                \OC_Util::setupFS($userId);

                $userGroups = $this->groupManager->getUserGroups($user);

                foreach ($userGroups as $group) {
                    $groupId = $group->getGID();

                    $this->externalManager->acceptRemoteGroupShares($groupId, $userId);
                }
            });
        }
    }
}
<?php

namespace OCA\FederatedGroups;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use OC\Hooks\PublicEmitter;
use OC\Group\Group;
use OCP\IGroupManager;
use OC\User\Manager as UserManager;
use OCP\ILogger;
use OCA\FederatedGroups\GroupBackend;


class GroupManagerProxy extends PublicEmitter
{
    /**
     * @var \OC\Group\Group[]
     */
    private $cachedGroups = [];

    /**
     * @var \OC\Group\Group[]
     */
    private $cachedUserGroups = [];

    private IGroupManager $groupManager;
    private UserManager $userManager;
    private EventDispatcherInterface $eventDispatcher;

    private ILogger $logger;
    private GroupBackend $groupBackend;



    public function __construct(
        IGroupManager $groupManager,
        UserManager $userManager,
        EventDispatcherInterface $eventDispatcher,
        GroupBackend $groupBackend,
        ILogger $logger
    ) {

        $this->groupManager = $groupManager;
        $this->eventDispatcher = $eventDispatcher;

        $this->userManager = $userManager;

        $cachedGroups = &$this->cachedGroups;
        $cachedUserGroups = &$this->cachedUserGroups;
        $this->groupBackend = $groupBackend;
        $this->logger = $logger;

        $this->listen('\OC\Group', 'postDelete', function ($group) use (&$cachedGroups, &$cachedUserGroups) {
            /**
             * @var \OC\Group\Group $group
             */
            unset($cachedGroups[$group->getGID()]);
            $cachedUserGroups = [];
        });
        $this->listen('\OC\Group', 'postAddUser', function ($group) use (&$cachedUserGroups) {
            /**
             * @var \OC\Group\Group $group
             */
            $cachedUserGroups = [];
        });
        $this->listen('\OC\Group', 'postRemoveUser', function ($group) use (&$cachedUserGroups) {
            /**
             * @var \OC\Group\Group $group
             */
            $cachedUserGroups = [];
        });
    }


    /**
     * @param string $gid
     * @return \OC\Group\Group
     */
    public function createGroup($gid)
    {
        if ($gid === '' || $gid === null || \trim($gid) !== $gid) {
            return false;
        } elseif ($group = $this->get($gid)) {
            return $group;
        } else {
            $this->emit('\OC\Group', 'preCreate', [$gid]);
            $this->eventDispatcher->dispatch(new GenericEvent(null, ['gid' => $gid]), 'group.preCreate');

            if ($this->groupBackend->implementsActions(\OC\Group\Backend::CREATE_GROUP)) {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $created = $this->groupBackend->createGroup($gid);

                if ($created) {
                    $this->cachedGroups[$gid] = new Group($gid, [$this->groupBackend], $this->userManager, $this->eventDispatcher, $this, null);
                }

                $group = $this->get($gid);
                $this->emit('\OC\Group', 'postCreate', [$group]);
                $this->eventDispatcher->dispatch(new GenericEvent($group, ['gid' => $gid]), 'group.postCreate');
                return $group;
            }

            return null;
        }
    }

    /**
     * @param string $gid
     * @return \OC\Group\Group|null
     */
    function get($gid)
    {
        if (isset($this->cachedGroups[$gid])) {
            return $this->cachedGroups[$gid];
        }
        return $this->groupManager->get($gid);
    }

    /**
     * Get the active backends
     * @return \OCP\GroupInterface[]
     */
    public function getBackends()
    {
        return $this->groupManager->getBackends();
    }

}
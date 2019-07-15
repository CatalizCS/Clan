<?php

namespace Clan;

use pocketmine\scheduler\Task;

class ClanWar extends Task {

    public $plugin;
    public $requester;

    public function __construct(ClanMain $pl, $requester) {
        $this->plugin = $pl;
        $this->requester = $requester;
    }

    public function onRun(int $currentTick) {
        unset($this->plugin->wars[$this->requester]);
        $this->plugin->getScheduler()->cancelTask($this->getTaskId());
    }

}
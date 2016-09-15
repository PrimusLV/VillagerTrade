<?php

namespace thebigsmileXD\VillagerTrade;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\Player;

class AddWindowTask extends PluginTask{

	public function __construct(Plugin $owner, Player $player, $inventory){
		parent::__construct($owner);
		$this->plugin = $owner;
		$this->player = $player;
		$this->inventory = $inventory;
	}

	public function onRun($currentTick){
		$this->getOwner()->addWindow($this->player, $this->inventory);
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}
?>
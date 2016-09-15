<?php

namespace thebigsmileXD\VillagerTrade;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\String;
use pocketmine\nbt\tag\Int;
use pocketmine\tile\Tile;
use pocketmine\item\Item;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\nbt\NBT;
use pocketmine\block\Block;
use pocketmine\tile\Hopper;
use pocketmine\inventory\HopperInventory;
use pocketmine\block\Chest;
use pocketmine\inventory\ChestInventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\level\generator\normal\biome\PlainBiome;
use pocketmine\level\sound\ClickSound;

class Main extends PluginBase implements Listener{
	public $trader;
	public $fakeChest = null;
	public $inventory = null;
	public $villager = null;
	public $site = 0;

	public function onLoad(){
		$this->getLogger()->info(TextFormat::GREEN . "Loading " . $this->getDescription()->getFullName());
	}

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getLogger()->info(TextFormat::GREEN . "Enabling " . $this->getDescription()->getFullName() . " by " . $this->getDescription()->getAuthors()[0]);
		$this->getServer()->getLogger()->info(TextFormat::AQUA . "o Thanks for using my VillagerTrade plugin! Thats so awesome!");
		$this->getServer()->getLogger()->info(TextFormat::AQUA . "o This is my second plugin, please leave a like! Please report bugs on the threat in the PocketMine Forum.");
		
		if(!$this->getConfig()->exists("messages")){
			$this->getConfig()->set("messages");
			$this->getConfig()->setNested("messages", array("join" => true));
		}
		$this->getConfig()->save();
		$this->saveResource("villagers.yml");
		$this->getVillagerConfig();
		
		if(empty($this->profession->getAll())){
			$this->profession->set("villager");
			$this->profession->setNested("Villager", array("items" => array("APPLE" => array("amount" => 5, "cost" => 3), "WOOD" => array("amount" => 32, "cost" => 10))));
		}
		$this->setVillagerConfig();
		// $this->saveResource("messages.yml");
	}

	public function getVillagerConfig(){
		$this->profession = new Config($this->getDataFolder() . "villagers.yml", Config::YAML);
	}

	public function setVillagerConfig(){
		$this->profession->save();
		$this->profession->reload();
	}

	/*
	 * public function getMessageConfig(){
	 * $this->translation = new Config($this->getDataFolder() . "players.yml", Config::YAML);
	 * }
	 * public function setMessageConfig(){
	 * $this->level->save();
	 * $this->level->reload();
	 * }
	 */
	public function onDisable(){
		$this->getServer()->getLogger()->info(TextFormat::RED . "Disabling " . $this->getDescription()->getFullName() . " by " . $this->getDescription()->getAuthors()[0]);
	}

	public function onJoin(PlayerJoinEvent $event){
		if($this->getConfig()->getNested("messages.join") == "true"){
			$event->getPlayer()->sendMessage(TextFormat::GOLD . "This server uses VillagerTrade by thebigsmileXD");
			if($event->getPlayer()->isOp()){
				$event->getPlayer()->sendMessage(TextFormat::AQUA . "[VillagerTrade] " . TextFormat::GOLD . "Thanks for using this plugin on your server! Please give feedback on the PocketMine forum!");
				$event->getPlayer()->sendMessage(TextFormat::AQUA . "[VillagerTrade] " . TextFormat::GOLD . "You can disable this message in the config.yml");
			}
		}
	}

	public function onLeave(PlayerQuitEvent $event){
		$this->closeMenu($event->getPlayer());
	}

	public function onKick(PlayerKickEvent $event){
		$this->closeMenu($event->getPlayer());
	}

	public function onWorldChange(EntityLevelChangeEvent $event){
		if($event->getEntity() instanceof Player){
			$this->closeMenu($event->getEntity());
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		if($sender instanceof Player && strtolower($command->getName()) === "trader"){
			$this->trader[$sender->getName()] = true;
		}
	}

	public function overrideVillager(EntityDamageEvent $event){
		$entity = $event->getEntity();
		$cause = $event->getCause();
		if($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK && $event instanceof EntityDamageByEntityEvent){
			$player = $event->getDamager();
			if($player instanceof Player && $entity->getNameTag() === "Villager"){
				$event->setCancelled(true);
				if($player->getInventory()->getItemInHand()->getId() === Item::DIAMOND_SWORD && $player->isOP()){
					$entity->kill();
					return;
				}
				$this->getVillagerConfig();
				if($this->profession->exists($entity->getNameTag())){
					$this->openMenu($player, $entity);
				}
			}
		}
	}

	public function onEntitySpawn(EntitySpawnEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Villager){
			$entity->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_NO_AI, true);
			$entity->setNameTag("Villager");
			$entity->setNameTagVisible(true);
		}
	}

	public function openMenu(Player $player, Entity $villager){
		$this->getVillagerConfig();
		$villager->getLevel()->setBlockIdAt($villager->x, $villager->y, $villager->z, Block::HOPPER);
		
		$nbt = new Compound("", [new Enum("Items", []), new String("id", Tile::HOPPER), new Int("x", $villager->x), new Int("y", $villager->y), new Int("z", $villager->z)]);
		$nbt->Items->setTagType(NBT::TAG_Compound);
		
		$nbt->CustomName = new String("CustomName", "Trade");
		$tile2 = new VillagerTile($villager->getLevel()->getChunk($villager->x >> 4, $villager->z >> 4), $nbt);
		$items = array_keys($this->profession->getNested($villager->getNameTag() . ".items"));
		$this->fakeChest = $tile2;
		$this->inventory = new VillagerInventory($tile2);
		
		$this->inventory->setItem(0, Item::get(Item::HOPPER));
		$this->inventory->setItem(1, Item::get(Item::AIR));
		$this->inventory->setItem(2, Item::get(Item::HOPPER));
		$this->inventory->setItem(3, Item::get(Item::fromString($items[0])->getId(), null, intval($this->profession->getNested($villager->getNameTag() . ".items." . $items[0] . ".amount"))));
		$this->inventory->setItem(4, Item::get(Item::HOPPER));
		$this->villager = $villager->getNameTag();
		$this->trader = $player;
		$this->site = 0;
		$this->getServer()->getScheduler()->scheduleDelayedTask(new AddWindowTask($this, $player, $this->inventory), 2);
	}

	public function addWindow(Player $player, $inventory){
		$player->addWindow($inventory);
	}

	public function closeMenu(Player $player){
		if($this->inventory !== null && $player->isOnline()) $player->getInventory()->addItem($this->inventory->getItem(1));
		if($this->fakeChest !== null) $player->getLevel()->setBlockIdAt($this->fakeChest->x, $this->fakeChest->y, $this->fakeChest->z, 0);
	}

	public function onClose(InventoryCloseEvent $event){
		if($this->inventory !== null && $event->getInventory() === $this->inventory) $this->closeMenu($event->getPlayer());
	}

	public function inventoryHandle(InventoryTransactionEvent $event){
		$playerid = array_keys($event->getTransaction()->getTransactions())[0];
		$villagerid = array_keys($event->getTransaction()->getTransactions())[1];
		$viewerid = array_keys($event->getTransaction()->getTransactions()[$playerid]->getInventory()->getViewers())[0];
		$player = $event->getTransaction()->getTransactions()[$playerid]->getInventory()->getViewers()[$viewerid];
		if(!$player instanceof Player || !$player->isOnline() || $this->inventory === null) return;
		if($event->getTransaction()->getTransactions()[$playerid]->getSourceItem()->getId() == Item::HOPPER){
			switch($event->getTransaction()->getTransactions()[$playerid]->getSlot()){
				case 0:
					{
						$this->goLeft($player);
						$event->setCancelled();
						break;
					}
				case 2:
					{
						$event->setCancelled();
						break;
					}
				case 4:
					{
						$this->goRight($player);
						$event->setCancelled();
						break;
					}
				default:
					break;
			}
		}
		elseif($event->getTransaction()->getTransactions()[$playerid]->getSlot() === 3){
			if($this->inventory->getItem(1)->getId() == Item::EMERALD){
				$cost = intval($this->profession->getNested($this->villager . ".items." . strtoupper($this->inventory->getItem(3)->getName()) . ".cost"));
				if($this->inventory->getItem(1)->getCount() < $cost) return;
				$amount = ($this->inventory->getItem(1)->getCount() - $cost);
				$this->inventory->getItem(1)->setCount($amount);
				if($amount <= 0) $this->inventory->setItem(1, Item::get(0));
				// else $this->inventory->setItem(1, Item::get($this->inventory->getItem(1)->getId(), null, $amount));
				$player->getInventory()->addItem($this->inventory->getItem(3));
			}
			$event->setCancelled();
		}
	}

	public function goLeft(Player $player){
		if($this->site <= 0) return;
		$items = array_keys($this->profession->getNested($this->villager . ".items"));
		$this->site--;
		$this->inventory->setItem(3, Item::get(Item::fromString($items[$this->site])->getId(), null, intval($this->profession->getNested($this->villager . ".items." . $items[$this->site] . ".amount"))));
	}

	public function goRight(Player $player){
		if($this->site >= (count(array_keys($this->profession->getNested($this->villager . ".items"))) - 1)) return;
		$items = array_keys($this->profession->getNested($this->villager . ".items"));
		$this->site++;
		$this->inventory->setItem(3, Item::get(Item::fromString($items[$this->site])->getId(), null, intval($this->profession->getNested($this->villager . ".items." . $items[$this->site] . ".amount"))));
	}
}
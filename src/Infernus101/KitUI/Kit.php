<?php

namespace Infernus101\KitUI;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Effect;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

use PiggyCustomEnchants\CustomEnchants\CustomEnchants;
use pocketmine\Server;

class Kit{

    public $pl;
    public $data;
    public $name;
    public $cost = 0;
    public $timer;
    public $timers = [];

    public function __construct(Main $pl, array $data, $name){
        $this->pl = $pl;
        $this->data = $data;
        $this->name = $name;
        $this->timer = $this->getTimerMinutes();
		if(file_exists($this->pl->getDataFolder()."timer/".strtolower($this->name).".sl")){
            $this->timers = unserialize(file_get_contents($this->pl->getDataFolder()."timer/".strtolower($this->name).".sl"));
        }
        if(isset($this->data["money"]) and $this->data["money"] != 0){
            $this->cost = (int) $this->data["money"];
        }
    }

    public function getName() {
        return $this->name;
    }

	public function isInventoryFull(Player $player){
		$full = true;
		for($i = 0; $i < $player->getInventory()->getSize(); $i++){
			if($player->getInventory()->getItem($i)->getId() === 0){
			$full = false;
			}
		}
		return $full;
    }

    public function add(Player $player){
        $inv = $player->getInventory();
		$flag = false;

		isset($this->data["helmet"]) and $inv->setHelmet($this->loadItem(...explode(":", $this->data["helmet"])));
        isset($this->data["chestplate"]) and $inv->setChestplate($this->loadItem(...explode(":", $this->data["chestplate"])));
        isset($this->data["leggings"]) and $inv->setLeggings($this->loadItem(...explode(":", $this->data["leggings"])));
        isset($this->data["boots"]) and $inv->setBoots($this->loadItem(...explode(":", $this->data["boots"])));

        foreach($this->data["items"] as $itemString){
			if(!$this->isInventoryFull($player)){
            $inv->setItem($inv->firstEmpty(), $i = $this->loadItem(...explode(":", $itemString)));
			}
			else{
			$flag = true;
			}
        }

		if($flag == true){
			$player->sendMessage($this->pl->language->getTranslation("inv-full"));
		}

        if(isset($this->data["effects"])){
            foreach($this->data["effects"] as $effectString){
                $e = $this->loadEffect(...explode(":", $effectString));
                if($e !== null){
                    $player->addEffect($e);
                }
            }
        }

        if(isset($this->data["commands"]) and is_array($this->data["commands"])){
            foreach($this->data["commands"] as $cmd){
                $this->pl->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("{player}", $player->getName(), $cmd));
            }
        }

        if($this->timer){
            $this->timers[strtolower($player->getName())] = $this->timer;
        }

        $this->pl->hasKit[strtolower($player->getName())] = $this;

    }

    public function loadItem($id = 0, $damage = 0, $count = 1, $name = "default", ...$enchantments){
        $item = Item::get($id, $damage, $count);
        if(strtolower($name) !== "default"){
            $item->setCustomName($name);
        }
	$ench = null;
        foreach($enchantments as $key => $name_level){
            if($key % 2 === 0){ //Name expected
                $ench = Enchantment::getEnchantmentByName((string) $name_level);
                if($ench === null){
                    $ench = CustomEnchants::getEnchantByName((string) $name_level);
                }
            }elseif($ench !== null){
                if($this->pl->piggyEnchants !== null && $ench instanceof CustomEnchants){
                    $this->pl->piggyEnchants->addEnchantment($item, $ench->getName(), (int) $name_level);
                }else{
                    $item->addEnchantment($ench->setLevel((int) $name_level));
                }
            }
        }
        return $item;
    }

    public function loadEffect($name = "INVALID", $seconds = 60, $amplifier = 1){
        $e = Effect::getEffectByName($name);
        if($e !== null){
            return $e->setDuration($seconds * 20)->setAmbient($amplifier);
        }
        return null;
    }

    public function getTimerMinutes(){
        $min = 0;
        if(isset($this->data["cooldown"]["minutes"])){
            $min += (int) $this->data["cooldown"]["minutes"];
        }
        if(isset($this->data["cooldown"]["hours"])){
            $min += (int) $this->data["cooldown"]["hours"] * 60;
        }
        return $min;
    }

    public function getTimerLeft(Player $player) {
        if(($minutes = $this->timers[strtolower($player->getName())]) < 60){
            return $this->pl->language->getTranslation("timer-format1", $minutes);
        }
        if(($modulo = $minutes % 60) !== 0){
            return $this->pl->language->getTranslation("timer-format2", floor($minutes / 60), $modulo);
        }
        return $this->pl->language->getTranslation("timer-format3", $minutes / 60);
    }

    public function processTimer(){
        foreach($this->timers as $player => $min){
            $this->timers[$player] -= 1;
            if($this->timers[$player] <= 0){
                unset($this->timers[$player]);
            }
        }
    }

    public function testPermission(Player $player) {
        return $player->hasPermission("kit.".strtolower($this->name));
    }

    public function save(){
        if(count($this->timers) > 0){
            file_put_contents($this->pl->getDataFolder()."timer/".strtolower($this->name).".sl", serialize($this->timers));
        }
    }

}

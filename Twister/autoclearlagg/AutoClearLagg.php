<?php
declare(strict_types=1);

namespace twisted\autoclearlagg;

use pocketmine\entity\Creature;
use pocketmine\entity\Human;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\object\ItemEntity;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use function array_map;
use function in_array;
use function is_array;
use function is_numeric;
use function str_replace;
use function strtolower;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class AutoClearLagg extends PluginBase{

    public const LANG_TIME_LEFT = "time-left";
    public const LANG_ENTITIES_CLEARED = "entities-cleared";

    /** @var int */
    private $interval;
    /** @var int */
    private $seconds;

    /** @var bool */
    private $clearItems;
    /** @var bool */
    private $clearMobs;
    /** @var bool */
    private $clearXpOrbs;

    /** @var string[] */
    private $exemptEntities;

    /** @var string[] */
    private $messages;
    /** @var int[] */
    private $broadcastTimes;
    
    public function onCommand(CommandSender $sender, Command $cmd, String $label, array $args) : bool {
        switch($cmd->getName()){
            case "aclg":
                if($sender->hasPermission("aclg")){
                    $entitiesCleared = 0;
                    foreach($this->getServer()->getLevels() as $level){
                        foreach($level->getEntities() as $entity){
                            if($this->clearItems && $entity instanceof ItemEntity){
                                $entity->flagForDespawn();
                                ++$entitiesCleared;
                                
                            }else if($this->clearMobs && $entity instanceof Creature && !$entity instanceof Human){
                                if(!in_array(strtolower($entity->getName()), $this->exemptEntities)){
                                    $entity->flagForDespawn();
                                    ++$entitiesCleared;
                                    
                                }
                                
                            }else if($this->clearXpOrbs && $entity instanceof ExperienceOrb){
                                $entity->flagForDespawn();
                                ++$entitiesCleared;
                                
                            }
                            
                        }
                        
                    }
                    $this->getServer()->broadcastMessage("§7[§cForce ClearLag§7] §aBerhasil membersihkan total $entitiesCleared sampah!");
                }
            break;
        }
        return true;
    }

    public function onEnable() : void{
        $config = $this->getConfig()->getAll();

        if(!is_numeric($config["seconds"] ?? 300)){
            $this->getLogger()->error("Config error: seconds attribute must an integer");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $this->interval = $this->seconds = $config["seconds"];

        if(!is_array($config["clear"] ?? [])){
            $this->getLogger()->error("Config error: clear attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $clear = $config["clear"] ?? [];
        $this->clearItems = (bool) ($clear["items"] ?? false);
        $this->clearMobs = (bool) ($clear["mobs"] ?? false);
        $this->clearXpOrbs = (bool) ($clear["xp-orbs"] ?? false);
        if(!is_array($clear["exempt"] ?? [])){
            $this->getLogger()->error("Config error: clear.exempt attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $this->exemptEntities = array_map(function($entity) : string{
            return strtolower((string) $entity);
        }, $clear["exempt"] ?? []);

        if(!is_array($config["messages"] ?? [])){
            $this->getLogger()->error("Config error: times attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $messages = $config["messages"] ?? [];
        $this->messages = [
            self::LANG_TIME_LEFT => $messages[self::LANG_TIME_LEFT] ?? "§7§l[§aVivaSMP§7] §r§cEntitas akan dihapus dalam {SECONDS} detik",
            self::LANG_ENTITIES_CLEARED => $messages[self::LANG_ENTITIES_CLEARED] ?? "§7§l[§aVivaSMP§7] §r§cMenghapus total {COUNT} entitas"
        ];

        if(!is_array($config["times"] ?? [])){
            $this->getLogger()->error("Config error: times attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $this->broadcastTimes = $config["times"] ?? [60, 30, 15, 10, 5, 4, 3, 2, 1];

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function($_) : void{
            if(--$this->seconds === 0){
                $entitiesCleared = 0;
                foreach($this->getServer()->getLevels() as $level){
                    foreach($level->getEntities() as $entity){
                        if($this->clearItems && $entity instanceof ItemEntity){
                            $entity->flagForDespawn();
                            ++$entitiesCleared;
                        }else if($this->clearMobs && $entity instanceof Creature && !$entity instanceof Human){
                            if(!in_array(strtolower($entity->getName()), $this->exemptEntities)){
                                $entity->flagForDespawn();
                                ++$entitiesCleared;
                            }
                        }else if($this->clearXpOrbs && $entity instanceof ExperienceOrb){
                            $entity->flagForDespawn();
                            ++$entitiesCleared;
                        }
                    }
                }
                if($this->messages[self::LANG_ENTITIES_CLEARED] !== ""){
                    $this->getServer()->broadcastMessage("§aBerhasil membersihkan total $entitiesCleared sampah!");
                }

                $this->seconds = $this->interval;
            }else if(in_array($this->seconds, $this->broadcastTimes) && $this->messages[self::LANG_TIME_LEFT] !== ""){
                $this->getServer()->broadcastMessage("§cPeringatan, barang di tanah akan di bersihkan dalam §7" . $this->seconds . " §cdetik!");
            }
        }), 20);
    }
}

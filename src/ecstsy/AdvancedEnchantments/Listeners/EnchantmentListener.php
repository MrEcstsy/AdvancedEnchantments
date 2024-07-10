<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\block\Farmland;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class EnchantmentListener implements Listener {

    private array $cooldowns = [];

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $block = $event->getBlock();

        if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
            foreach ($item->getEnchantments() as $enchantmentInstance) {
                $enchantment = $enchantmentInstance->getType();

                if ($enchantment instanceof CustomEnchantment) {
                    $enchantmentName = $enchantment->getName();
                    $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                    if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                        $enchantmentData = $enchantmentConfig[$enchantmentName];

                        if ($enchantmentData['type'] === 'MINING') {
                            $playerName = $player->getName();
                            $level = $enchantmentInstance->getLevel();

                            if ($this->hasCooldown($playerName, $enchantmentName)) {
                                continue;
                            }

                            if (isset($enchantmentData['levels'][$level])) {
                                $chance = $enchantmentData['levels'][$level]['chance'];
                                if (mt_rand(1, 100) <= $chance) {
                                    Utils::applyBlockEffects($player, $block, $enchantmentData['levels'][$level]['effects']);

                                    $cooldown = $enchantmentData['levels'][$level]['cooldown'];
                                    $this->setCooldown($playerName, $enchantmentName, $cooldown);

                                    $color = CEGroups::translateGroupToColor($enchantment->getRarity());
                                    if (isset($enchantmentData['settings']['showActionBar']) && $enchantmentData['settings']['showActionBar']) {
                                        $actionBarMessage = C::WHITE . "Used " . $color . ucfirst($enchantmentName) . " " . Utils::getRomanNumeral($level);
                                        $player->sendActionBarMessage($actionBarMessage);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK && $player->isSneaking()) {
            if ($block instanceof Farmland) {
                $enchantments = $item->getEnchantments();
                foreach ($enchantments as $enchantmentInstance) {
                    $enchantment = $enchantmentInstance->getType();
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentName = $enchantment->getName();
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();

                        if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                            $enchantmentData = $enchantmentConfig[$enchantmentName];
                            $level = $enchantmentInstance->getLevel();

                            if (isset($enchantmentData['levels']["$level"]['effects'])) {
                                foreach ($enchantmentData['levels']["$level"]['effects'] as $effect) {
                                    if ($effect['type'] === "PLANT_SEEDS") {
                                        $radius = $effect['radius'] ?? 1;
                                        $seedType = $effect['seed-type'] ?? null;
                                        Utils::plantSeeds($player, $block, $radius, $seedType);
                                        break; 
                                    }
                                }
                            }
                            break; 
                        }
                    }
                }
            }
        }
    }
 
    public function onEntityDamageEntity(EntityDamageByEntityEvent $event) {
        $attacker = $event->getDamager();
        $victim = $event->getEntity();
    
        if ($attacker instanceof Player) {
            $targetType = ($victim instanceof Player) ? 'PLAYERS' : 'MOBS';
            $context = ($victim instanceof Player) ? 'ATTACK' : 'ATTACK_MOBS';
    
            // Apply damage modifications and effects for attacker
            foreach ($attacker->getArmorInventory()->getContents() as $item) {
                foreach ($item->getEnchantments() as $enchantmentInstance) {
                    $enchantment = $enchantmentInstance->getType();
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentName = $enchantment->getName();
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                        
                        if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                            $enchantmentData = $enchantmentConfig[$enchantmentName];
                            $level = $enchantmentInstance->getLevel();
    
                            if ($enchantmentData['type'] === $context && isset($enchantmentData['levels']["$level"]['effects'])) {
                                $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;
    
                                if (mt_rand(1, 100) <= $chance) {
                                    $effects = $enchantmentData['levels']["$level"]['effects'];
                                    if (isset($effects['INCREASE_DAMAGE'])) {
                                        $event->setBaseDamage($event->getBaseDamage() + $effects['INCREASE_DAMAGE']);
                                    }
                                    if (isset($effects['DECREASE_DAMAGE'])) {
                                        $event->setBaseDamage($event->getBaseDamage() - $effects['DECREASE_DAMAGE']);
                                    }
                                    Utils::applyPlayerEffects($attacker, $victim, $effects);
                                }
                            }
                        }
                    }
                }
            }
    
            // Attacker item in hand
            $itemInHand = $attacker->getInventory()->getItemInHand();
            foreach ($itemInHand->getEnchantments() as $enchantmentInstance) {
                $enchantment = $enchantmentInstance->getType();
                if ($enchantment instanceof CustomEnchantment) {
                    $enchantmentName = $enchantment->getName();
                    $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();

                    if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                        $enchantmentData = $enchantmentConfig[$enchantmentName];
                        $level = $enchantmentInstance->getLevel();

                        if (($enchantmentData['type'] === "ATTACK" || $enchantmentData['type'] === "ATTACK_MOB") && isset($enchantmentData['levels']["$level"]['effects'])) {
                            $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;

                            if (mt_rand(1, 100) <= $chance) {
                                $effects = $enchantmentData['levels']["$level"]['effects'];
                                $conditions = $enchantmentData['levels']["$level"]['conditions'] ?? [];
                                $conditionsMet = empty($conditions) || Utils::checkConditions($conditions, $attacker, $victim);

                                if ($conditionsMet) {
                                    foreach ($effects as $effect) {

                                        if ($effect['type'] === "INCREASE_DAMAGE") {
                                            $amount = Utils::parseLevel($effect['amount'], $level);
                                            $event->setBaseDamage($event->getBaseDamage() + $amount);
                                        }

                                        if ($effect['type'] === "DECREASE_DAMAGE") {
                                            $amount = Utils::parseLevel($effect['amount'], $level);
                                            $event->setBaseDamage($event->getBaseDamage() - $amount);
                                        }
                                        Utils::applyPlayerEffects($attacker, $victim, $effects);
                                    }
                                }
                            }
                        }
                    }
                }
            }
    
            // Apply effects for victim if victim is a player
            if ($victim instanceof Player) {
                $attackerType = ($attacker instanceof Player) ? 'PLAYERS' : 'MOBS';
                $defenseContext = 'DEFENSE_' . $attackerType;
            
                foreach ($victim->getArmorInventory()->getContents() as $item) {
                    foreach ($item->getEnchantments() as $enchantmentInstance) {
                        $enchantment = $enchantmentInstance->getType();
                        if ($enchantment instanceof CustomEnchantment) {
                            $enchantmentName = $enchantment->getName();
                            $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                            
                            if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                                $enchantmentData = $enchantmentConfig[$enchantmentName];
                                $level = $enchantmentInstance->getLevel();
                                
                                if ($enchantmentData['type'] === "DEFENSE" && isset($enchantmentData['levels']["$level"]['effects'])) {
                                    $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;    
                                    
                                    if (mt_rand(1, 100) <= $chance) {
                                        $effects = $enchantmentData['levels']["$level"]['effects'];
                                        $initialDamage = $event->getBaseDamage();
                                        
                                        $conditions = $enchantmentData['levels']["$level"]['conditions'] ?? [];
                                        $conditionsMet = empty($conditions) || Utils::checkConditions($conditions, $attacker, $victim);
            
                                        if ($conditionsMet) {
                                            if ($initialDamage > 0) {
                                                foreach ($effects as $effect) {
                                                    if ($effect['type'] === "INCREASE_DAMAGE") {
                                                        $newDamage = $initialDamage + $effect['amount'];
                                                        $event->setBaseDamage($newDamage);
                                                    }
                                                    if ($effect['type'] === "DECREASE_DAMAGE") {
                                                        $newDamage = max(0, $initialDamage - $effect['amount']);
                                                        $event->setBaseDamage($newDamage);
                                                    }
                                                    Utils::applyPlayerEffects($attacker, $victim, $effect);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Victim item in hand
                $itemInHand = $victim->getInventory()->getItemInHand();
                foreach ($itemInHand->getEnchantments() as $enchantmentInstance) {
                    $enchantment = $enchantmentInstance->getType();
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentName = $enchantment->getName();
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
                        if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                            $enchantmentData = $enchantmentConfig[$enchantmentName];
                            $level = $enchantmentInstance->getLevel();
    
                            if ($enchantmentData['type'] === $defenseContext && isset($enchantmentData['levels']["$level"]['effects'])) {
                                $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;
    
                                if (mt_rand(1, 100) <= $chance) {
                                    $effects = $enchantmentData['levels']["$level"]['effects'];
                                    Utils::applyPlayerEffects($victim, $attacker, $effects);
                                }
                            }
                        }
                    }
                }
            }
        }
    }    
    
    public function onEntityDeath(EntityDeathEvent $event) {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();
    
        if ($cause instanceof EntityDamageByEntityEvent) {
            $attacker = $cause->getDamager();
            if ($attacker instanceof Player) {
                $hand = $attacker->getInventory()->getItemInHand();
                foreach ($hand->getEnchantments() as $enchantmentInstance) {
                    $enchantment = $enchantmentInstance->getType();
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentName = $enchantment->getName();
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
                        if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                            $enchantmentData = $enchantmentConfig[$enchantmentName];
    
                            if ($enchantmentData['type'] === 'KILL_MOB') {
                                $level = $enchantmentInstance->getLevel();
                                if (isset($enchantmentData['levels']["$level"]['effects'])) {
                                    $effects = $enchantmentData['levels']["$level"]['effects'];

                                    Utils::applyPlayerEffects($attacker, $entity, $effects, function ($formula, $level) use ($event) {
                                        $exp = $event->getXpDropAmount();
                                        $newFormula = str_replace(['{exp}', '{level}'], [$exp, $level], $formula);
                                        try {
                                            $newExp = Utils::evaluateFormula($newFormula, $level);
                                            $event->setXpDropAmount($newExp);
                                        } catch (\Throwable $e) {
                                            Loader::getInstance()->getLogger()->error("Failed to evaluate formula: " . $e->getMessage());
                                        }
                                    });
    
                                    $color = CEGroups::translateGroupToColor($enchantment->getRarity());
    
                                    if (isset($enchantmentData['settings']['showActionBar']) && $enchantmentData['settings']['showActionBar']) {
                                        $actionBarMessage = str_replace(["{enchant-color}", "{level}"], [$color . ucfirst($enchantmentName), Utils::getRomanNumeral($level)], Loader::getInstance()->getLang()->getNested("effects.used"));
                                        $attacker->sendActionBarMessage(C::colorize($actionBarMessage));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }        

    public function onEntityDamage(EntityDamageEvent $event) {
        $entity = $event->getEntity();
        
        if ($entity instanceof Player) {
            foreach ($entity->getArmorInventory()->getContents() as $item) {
                foreach ($item->getEnchantments() as $enchantmentInstance) {
                    $enchantment = $enchantmentInstance->getType();
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentName = $enchantment->getName();
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();

                        if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                            $enchantmentData = $enchantmentConfig[$enchantmentName];
                            $level = $enchantmentInstance->getLevel();

                            if ($enchantmentData['type'] === 'FALL_DAMAGE' && isset($enchantmentData['levels']["$level"]['effects'])) {
                                $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;

                                if (mt_rand(1, 100) <= $chance) {
                                    $event->cancel();
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
    
        if ($player instanceof Player) {
            $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
            foreach ($transaction->getActions() as $action) {
                if ($action instanceof SlotChangeAction) {
                    $inventory = $action->getInventory();
                    if ($inventory instanceof ArmorInventory) {
                        $newArmorPiece = $action->getTargetItem();
                        $oldArmorPiece = $action->getSourceItem();

                        // Adds Effects
                        foreach ($newArmorPiece->getEnchantments() as $enchantmentInstance) {
                            $enchantment = $enchantmentInstance->getType();
                            if ($enchantment instanceof CustomEnchantment) {
                                $enchantmentName = $enchantment->getName();

                                if (isset($enchantmentConfig[$enchantmentName])) {
                                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                                    $level = $enchantmentInstance->getLevel();

                                    if ($enchantmentData['type'] === 'EFFECT_STATIC' && isset($enchantmentData['levels']["$level"]['effects'])) {
                                        Utils::applyPlayerEffects($player, null, $enchantmentData['levels']["$level"]['effects']);
                                    }
                                }
                            }
                        }

                        // Removes Effects
                        foreach ($oldArmorPiece->getEnchantments() as $enchantmentInstance) {
                            $enchantment = $enchantmentInstance->getType();
                            if ($enchantment instanceof CustomEnchantment) {
                                $enchantmentName = $enchantment->getName();

                                if (isset($enchantmentConfig[$enchantmentName])) {
                                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                                    $level = $enchantmentInstance->getLevel();

                                    if ($enchantmentData['type'] === 'EFFECT_STATIC' && isset($enchantmentData['levels']["$level"]['effects'])) {
                                        Utils::removePlayerEffects($player, $enchantmentData['levels']["$level"]['effects']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function onPlayerHoldItem(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $previousItem = $player->getInventory()->getItemInHand();

        foreach ($item->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();

                if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                    $level = $enchantmentInstance->getLevel();

                    if ($enchantmentData['type'] === 'HELD' && isset($enchantmentData['levels']["$level"]['effects'])) {
                        Utils::applyPlayerEffects($player, null, $enchantmentData['levels']["$level"]['effects']);
                    }
                }
            } 
        }

        foreach ($previousItem->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
                if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                    $level = $enchantmentInstance->getLevel();
    
                    if ($enchantmentData['type'] === 'HELD' && isset($enchantmentData['levels']["$level"]['effects'])) {
                        Utils::removePlayerEffects($player, $enchantmentData['levels']["$level"]['effects']);
                    }
                }
            }
        }
    }
    
    private function applyEnchantmentEffects(Player $source, Player $target, Item $item, string $context): void {
        foreach ($item->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                
                if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                    $level = $enchantmentInstance->getLevel();
                    
                    if ($enchantmentData['type'] === $context && isset($enchantmentData['levels']["$level"]['effects'])) {
                        $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;
                        
                        if (mt_rand(1, 100) <= $chance) {
                            Utils::applyPlayerEffects($source, $target, $enchantmentData['levels']["$level"]['effects']);
                        }
                    }
                }
            }
        }
    }

    public function setCooldown(string $playerName, string $enchantmentName, int $time) {
        $this->cooldowns[$playerName][$enchantmentName] = time() + $time;
    }

    public function hasCooldown(string $playerName, string $enchantmentName): bool {
        if (isset($this->cooldowns[$playerName][$enchantmentName])) {
            if (time() < $this->cooldowns[$playerName][$enchantmentName]) {
                return true;
            } else {
                unset($this->cooldowns[$playerName][$enchantmentName]);
            }
        }
        return false;
    }

    public function getCooldownTime(string $playerName, string $enchantmentName): int {
        if (isset($this->cooldowns[$playerName][$enchantmentName])) {
            return max(0, $this->cooldowns[$playerName][$enchantmentName] - time());
        }
        return 0;
    }
    
}
    

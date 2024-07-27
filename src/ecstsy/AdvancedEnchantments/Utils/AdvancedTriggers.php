<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;

class AdvancedTriggers implements Listener {
    
    private static array $registeredTriggers = [];

    public static function registerTrigger(string $triggerName, array $triggerHandlers): void {
        if (is_array($triggerHandlers) && isset($triggerHandlers[0]) && isset($triggerHandlers[1])) {
            self::$registeredTriggers[$triggerName] = $triggerHandlers;
        } else {
            Loader::getInstance()->getLogger()->error("Invalid trigger handler for trigger: $triggerName");
        }
    }

    public static function handleTrigger(string $triggerName, ...$args): void {
        if (isset(self::$registeredTriggers[$triggerName])) {
            call_user_func_array(self::$registeredTriggers[$triggerName], $args);
        }
    }

    public static function init(): void {
        self::registerTrigger('ATTACK', [self::class, 'onAttack']);
        self::registerTrigger('ATTACK_MOB', [self::class, 'onAttackMob']);
    }

    public function onAttack(EntityDamageByEntityEvent $event): void {
        $attacker = $event->getDamager();
        $victim = $event->getEntity();
    
        if ($attacker instanceof Player) {
            $enchantments = Utils::getEnchantmentsForItem($attacker->getInventory()->getItemInHand());
            $context = 'ATTACK';
    
            self::applyEnchantmentEffects($attacker, $victim, $enchantments, $context);
        }
    }
    
    public function onAttackMob(EntityDamageByEntityEvent $event): void {
        $attacker = $event->getDamager();
        $victim = $event->getEntity();
     
        if ($attacker instanceof Player) {
            $enchantments = Utils::getEnchantmentsForItem($attacker->getInventory()->getItemInHand());
            $context = 'ATTACK_MOB';
    
            self::applyEnchantmentEffects($attacker, $victim, $enchantments, $context);
        }
    }   

    public static function applyEnchantmentEffects(Player $attacker, $victim, array $enchantments, string $context): void {
        $item = $attacker->getInventory()->getItemInHand();
        
        foreach ($item->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) { 
                $enchantmentName = $enchantment->getName();
                $currentLevel = $enchantmentInstance->getLevel();
                
                if (isset($enchantments[$enchantmentName]) && in_array($context, $enchantments[$enchantmentName]['type'] ?? [], true)) {
                    $enchantmentData = $enchantments[$enchantmentName];
                    
                    if (isset($enchantmentData['levels'][$currentLevel])) {
                        $levelData = $enchantmentData['levels'][$currentLevel];
                        $chance = $levelData['chance'] ?? 100;
    
                        if (mt_rand(1, 100) <= $chance) {
                            $effects = $levelData['effects'] ?? [];
                            foreach ($effects as $effect) {
                                $effectType = $effect['type'] ?? 'UNKNOWN';
                                $effectData = [
                                    'text' => $effect['text'] ?? '',
                                    'potion' => $effect['potion'] ?? '',
                                    'amplifier' => $effect['amplifier'] ?? 0,
                                    'duration' => $effect['duration'] ?? 0,
                                    'target' => $effect['target'] ?? '',
                                    'value' => $effect['value'] ?? 0,
                                    'time' => $effect['time'] ?? 0,
                                    'aoe' => $effect['aoe'] ?? '',
                                    'aoe_radius' => $effect['aoe']['radius'] ?? 0,
                                    'aoe_target' => $effect['aoe']['target'] ?? '',
                                    'amount' => $effect['amount'] ?? 0,
                                    'ticks' => $effect['ticks'] ?? 0,
                                    'health' => $effect['health'] ?? 0,
                                    'formula' => $effect['formula'] ?? '',
                                    'direction' => $effect['direction'] ?? '',
                                    'distance' => $effect['distance'] ?? 0,
                                ];
    
                                $contextData = [
                                    'attacker' => $attacker,
                                    'victim' => $victim,
                                    'attacker_name' => $attacker->getName(),
                                    'victim_name' => $victim->getName(),
                                    'enchantment_name' => $enchantmentName,
                                    'enchantment_data' => $enchantmentData,
                                    'level' => $currentLevel,
                                    'level_data' => $levelData,
                                    'effect_name' => $effectType,
                                ];
    
                                AdvancedEffect::handleEffect($effectType, $attacker, $effectData, $contextData);
                            }
                        }
                    }
                }
            }
        }
    }
    
}

<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\Armor;
use pocketmine\item\Item;
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
        self::registerTrigger('ARROW_HIT', [self::class, 'onArrowHit']);
       # self::registerTrigger("BOW_FIRE", [self::class, 'onBowFire']);
       # self::registerTrigger("BITE_HOOK", [self::class, 'onBiteHook']);
       # self::registerTrigger("BREW_POTION", [self::class, 'onBrewPotion']);
       # self::registerTrigger("CATCH_FISH", [self::class, 'onCatchFish']);
        self::registerTrigger("COMMAND", [self::class, 'onCommand']); 
       # self::registerTrigger("HOOK_ENTITY", [self::class, 'onHookEntity']); 
        self::registerTrigger("DEATH", [self::class, 'onDeath']);
        self::registerTrigger("DEFENSE", [self::class, 'onDefense']);
        self::registerTrigger("DEFENSE_MOB", [self::class, 'onDefenseMob']);
        self::registerTrigger("DEFENSE_MOB_PROJECTILE", [self::class, 'onDefenseMobProjectile']);
        self::registerTrigger("DEFENSE_PROJECTILE", [self::class, 'onDefenseProjectile']);
        self::registerTrigger("EAT", [self::class, 'onEat']);
       # self::registerTrigger("ELYTRA_FLY", [self::class, 'onElytraFly']);
        self::registerTrigger("EXPLOSION", [self::class, 'onExplosion']); 
        self::registerTrigger("FALL_DAMAGE", [self::class, 'onFallDamage']);
       # self::registerTrigger("ELYTRA_FALL_DAMAGE", [self::class, 'onElytraFallDamage']);
        self::registerTrigger("FIRE", [self::class, 'onFire']);
        self::registerTrigger("HELD", [self::class, 'onHeld']); 
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

    public function onArrowHit(ProjectileHitEvent $event): void {
        $projectile = $event->getEntity();

        if ($projectile instanceof Arrow) {
            $shooter = $projectile->getOwningEntity();

            if ($shooter instanceof Player) {
                $victim = $event->getEntity();
                if ($victim instanceof Living) {
                    $enchantments = Utils::getEnchantmentsForItem($shooter->getInventory()->getItemInHand());
                    $context = 'ARROW_HIT';

                    self::applyEnchantmentEffects($shooter, $victim, $enchantments, $context);
                }
            }
        }
    }

    public function onCommand(CommandEvent $event): void {
        // TODO
    }

    public function onDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();
        $context = "DEATH";

        if ($entity instanceof Player) {
            $enchantments = Utils::getEnchantmentsForItem($entity->getInventory()->getItemInHand());
            self::applyEnchantmentEffects($entity, $entity, $enchantments, $context);
        }

        if ($entity instanceof Living) {
            $this->applyEnchantmentsOnDeath($entity, $context);
        }
    }

    public function onDefense(EntityDamageByEntityEvent $event): void {
        $attacker = $event->getDamager();
        $victim = $event->getEntity();
    
        if ($attacker instanceof Player && $victim instanceof Living) {
            $context = 'DEFENSE';
            foreach ($victim->getArmorInventory()->getContents() as $armorItem) {
                $victimEnchantments = Utils::getEnchantmentsForItem($armorItem);
                self::applyEnchantmentEffects($attacker, $victim, $victimEnchantments, $context);
            }
        } 
    }
    

    public function onDefenseMob(EntityDamageByEntityEvent $event): void {
        $attacker = $event->getDamager();
        $victim = $event->getEntity();

        if ($victim instanceof Living && !($attacker instanceof Player)) {
            $context = 'DEFENSE_MOB';

            foreach ($victim->getArmorInventory()->getContents() as $armorItem) {
                $victimEnchantments = Utils::getEnchantmentsForItem($armorItem);
                self::applyEnchantmentEffects($attacker, $victim, $victimEnchantments, $context);
            }
        }
    }

    private function applyEnchantmentsOnDeath(Living $entity, string $context): void {
        if ($entity instanceof Player) {
            $weaponEnchantments = Utils::getEnchantmentsForItem($entity->getInventory()->getItemInHand());
            self::applyEnchantmentEffects($entity, $entity, $weaponEnchantments, $context);
        }

        foreach ($entity->getArmorInventory()->getContents() as $armorItem) {
            $armorEnchantments = Utils::getEnchantmentsForItem($armorItem);
            self::applyEnchantmentEffects($entity, $entity, $armorEnchantments, $context);
        }
    }

    public function onDefenseMobProjectile(EntityDamageByEntityEvent $event): void {
        $projectile = $event->getDamager();
        $victim = $event->getEntity();

        if ($projectile instanceof Arrow) {
            $shooter = $projectile->getOwningEntity();

            if (!($shooter instanceof Player) && $victim instanceof Living) {
                $context = 'DEFENSE_MOB_PROJECTILE';

                foreach ($victim->getArmorInventory()->getContents() as $armorItem) {
                    $victimEnchantments = Utils::getEnchantmentsForItem($armorItem);
                    self::applyEnchantmentEffects($shooter, $victim, $victimEnchantments, $context);
                }
            }
        }
    }

    public function onDefenseProjectile(EntityDamageByEntityEvent $event): void {
        $projectile = $event->getDamager();
        $victim = $event->getEntity();

        if ($projectile instanceof Arrow) {
            $shooter = $projectile->getOwningEntity();

            if ($shooter instanceof Player && $victim instanceof Living) {
                $context = 'DEFENSE_PROJECTILE';

                foreach ($victim->getArmorInventory()->getContents() as $armorItem) {
                    $victimEnchantments = Utils::getEnchantmentsForItem($armorItem);
                    self::applyEnchantmentEffects($shooter, $victim, $victimEnchantments, $context);
                }
            }
        }
    }

    public function onEat(PlayerItemConsumeEvent $event): void {
        $player = $event->getPlayer();
        $context = 'EAT';

        $item = $event->getItem();
        $enchantments = Utils::getEnchantmentsForItem($item);

        self::applyEnchantmentEffects($player, $player, $enchantments, $context);

        foreach ($player->getArmorInventory()->getContents() as $armorItem) {
            $armorEnchantments = Utils::getEnchantmentsForItem($armorItem);
            self::applyEnchantmentEffects($player, $player, $armorEnchantments, $context);
        }
    }

    public function onExplosion(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        $context = "EXPLOSION";
        $cause = $event->getCause();

        if ($entity instanceof Living) {
            if ($cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION || $cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION) {
                $armor = $entity->getArmorInventory();
                foreach ($armor->getContents() as $armorItem) {
                    $enchantments = Utils::getEnchantmentsForItem($armorItem);
                    self::applyEnchantmentEffects($entity, $entity, $enchantments, $context);
                }
            }
        }
    }

    public function onFallDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        $context = "FALL_DAMAGE";
        $cause = $event->getCause();

        if ($entity instanceof Living) {
            if ($cause === EntityDamageEvent::CAUSE_FALL) {
                $armor = $entity->getArmorInventory();
                foreach ($armor->getContents() as $armorItem) {
                    $enchantments = Utils::getEnchantmentsForItem($armorItem);
                    self::applyEnchantmentEffects($entity, $entity, $enchantments, $context);
                }
            }
        }
    }

    public function onFire(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        $context = "FIRE";
        $cause = $event->getCause();

        if ($entity instanceof Living) {
            if ($cause === EntityDamageEvent::CAUSE_FIRE || $cause === EntityDamageEvent::CAUSE_FIRE_TICK) {
                $armor = $entity->getArmorInventory();
                foreach ($armor->getContents() as $armorItem) {
                    $enchantments = Utils::getEnchantmentsForItem($armorItem);
                    self::applyEnchantmentEffects($entity, $entity, $enchantments, $context);
                }
            }
        }
    }

    public function onHeld(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $newItem = $event->getItem();
        $previousItem = $player->getInventory()->getItemInHand();
        $context = "HELD";
    
        if ($previousItem !== null && !$previousItem->isNull()) { 
            $previousItemEnchantments = Utils::getEnchantmentsForItem($previousItem);
            if (!empty($previousItemEnchantments)) {
                Utils::removeItemEffects($player, $previousItem);
            }
        }
    
        if ($newItem !== null && !$newItem->isNull() && !Utils::isArmorItem($newItem)) {  
            $newItemEnchantments = $newItem->getEnchantments();
            if (!empty($newItemEnchantments)) {
                self::applyEnchantmentEffects($player, null, $newItemEnchantments, $context);
            }
        }
    }
    
    private static function getItemForContext(Living $attacker, ?Living $victim, string $context): ?Item {
        if ($context === 'DEFENSE' || $context === 'DEFENSE_MOB' || $context === 'DEFENSE_MOB_PROJECTILE' || $context === 'DEFENSE_PROJECTILE') {
            if ($victim instanceof Living) {
                foreach ($victim->getArmorInventory()->getContents() as $armorItem) {
                    if ($armorItem instanceof Armor) {
                        return $armorItem;
                    }
                }
            }
        } elseif ($context === 'HELD') {
            if ($attacker instanceof Player) {
                return $attacker->getInventory()->getItemInHand();
            }
        } else {
            if ($attacker instanceof Player) {
                return $attacker->getInventory()->getItemInHand();
            }
        }
        return null; 
    }      

    public static function applyEnchantmentEffects(Living $attacker, ?Living $victim, array $enchantments, string $context): void {
        $config = Utils::getConfiguration("enchantments.yml")->getAll();
    
        foreach ($enchantments as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                $currentLevel = $enchantmentInstance->getLevel();
                $enchantmentData = $config[$enchantmentName];
                
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
                                'duration' => $effect['duration'] ?? 2147483647,
                                'target' => $effect['target'] ?? 'self',
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
                                'victim_name' => $victim !== null ? $victim->getName() : '',  
                                'enchantment_name' => $enchantmentName,
                                'enchantment_data' => $enchantmentData,
                                'level' => $currentLevel,
                                'level_data' => $levelData,
                                'effect_name' => $effectType,
                            ];
                            
                            $targetType = $effectData['target'] ?? 'self';

                            $targets = TargetHandler::handleTarget($targetType, $contextData, $attacker);

                            foreach ($targets as $target) {
                                AdvancedEffect::handleEffect($effectType, $target, $effectData, $contextData);
                            }
                        }
                    }
                }
            }
        }
    }
}  

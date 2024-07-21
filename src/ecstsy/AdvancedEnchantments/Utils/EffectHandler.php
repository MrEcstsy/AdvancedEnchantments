<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Durable;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;

class EffectHandler {

    public static function applyPlayerEffects(Entity $source, ?Entity $target, array $effects, ?callable $callback = null): void {
        if (empty($effects)) {
            return;
        }

        $effect = array_shift($effects);

        if (!isset($effect['type'])) {
            self::applyPlayerEffects($source, $target, $effects, $callback);
            return;
        }

        $effectHandlers = [
            'PLAY_SOUND' => 'handlePlaySound',
            'ADD_PARTICLE' => 'handleAddParticle',
            'ADD_POTION' => 'handleAddPotion',
            'REMOVE_POTION' => 'handleRemovePotion',
            'DOUBLE_DAMAGE' => 'handleDoubleDamage',
            'WAIT' => 'handleWait',
            'DO_HARM' => 'handleDoHarm',
            'MESSAGE' => 'handleMessage',
            'EFFECT_STATIC' => 'handleEffectStatic',
            'EXP' => 'handleExp',
            'PULL_AWAY' => 'handlePullAway',
            'BURN' => 'handleBurn',
            'ADD_FOOD' => 'handleAddFood',
            'REMOVE_FOOD' => 'handleRemoveFood',
            'PULL_CLOSER' => 'handlePullCloser',
            'EXTINGUISH' => 'handleExtinguish',
            'ADD_DURABILITY_ITEM' => 'handleAddDurabilityItem',
            'TELEPORT_BEHIND' => 'handleTeleportBehind',
            'ADD_HEALTH' => 'handleAddHealth',
            'CURE' => 'handleCure',
            'STEAL_HEALTH' => 'handleStealHealth',
            'BLOOD' => 'handleBlood',
        ];

        $handler = $effectHandlers[$effect['type']] ?? null;

        if ($handler && method_exists(__CLASS__, $handler)) {
            self::$handler($source, $target, $effect, $callback);
        }

        self::applyPlayerEffects($source, $target, $effects, $callback);
    }

    private static function handlePlaySound(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['sound']) && isset($effect['target']) && $effect['target'] === 'attacker' && $source instanceof Living) {
            Utils::playSound($source, $effect['sound'], $effect['volume'] ?? 1);
        }
    }

    private static function handleAddParticle(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['particle']) && $target instanceof Player) {
            Utils::spawnParticle($target->getPosition(), $effect['particle']);
        }
    }

    private static function handleAddPotion(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['potion'], $effect['duration'], $effect['amplifier'], $effect['target'])) {
            $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
            if ($potion === null) {
                throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
            }

            $entity = $effect['target'] === 'attacker' ? $source : $target;
            if ($entity instanceof Living) {
                $entity->getEffects()->add(new EffectInstance($potion, $effect['duration'], $effect['amplifier']));
            }
        }
    }

    private static function handleRemovePotion(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['potion'])) {
            $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
            if ($potion === null) {
                throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
            }

            if ($target instanceof Player) {
                $target->getEffects()->remove($potion);
            }
        }
    }

    private static function handleDoubleDamage(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        // Implement the double damage effect logic here
    }

    private static function handleWait(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['time'])) {
            $task = new ClosureTask(function() use ($source, $target, $effect): void {
                self::applyPlayerEffects($source, $target, $effect);
            });
            Loader::getInstance()->getScheduler()->scheduleDelayedTask($task, $effect['time']);
            return;
        }
    }

    private static function handleDoHarm(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['value'])) {
            $damage = Utils::parseLevel($effect['value']);

            if (isset($effect['aoe'])) {
                $radius = $effect['aoe']['radius'] ?? 2;
                $aoeTarget = $effect['aoe']['target'] ?? 'damageable';
                $center = $effect['target'] === 'victim' ? $target : $source;

                foreach ($center->getWorld()->getNearbyEntities($center->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $entity) {
                    if ($aoeTarget === 'damageable' && $entity instanceof Living && $entity !== $target) {
                        $entity->attack(new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
                    }
                }
            } else {
                if ($effect['target'] === 'victim') {
                    $target->attack(new EntityDamageEvent($target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
                } elseif ($effect['target'] === 'attacker') {
                    $source->attack(new EntityDamageEvent($source, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
                }
            }
        }
    }

    private static function handleMessage(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['text'])) {
            $message = C::colorize($effect['text']);
            if ($source instanceof Player) {
                if ($effect['target'] === 'self') {
                    $source->sendMessage($message);
                } elseif ($effect['target'] === 'victim' && $target instanceof Player) {
                    $target->sendMessage($message);
                } elseif ($effect['target'] === 'attacker' && $source instanceof Player && $target instanceof Player) {
                    $source->sendMessage($message);
                }
            }
        }
    }

    private static function handleEffectStatic(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['effect'])) {
            $potion = StringToEffectParser::getInstance()->parse($effect['effect']);
            if ($potion === null) {
                throw new \RuntimeException("Invalid potion effect '" . $effect['effect'] . "'");
            }

            if ($source instanceof Player) {
                $source->getEffects()->add(new EffectInstance($potion, 20 * 999999, $effect['amplifier'] ?? 0));
            }
        }
    }

    private static function handleExp(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['formula']) && $source instanceof Player) {
            $hand = $source->getInventory()->getItemInHand();

            foreach ($hand->getEnchantments() as $enchantmentInstance) {
                $enchantment = $enchantmentInstance->getType();
                if ($enchantment instanceof CustomEnchantment) {
                    $level = $enchantmentInstance->getLevel();
                    if ($callback !== null) {
                        $callback($effect['formula'], $level);
                    }
                }
            }
        }
    }

    private static function handlePullAway(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['distance']) && isset($effect['target'])) {
            $distance = $effect['distance'];

            if ($effect['target'] === 'victim' && $target instanceof Living && $source instanceof Living) {
                $attackerPosition = $source->getPosition();
                $targetPosition = $target->getPosition();
                $direction = $targetPosition->subtract($attackerPosition->getX(), $attackerPosition->getY(), $attackerPosition->getZ())->normalize();
                $pushVector = $direction->multiply($distance);

                $target->setMotion($pushVector);
            } elseif ($effect['target'] === 'attacker' && $source instanceof Living && $target instanceof Living) {
                $attackerPosition = $target->getPosition();
                $targetPosition = $source->getPosition();
                $direction = $targetPosition->subtract($attackerPosition->getX(), $attackerPosition->getY(), $attackerPosition->getZ())->normalize();
                $pushVector = $direction->multiply($distance);

                $source->setMotion($pushVector);
            }
        }
    }
    
    private static function handleBurn(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['time']) && isset($effect['target'])) {
            if ($effect['target'] === 'victim') {
                $source->setOnFire($effect['time']);
            } elseif ($effect['target'] === 'attacker') {
                $target->setOnFire($effect['time']);
            }
        }
    }

    private static function handleAddFood(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['amount']) && isset($effect['target'])) {
            $parse = Utils::parseLevel($effect['amount']);
            if ($effect['target'] === 'victim') {
                if ($target instanceof Player) {
                    $target->getHungerManager()->addFood($parse);
                }
            } elseif ($effect['target'] === 'attacker') {
                if ($source instanceof Player) {
                    $source->getHungerManager()->addFood($parse);
                }
            } elseif ($effect['target'] === 'self') {
                if ($source instanceof Player) {
                    $source->getHungerManager()->addFood($parse);
                }
            }
        }
    }

    private static function handleRemoveFood(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['amount']) && isset($effect['target'])) {
            $parse = Utils::parseLevel($effect['amount']);
            if ($effect['target'] === 'victim') {
                if ($target instanceof Player) {
                    $target->getHungerManager()->setFood($target->getHungerManager()->getFood() - $parse);
                }
            } elseif ($effect['target'] === 'attacker') {
                if ($source instanceof Player) {
                    $source->getHungerManager()->setFood($source->getHungerManager()->getFood() - $parse);
                }
            }
        }
    }

    private static function handleExtinguish(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['target'])) {
            if ($effect['target'] === 'victim') {
                $target->extinguish();
            } elseif ($effect['target'] === 'attacker') {
                $source->extinguish();
            }
        }
    }

    private static function handlePullCloser(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['distance']) && isset($effect['target'])) {
            $distance = $effect['distance'];
            $targetEntity = ($effect['target'] === 'victim') ? $target : $source;
    
            if ($targetEntity instanceof Entity) {
                $attackerPosition = $source->getPosition();
                $targetPosition = $targetEntity->getPosition();
                $direction = $attackerPosition->subtract($targetPosition->getX(), $targetPosition->getY(), $targetPosition->getZ())->normalize();
                $pullVector = $direction->multiply($distance);
    
                $targetEntity->setMotion($pullVector);
            }
        }
    }

    private static function handleAddDurabilityItem(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['amount']) && $source instanceof Player) {
            $item = $source->getInventory()->getItemInHand();
            if ($item instanceof Durable && $item->getDamage() > 0) {
                $newDurability = $item->getDamage() - $effect['amount'];
                $item->setDamage(max(0, $newDurability)); 
                $source->getInventory()->setItemInHand($item);
            }
        }
    }

    private static function handleTeleportBehind(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        // TODO: make teleported player face the oponent
        if (isset($effect['target'])) {
            $multiplier = 1.5; 

            if ($effect['target'] === 'victim' && $source instanceof Living && $target instanceof Living) {
                $direction = $source->getDirectionVector();
                $positionBehind = $source->getPosition()->subtract($direction->multiply($multiplier)->getX(), $direction->multiply($multiplier)->getY(), $direction->multiply($multiplier)->getZ());

                $highestY = $source->getWorld()->getHighestBlockAt((int)$positionBehind->x, (int)$positionBehind->z);
                $positionBehind->y = $highestY + 1;

                $target->teleport($positionBehind);
            } elseif ($effect['target'] === 'attacker' && $source instanceof Living && $target instanceof Living) {
                $direction = $target->getDirectionVector();
                $positionBehind = $target->getPosition()->subtract($direction->multiply($multiplier)->getX(), $direction->multiply($multiplier)->getY(), $direction->multiply($multiplier)->getZ());

                $highestY = $target->getWorld()->getHighestBlockAt((int)$positionBehind->x, (int)$positionBehind->z);
                $positionBehind->y = $highestY + 1;

                $source->teleport($positionBehind);
            }
       }
    }

    private static function handleAddHealth(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['amount']) && isset($effect['target'])) {
            $amount = Utils::parseLevel($effect['amount']); 

            if ($effect['target'] === 'victim') {
                if ($target instanceof Living) {
                    $target->setHealth($target->getHealth() + $amount);
                }
            } elseif ($effect['target'] === 'attacker') {
                if ($source instanceof Living) {
                    $source->setHealth($source->getHealth() + $amount);
                }
            } elseif ($effect['target'] === 'self') {
                if ($source instanceof Living) {
                    $source->setHealth($source->getHealth() + $amount);
                }
            }
        }
    }

    private static function handleCure(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['potion']) && isset($effect['target'])) {
            $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
            if ($potion === null) {
                throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
            }

            if ($potion->isBad()) {
                if ($effect['target'] === 'victim') {
                    if ($target instanceof Living) {
                        $target->getEffects()->remove($potion);
                    }
                } elseif ($effect['target'] === 'attacker') {
                    if ($source instanceof Living) {
                        $source->getEffects()->remove($potion);
                    }
                }
            }
        }
    }

    private static function handleAddDurabilityCurrentItem(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['amount']) && isset($effect['target'])) {

            if ($effect['target'] === 'victim') {
                if ($target instanceof Player) {
                    $item = $target->getInventory()->getItemInHand();
                    if ($item instanceof Durable) {
                        $newDurability = $item->getDamage() - $effect['amount'];
                        $item->setDamage(max(0, $newDurability)); 
                        $target->getInventory()->setItemInHand($item); 
                    }
                }
            } elseif ($effect['target'] === 'attacker') {
                if ($source instanceof Player) {
                    $item = $source->getInventory()->getItemInHand();
                    if ($item instanceof Durable) {
                        $newDurability = $item->getDamage() - $effect['amount'];
                        $item->setDamage(max(0, $newDurability)); 
                        $source->getInventory()->setItemInHand($item); 
                    }
                }
            }
        }
    }

    private static function handleStealHealth(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['amount']) && isset($effect['target'])) {
            $amount = Utils::parseLevel($effect['amount']); 
        
            if ($effect['target'] === 'attacker') {
                $currentHealth = $source->getHealth();
                $newHealth = max(0, min($currentHealth + $amount, $source->getMaxHealth()));
                $source->setHealth($newHealth);
        
                $currentVictimHealth = $target->getHealth();
                $newVictimHealth = max(0, $currentVictimHealth - $amount);
                $target->setHealth($newVictimHealth);
            } elseif ($effect['target'] === 'victim') {
                $currentHealth = $target->getHealth();
                $newHealth = max(0, min($currentHealth + $amount, $target->getMaxHealth()));
                $target->setHealth($newHealth);
        
                $currentAttackerHealth = $source->getHealth();
                $newAttackerHealth = max(0, $currentAttackerHealth - $amount);
                $source->setHealth($newAttackerHealth);
            }
        }
    }

    private static function handleBlood(Entity $source, ?Entity $target, array $effect, ?callable $callback = null): void {
        if (isset($effect['target'])) {
            if ($effect['target'] === 'attacker') {
                $source->getWorld()->addParticle($source->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
            } elseif ($effect['target'] === 'victim') {
                $target->getWorld()->addParticle($target->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
            }
        }
    }
}
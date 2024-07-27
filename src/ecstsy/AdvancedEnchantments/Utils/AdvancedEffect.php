<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\libs\DaPigGuy\libPiggyEconomy\providers\EconomyProvider;
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
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;

class AdvancedEffect {

    private static array $effects = [];

    public static function registerEffect(string $effectName, callable $handler): void {
        self::$effects[$effectName] = $handler;
    }

    public static function handleEffect(string $effectName, Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['aoe']['radius']) && isset($effectData['aoe']['target'])) {
            $radius = (int) $effectData['aoe']['radius'];
            $targetType = (string) $effectData['aoe']['target'];
            $entities = TargetHandler::getAOEEntities($player->getWorld(), $radius, $targetType, $player);
    
            foreach ($entities as $entity) {
                self::applyEffect($effectName, $entity, $effectData, $contextData);
            }
        } else {
            self::applyEffect($effectName, $player, $effectData, $contextData);
        }
    }     
    
    private static function applyEffect(string $effectName, $entity, array $effectData, array $contextData): void {
        if (isset(self::$effects[$effectName])) {
            $handler = self::$effects[$effectName];
            if (is_callable($handler)) {
                call_user_func($handler, $entity, $effectData, $contextData);
            } else {
                Loader::getInstance()->getLogger()->warning("Handler for effect '$effectName' is not callable.");
            }
            $handler($entity, $effectData, $contextData);
        } else {
            $text = $effectData['text'] ?? 'No Text Provided';
            $trigger = $contextData['trigger'] ?? 'No Trigger Provided';
            $entityName = $entity instanceof Player ? $entity->getName() : 'Unknown';
    
            $errorMessage = "Failed to activate effects as advancedEffect is null or invalid: '{$effectName}', " .
                            "{$effectName}: {$text}&r&7, trigger: {$trigger}, entity: {$entityName}";
            self::handleError($entity, $effectName, $errorMessage);
        }
    }

    public static function init(): void {
        $effects = [
            "ACTION_BAR" => "handleActionBar",
            "ADD_DURABILITY_CURRENT_ITEM" => "handleAddDurabilityCurrentItem",
            "ADD_DURABILITY_ARMOR" => "handleAddDurabilityArmor",
            "ADD_DURABILITY_ITEM" => "handleAddDurabilityItem",
            "ADD_FOOD" => "handleAddFood",
            "ADD_HEALTH" => "handleAddHealth",
            "ADD_MONEY" => "handleAddMoney",
            "AIR" => "handleAir",
            "BLEED" => "handleBleed",
            "BLOOD" => "handleBlood",
            "BOOST" => "handleBoost",
            "BURN" => "handleBurn",
            "CACTUS" => "handleCactus",
            "CANCEL_EVENT" => "handleCancelEvent",
            "CANCEL_USE" => "handleCancelUse",
            "CONSOLE_COMMAND" => "handleConsoleCommand",
            "CURE" => "handleCure",
            "CURE_PERMANENT" => "handleCurePermanent",
            "DECREASE_DAMAGE" => "handleDecreaseDamage",
            "DISABLE_ACTIVATION" => "handleDisableActivation",
            "DISABLE_KNOCKBACK" => "handleDisableKnockback",
            "DISARM" => "handleDisarm",
            "DO_HARM" => "handleDoHarm",
            "REMOVE_HEALTH" => "handleRemoveHealth",
            "REMOVE_HEALTH_DAMAGE" => "handleRemoveHealthDamage",
            "REMOVE_HEALTH_DAMAGE_TOTEM" => "handleRemoveHealthDamageTotem", // Remove health from entity while allowing the use of totem
            "DOUBLE_DAMAGE" => "handleDoubleDamage",
            "DROP_HEAD" => "handleDropHead",
            "EXP" => "handleExp",
            "EXPLODE" => "handleExplode",
            "EXTINGUISH" => "handleExtinguish",
            "FIREBALL" => "handleFireball",
            "FLY" => "handleFly",
            "FREEZE" => "handleFreeze",
            "GUARD" => "handleGuard",
            "GIVE_ITEM" => "handleGiveItem",
            "HALF_DAMAGE" => "handleHalfDamage",
            "IGNORE_ARMOR_DAMAGE" => "handleIgnoreArmorDamage",
            "IGNORE_ARMOR_PROTECTION" => "handleIgnoreArmorProtection",
            "INCREASE_DAMAGE" => "handleIncreaseDamage",
            "INVINCIBLE" => "handleInvincible",
            "KILL" => "handleKill",
            "KEEP_ON_DEATH" => "handleKeepOnDeath",
            "LIGHTNING" => "handleLightning",
            "MESSAGE" => "handleMessage",
            "MORE_DROPS" => "handleMoreDrops",
            "NEGATE_DAMAGE" => "handleNegateDamage",
            "PARTICLE" => "handleParticle",
            "PARTICLE_LINE" => "handleParticleLine",
            "PERMISSION" => "handlePermission", // toggle a players permission
            "PLAYER_COMMAND" => "handlePlayerCommand", // run cmd thru player
            "POTION" => "handlePotion",
            "POTION_OVERRIDE" => "handlePotionOverride",
            "PULL_AWAY" => "handlePullAway",
            "PULL_CLOSER" => "handlePullCloser",
            "PUMPKIN" => "handlePumpkin",
            "REMOVE_ARMOR" => "handleRemoveArmor",
            "REMOVE_RANDOM_ARMOR" => "handleRemoveRandomArmor",
            "REMOVE_MONEY" => "handleRemoveMoney",
            "REPAIR" => "handleRepair",
            "REVIVE" => "handleRevive",
            "SET_AIR" => "handleSetAir",
            "SHUFFLE_HOTBAR" => "handleShuffleHotbar",
            "SPAWN_ARROWS" => "handleSpawnArrows", // Spawn flood of arrows from above
            "SPAWN_BLOCKS" => "handleSpawnBlocks", // Spawn falling blocks from above player and to cause damage
            "STEAL_EXP" => "handleStealExp",
            "STEAL_HEALTH" => "handleStealHealth",
            "STEAL_MONEY" => "handleStealMoney",
            "STOP_KNOCKBACK" => "handleStopKnockback",
            "SUBTITLE" => "handleSubtitle",
            "TAKE_AWAY" => "handleTakeAway",
            "TELEPORT_BEHIND" => "handleTeleportBehind",
            "TELEPORT" => "handleTeleport",
            "TITLE" => "handleTitle",
            "TNT" => "handleTnt",
            "TP_DROPS" => "handleTpDrops",
            "DELETE_ITEM" => "handleDeleteItem",
            "SPAWN_ENTITY" => "handleSpawnEntity",
            "PROJECTILE" => "handleProjectile",
            "WEB_WALKER" => "handleWebWalker",
            "LAVA_WALKER" => "handleLavaWalker",
            "WATER_WALKER" => "handleWaterWalker",
            "WALK_SPEED" => "handleWalkSpeed",
            "DROP_HELD_ITEM" => "handleDropHeldItem",
            "SCREEN_FREEZE" => "handleScreenFreeze",
            "WAIT" => "handleWait",
            "ADD_ENCHANT" => "handleAddEnchant",
            "REMOVE_ENCHANT" => "handleRemoveEnchant",
            "ADD_SOULS" => "handleAddSouls",
            "REMOVE_SOULS" => "handleRemoveSouls",
        ];

        foreach ($effects as $effectName => $effectHandler) {
            if (method_exists(self::class, $effectHandler)) {
                self::registerEffect($effectName, [self::class, $effectHandler]);
            } else {
                throw new \RuntimeException("Effect handler {$effectHandler} does not exist.");
            }
        }
        
    }

    private static function handleError(Entity $entity, string $effectType, string $errorMessage): void {
        if ($entity instanceof Player) {
            $message = C::colorize("&r&4Failed to activate effect '&f{$effectType}&r&4'");
            $additionalInfo = C::colorize("&r&cAdditional Information: &7{$errorMessage}");
            $entity->sendMessage($message);
            $entity->sendMessage($additionalInfo);
        }
    }

    public static function replaceTags(string $text, array $data = []): string {
        $tags = [
            '{attacker_name}' => $data['attacker_name'] ?? 'Unknown',
            '{victim_name}' => $data['victim_name'] ?? 'Unknown',
            '{block_type}' => $data['block_type'] ?? 'BlockTypePlaceholder',
            '{system_time}' => (string)microtime(true),
            '{exp}' => $data['exp'] ?? '0',
            '{player_name}' => $data['player_name'] ?? 'Unknown',
            '{damage}' => $data['damage'] ?? '0',
            '{raw_damage}' => $data['raw_damage'] ?? '0',
            '{damage_cause}' => $data['damage_cause'] ?? 'Unknown',
            '{random}' => $data['random'] ?? '0',
            '{is_removed}' => $data['is_removed'] ?? 'false',
        ];

        return str_replace(array_keys($tags), array_values($tags), $text);
    }

    public static function handleActionBar(Entity $entity, array $effectData, array $contextData): void {
        $text = self::replaceTags($effectData['text'] ?? '', $contextData);
        if ($entity instanceof Player) {
            $entity->sendActionBarMessage(C::colorize($text));
        }
    }

    public static function handleAddDurabilityCurrentItem(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['amount'])) {
            $item = $player->getInventory()->getItemInHand();
            if ($item instanceof Durable && $item->getDamage() > 0) {
                $newDurability = $item->getDamage() - $effectData['amount'];
                $item->setDamage(max(0, $newDurability)); 
                $player->getInventory()->setItemInHand($item);
            }
        }    
    }

    public static function handleAddDurabilityArmor(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['amount'])) {
            $amount = (int) $effectData['amount'];

            $armorInventory = $player->getArmorInventory();
            for ($i = 0; $i < $armorInventory->getSize(); $i++) {
                $item = $armorInventory->getItem($i);
                if ($item instanceof Durable && $item->getDamage() > 0) {
                    $newDurability = $item->getDamage() - $amount;
                    $item->setDamage(max(0, $newDurability));
                    $armorInventory->setItem($i, $item);
                }
            }
        }
    }

    public static function handleAddDurabilityItem(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['amount']) && $player instanceof Player) {
            $item = $player->getInventory()->getItemInHand();
            if ($item instanceof Durable && $item->getDamage() > 0) {
                $newDurability = $item->getDamage() - $effectData['amount'];
                $item->setDamage(max(0, $newDurability)); 
                $player->getInventory()->setItemInHand($item);
            }
        }
    }

    public static function handleAddFood(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['amount']) && isset($effectData['target'])) {
            $amount = Utils::parseLevel($effectData['amount']);
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $player);
    
            if ($target[0] instanceof Player) {
                $hungerManager = $target[0]->getHungerManager();
                $hungerManager->addFood($amount);
            } 
        }
    }
    
    public static function handleAddHealth(Living $player, array $effectData, array $contextData): void {
        if (isset($effectData['health']) && isset($effectData['target'])) {
            $amount = Utils::parseLevel($effectData['health']);
            
            $player->setHealth($player->getHealth() + $amount);
        }
    }

    public static function handleAddMoney(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['amount']) && isset($effectData['target'])) {
            $amount = Utils::parseLevel($effectData['amount']);
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $player);
    
            if ($target[0] instanceof Player) {
                Loader::getInstance()->economyProvider->giveMoney($target[0], $amount);
            } 
        }
    }

    public static function handleAir(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['amount'])) {
            $amount = Utils::parseLevel($effectData['amount']);

            if ($entity instanceof Living) {
                $entity->setAirSupplyTicks($entity->getAirSupplyTicks() + $amount);
            }
        }
    }

    public static function handleBleed(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['ticks'])) {

        }
    }

    public static function handleBlood(Entity $entity, array $effectData, array $contextData): void {
        $entity->getWorld()->addParticle($entity->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
    }

    public static function handleBoost(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['amount']) && isset($effectData['direction'])) {
            $amount = Utils::parseLevel($effectData['amount']);
            $direction = strtolower($effectData['direction']);
    
            if ($entity instanceof Player) {
                $vector = $entity->getLocation()->asVector3();
    
                switch ($direction) {
                    case 'up':
                        $vector->y += $amount;
                        break;
                    case 'down':
                        $vector->y -= $amount;
                        break;
                    case 'left':
                        $vector->x -= $amount;
                        break;
                    case 'right':
                        $vector->x += $amount;
                        break;
                    case 'forward':
                        $vector->z += $amount;
                        break;
                    case 'backward':
                        $vector->z -= $amount;
                        break;
                    default:
                        Loader::getInstance()->getLogger()->warning("Invalid direction specified: $direction");
                        $entity->sendMessage("Invalid direction specified: $direction");
                        return;
                }
    
                $entity->setMotion($entity->getMotion()->multiply($amount));
                $entity->sendMessage("Boosted $direction by $amount blocks.");
            } else {
                Loader::getInstance()->getLogger()->warning("Entity is not a player and cannot be boosted.");
            }
        } else {
            Loader::getInstance()->getLogger()->warning("Amount or direction not set in effectData.");
        }
    }
      

    public static function handleBurn(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['time']) && isset($effectData['target'])) {
            $time = Utils::parseLevel($effectData['time']);
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $player);
            
            if ($target[0] instanceof Player) {
                $target[0]->setOnFire($time);
            } 
        }
    }

    public static function handleCactus(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleCancelEvent(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleCancelUse(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleConsoleCommand(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleCure(Living $entity, array $effectData, array $contextData): void {
        if (isset($effect['potion']) && isset($effect['target'])) {
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $entity);

            $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
            if ($potion === null) {
                throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
            }

            if ($potion->isBad()) {
                if ($target[0] instanceof Living) {
                    $target[0]->getEffects()->remove($potion);
                }
            }
        }
    }

    public static function handleCurePermanent(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDecreaseDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDisableActivation(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDisableKnockback(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDisarm(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDoHarm(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['amount'])) {
            $amount = Utils::parseLevel($effectData['amount']);
            $targetType = $effectData['target'] ?? 'self';
            $targets = TargetHandler::handleTarget($targetType, $contextData, $entity);
    
            foreach ($targets as $target) {
                if ($target instanceof Living) {
                    $target->attack(new EntityDamageEvent($target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $amount));
                }
            }
        }
    }    
    
    public static function handleRemoveHealth(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['health'])) {
            $health = Utils::parseLevel($effectData['health']);

            $entity->setHealth($entity->getHealth() - $health);
        }
    }

    public static function handleRemoveHealthDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleRemoveHealthDamageTotem(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDoubleDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDropHead(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleExp(Player $player, array $effectData, array $contextData, ?callable $callback = null): void {
        if (isset($effect['formula'])) {
            $hand = $player->getInventory()->getItemInHand();

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

    public static function handleExplode(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleExtinguish(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['target'])) {
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $entity);

            if ($target[0] instanceof Living) {
                if ($target[0]->isOnFire()) {
                    $target[0]->extinguish();
                }
            }
        }
    }

    public static function handleFireball(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleFly(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleFreeze(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['ticks'])) {
            $amount = Utils::parseLevel($effectData['ticks']);

            $entity->setNoClientPredictions(true);

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function () use ($entity): void {
                    if ($entity instanceof Player) {
                        $entity->setNoClientPredictions(false);
                        $entity->sendMessage("Freeze effect ended.");
                    }
                }
            ), $amount);
        }
    }

    public static function handleGuard(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleGiveItem(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleHalfDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleIgnoreArmorDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleIgnoreArmorProtection(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleIncreaseDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleInvincible(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleKill(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleKeepOnDeath(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleLightning(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleMessage(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['text'])) {
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $entity);

            if ($target[0] instanceof Player) {
                $text = self::replaceTags($effectData['text'] ?? '', $contextData);
                $target[0]->sendMessage(C::colorize($text));

            }
        }
    }

    public static function handleMoreDrops(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleNegateDamage(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleParticle(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleParticleLine(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handlePermission(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handlePlayerCommand(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handlePotion(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['potion']) && isset($effectData['level']) && isset($effectData['duration'])) {
            $potion = StringToEffectParser::getInstance()->parse($effectData['potion']);

            if ($potion === null) {
                Loader::getInstance()->getLogger()->warning("Invalid parsed potion for potion effect: {$effectData['potion']}");
            }

            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $entity);

            if ($target[0] instanceof Living) {
                $target[0]->getEffectManager()->add(new EffectInstance($potion, $effectData['duration'], $effectData['level']));
            }
        }
    }

    public static function handlePotionOverride(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handlePullAway(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['distance']) && isset($effectData['target'])) {
            $distance = $effectData['distance'];
            $targetType = $effectData['target'] ?? 'self';
            $targets = TargetHandler::handleTarget($targetType, $contextData, $entity);
    
            if (!empty($targets) && $targets[0] instanceof Living) {
                $target = $targets[0];
                $entityPosition = $entity->getPosition();
                $targetPosition = $target->getPosition();
                $direction = $targetPosition->subtract($entityPosition->getX(), $entityPosition->getY(), $entityPosition->getZ())->normalize();
                $pushVector = $direction->multiply($distance);
    
                $target->setMotion($pushVector);
            }
        }
    }    

    public static function handlePullCloser(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['distance']) && isset($effectData['target'])) {
            $distance = $effectData['distance'];
            $targetType = $effectData['target'] ?? 'self';
            $targets = TargetHandler::handleTarget($targetType, $contextData, $entity);

            if (!empty($targets) && $targets[0] instanceof Living) {
                $target = $targets[0];
                $attackerPosition = $entity->getPosition();
                $targetPosition = $target->getPosition();
                $direction = $attackerPosition->subtract($targetPosition->getX(), $targetPosition->getY(), $targetPosition->getZ())->normalize();
                $pullVector = $direction->multiply($distance);
    
                $target->setMotion($pullVector);
            }
        }
    }

    public static function handlePumpkin(Entity $entity, array $effectData, array $contextData): void {
        if ($entity instanceof Player) {
            // is this even possible in pm w/o replacing the helmet?
        }
    }

    public static function handleRemoveArmor(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleRemoveRandomArmor(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleRemoveMoney(Player $player, array $effectData, array $contextData): void {
        if (isset($effectData['amount']) && isset($effectData['target'])) {
            $amount = Utils::parseLevel($effectData['amount']);
            $targetType = $effectData['target'] ?? 'self';
            $target = TargetHandler::handleTarget($targetType, $contextData, $player);
    
            if ($target[0] instanceof Player) {
                Loader::getInstance()->economyProvider->takeMoney($target[0], $amount);
            } 
        }
    }

    public static function handleRepair(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleRevive(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleSetAir(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['amount'])) {
            if ($entity instanceof Living) {
                $entity->setAirSupplyTicks($effectData['amount']);
            }
        }
    }

    public static function handleShuffleHotbar(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleSpawnArrows(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }


    public static function handleSpawnBlocks(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }


    public static function handleStealExp(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleStealHealth(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['amount']) && isset($effectData['target'])) {
            $amount = Utils::parseLevel($effectData['amount']);
            $targetType = $effectData['target'];
            $targets = TargetHandler::handleTarget($targetType, $contextData, $entity);
    
            if (!empty($targets) && $targets[0] instanceof Living) {
                $target = $targets[0];
    
                if ($targetType === 'attacker') {
                    $source = $entity;
                } else {
                    $source = $target;
                    $target = $entity;
                }
    
                $currentSourceHealth = $source->getHealth();
                $newSourceHealth = max(0, min($currentSourceHealth + $amount, $source->getMaxHealth()));
                $source->setHealth($newSourceHealth);
    
                $currentTargetHealth = $target->getHealth();
                $newTargetHealth = max(0, $currentTargetHealth - $amount);
                $target->setHealth($newTargetHealth);
            } else {
                Loader::getInstance()->getLogger()->warning("Target is not a living entity or is missing.");
            }
        } else {
            Loader::getInstance()->getLogger()->warning("Amount or target not set in effectData.");
        }
    }    

    public static function handleStealMoney(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleStopKnockback(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleSubtitle(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['text'])) {
            $text = self::replaceTags($effectData['text'] ?? '', $contextData);
            if ($entity instanceof Player) {
                $entity->sendSubTitle(C::colorize($text));
            }
        }
    }

    public static function handleTakeAway(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleTeleportBehind(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['target'])) {
            $multiplier = 1.5; 
            $targetType = $effectData['target'];
            $targets = TargetHandler::handleTarget($targetType, $contextData, $entity);
    
            if (!empty($targets) && $targets[0] instanceof Living) {
                $target = $targets[0];
    
                if ($targetType === 'victim') {
                    $source = $entity;
                } else {
                    $source = $target;
                    $target = $entity;
                }
    
                $direction = $source->getDirectionVector();
                $positionBehind = $source->getPosition()->subtract(
                    $direction->multiply($multiplier)->getX(),
                    $direction->multiply($multiplier)->getY(),
                    $direction->multiply($multiplier)->getZ()
                );
    
                $highestY = $source->getWorld()->getHighestBlockAt((int) $positionBehind->x, (int) $positionBehind->z);
                $positionBehind->y = $highestY + 1;
    
                $target->teleport($positionBehind);
                $target->sendMessage("You have been teleported behind the entity.");
            } else {
                Loader::getInstance()->getLogger()->warning("Target is not a living entity or is missing.");
            }
        } else {
            Loader::getInstance()->getLogger()->warning("Target not set in effectData.");
        }
    }    

    public static function handleTeleport(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleTitle(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['text'])) {
            if ($entity instanceof Player) {
                $entity->sendTitle(C::colorize($effectData['text']));
            }
        }
    }

    public static function handleTnt(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleTpDrops(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDeleteItem(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleSpawnEntity(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleProjectile(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleWebWalker(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleLavaWalker(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleWaterWalker(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleWalkSpeed(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleDropHeldItem(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleScreenFreeze(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleWait(Entity $entity, array $effectData, array $contextData): void {
        if (isset($effectData['time'])) {
            $time = Utils::parseLevel($effectData['time']);
            $task = new ClosureTask(function() use ($entity, $effectData, $contextData): void {
                if (isset($contextData['effectName'])) {
                    $effectName = $contextData['effectName'];
                    if (isset(self::$effects[$effectName])) {
                        $handler = self::$effects[$effectName];
                        $handler($entity, $effectData, $contextData);
                    } else {
                        Loader::getInstance()->getLogger()->warning("Effect handler for $effectName does not exist.");
                    }
                } else {
                    Loader::getInstance()->getLogger()->warning("Effect name not provided in contextData.");
                }
            });
            Loader::getInstance()->getScheduler()->scheduleDelayedTask($task, $time);
        } else {
            Loader::getInstance()->getLogger()->warning("Time not set in effectData.");
        }
    }
    
    public static function handleAddEnchant(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleRemoveEnchant(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleAddSouls(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

    public static function handleRemoveSouls(Entity $entity, array $effectData, array $contextData): void {
        // Method implementation is empty
    }

}

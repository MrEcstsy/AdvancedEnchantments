<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantments;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Tasks\ApplyBlockBreakEffectTask;
use ecstsy\AdvancedEnchantments\Utils\EffectHandler;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\block\Farmland;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
class EnchantmentListener implements Listener {

    private array $cooldowns = [];

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();

        if ($packet instanceof MobEquipmentPacket) {
            CustomEnchantments::filter($packet->item->getItemStack());
        }
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
        foreach ($player->getArmorInventory()->getContents() as $slot => $content) {
            if (!$content->isNull()) {
                Utils::applyItemEffects($player, $content, $enchantmentConfig);
            }
        }
    
        $this->addInventoryListeners($player, $enchantmentConfig);
    }
    

    private function addInventoryListeners(Player $player, array $enchantmentConfig): void
    {
        $onSlot = function (Inventory $inventory, int $slot, Item $oldItem) use ($enchantmentConfig): void {
            if ($inventory instanceof ArmorInventory) {
                $holder = $inventory->getHolder();
                if ($holder instanceof Player) {
                    $newItem = $inventory->getItem($slot);
    
                    if (!$oldItem->equals($newItem, false)) {
                        if (!$oldItem->isNull()) {
                            Utils::removeItemEffects($holder, $oldItem, $enchantmentConfig);
                        }
    
                        if (!$newItem->isNull()) {
                            Utils::applyItemEffects($holder, $newItem, $enchantmentConfig);
                        }
                    }
                }
            }
        };
    
        $onContent = function (Inventory $inventory, array $oldContents) use ($onSlot): void {
            foreach ($oldContents as $slot => $oldItem) {
                if (!($oldItem ?? VanillaItems::AIR())->equals($inventory->getItem($slot), false)) {
                    $onSlot($inventory, $slot, $oldItem);
                }
            }
        };
    
        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener($onSlot, $onContent));
    }
    

    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
    
        if ($player instanceof Player) {
            $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
            foreach ($transaction->getActions() as $action) {
                if ($action instanceof SlotChangeAction) {
                    $inventory = $action->getInventory();
    
                    if ($inventory instanceof ArmorInventory) {
                        $newItem = $action->getTargetItem();
                        $oldItem = $action->getSourceItem();
    
                        if (!$oldItem->isNull()) {
                            Utils::removeItemEffects($player, $oldItem, $enchantmentConfig);
                        }
    
                        if (!$newItem->isNull()) {
                            Utils::applyItemEffects($player, $newItem, $enchantmentConfig);
                        }
                    }
                }
            }
        }
    }

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

                        foreach ($enchantmentData['type'] as $type) {
                            if ($type === 'MINING') {
                                $playerName = $player->getName();
                                $level = $enchantmentInstance->getLevel();

                                if ($this->hasCooldown($playerName, $enchantmentName)) {
                                    continue;
                                }

                                if (isset($enchantmentData['levels'][$level])) {
                                    $chance = $enchantmentData['levels'][$level]['chance'] ?? 100;
                                    if (mt_rand(1, 100) <= $chance) {
                                        Utils::applyBlockEffects($player, $block, $enchantmentData['levels'][$level]['effects']);

                                        $cooldown = $enchantmentData['levels'][$level]['cooldown'] ?? 0;
                                        $this->setCooldown($playerName, $enchantmentName, $cooldown);

                                        $color = CEGroups::translateGroupToColor($enchantment->getRarity());

                                        if (isset($enchantmentData['settings']['showActionBar']) && $enchantmentData['settings']['showActionBar']) {
                                            $actionBarMessage = str_replace(["{enchant-color}", "{level}"], [$color . ucfirst($enchantmentName), Utils::getRomanNumeral($level)], Loader::getInstance()->getLang()->getNested("effects.used"));
                                            $player->sendActionBarMessage(C::colorize($actionBarMessage));
                                        }
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
                                    foreach ($enchantmentData['type'] as $type) {
                                        if ($type === 'PLANT_SEEDS') {
                                            $radius = $effect['radius'] ?? 1;
                                            $seedType = $effect['seed-type'] ?? null;
                                            Utils::plantSeeds($player, $block, $radius, $seedType);

                                            $color = CEGroups::translateGroupToColor($enchantment->getRarity());

                                            if (isset($enchantmentData['settings']['showActionBar']) && $enchantmentData['settings']['showActionBar']) {
                                                $actionBarMessage = str_replace(["{enchant-color}", "{level}"], [$color . ucfirst($enchantmentName), Utils::getRomanNumeral($level)], Loader::getInstance()->getLang()->getNested("effects.used"));
                                                $player->sendActionBarMessage(C::colorize($actionBarMessage));
                                            }
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
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $block = $event->getBlock();

        foreach ($item->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();

            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                    $enchantmentData = $enchantmentConfig[$enchantmentName];

                    foreach ($enchantmentData['type'] as $type) {
                        if ($type === 'MINING') {
                            $playerName = $player->getName();
                            $level = $enchantmentInstance->getLevel();

                            if ($this->hasCooldown($playerName, $enchantmentName)) {
                                continue;
                            }

                            if (isset($enchantmentData['levels'][$level])) {
                                $chance = $enchantmentData['levels'][$level]['chance'] ?? 100;
                                if (mt_rand(1, 100) <= $chance) {
                                    foreach ($enchantmentData['levels'][$level]['effects'] as $effect) {
                                        if ($effect['type'] === 'BREAK_BLOCK') {
                                            $radius = $effect['radius'] ?? 1;
                                            $target = $effect['target'] ?? null;
                                            
                                            if ($target !== null) {
                                                $targetBlock = Utils::getStringToBlock($target); 
                                                if ($block->getTypeId() !== $targetBlock->getTypeId()) {
                                                    continue; 
                                                }
                                            }

                                            $task = new ApplyBlockBreakEffectTask(
                                                $player->getName(),
                                                $player->getWorld()->getFolderName(),
                                                [
                                                    $block->getPosition()->getX(),
                                                    $block->getPosition()->getY(),
                                                    $block->getPosition()->getZ()
                                                ],
                                                $radius
                                            );

                                            Server::getInstance()->getAsyncPool()->submitTask($task);
                                        }
                                    }

                                    Utils::applyBlockEffects($player, $block, $enchantmentData['levels'][$level]['effects']);

                                    $cooldown = $enchantmentData['levels'][$level]['cooldown'] ?? 0;
                                    $this->setCooldown($playerName, $enchantmentName, $cooldown);

                                    $color = CEGroups::translateGroupToColor($enchantment->getRarity());

                                    if (isset($enchantmentData['settings']['showActionBar']) && $enchantmentData['settings']['showActionBar']) {
                                        $actionBarMessage = str_replace(["{enchant-color}", "{level}"], [$color . ucfirst($enchantmentName), Utils::getRomanNumeral($level)], Loader::getInstance()->getLang()->getNested("effects.used"));
                                        $player->sendActionBarMessage(C::colorize($actionBarMessage));
                                    }
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
    
                            $typeMatches = false;
                            foreach ($enchantmentData['type'] as $type) {
                                if ($type === 'KILL_MOB') {
                                    $typeMatches = true;
                                    break;
                                }
                            }
                            
                            if ($enchantmentData['type'] === 'KILL_MOB') {
                                $level = $enchantmentInstance->getLevel();
                                if ($typeMatches && isset($enchantmentData['levels']["$level"]['effects'])) {
                                    $effects = $enchantmentData['levels']["$level"]['effects'];

                                    EffectHandler::applyPlayerEffects($attacker, $entity, $effects, function ($formula, $level) use ($event) {
                                        $exp = $event->getXpDropAmount();
                                        $newFormula = str_replace(['{exp}', '{level}'], [$exp, $level], $formula);
                                        try {
                                            $newExp = Utils::evaluateFormula($newFormula, $level);
                                            $event->setXpDropAmount($newExp);
                                        } catch (\Throwable $e) {
                                            Loader::getInstance()->getLogger()->error("Failed to evaluate formula: " . $e->getMessage());
                                        }
                                    });

                                    if (isset($enchantmentData['levels']["$level"]['effects'])) {
                                        foreach ($effects as $effect) {
                                            if ($effect['type'] === 'TP_DROPS') {
                                                foreach ($entity->getDrops() as $drop) {
                                                    if ($attacker->getInventory()->canAddItem($drop)) {
                                                        $attacker->getInventory()->addItem($drop);
                                                    } else {
                                                        $attacker->getWorld()->dropItem($attacker->getPosition(), $drop);
                                                    }
                                                }
                                                $event->setDrops([]);
                                            }
                                        }
                                    }

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
                                    EffectHandler::applyPlayerEffects($attacker, $victim, $effects);
                                }
                            }
                        }
                    }
                }
            }
    
            if ($attacker instanceof Player) {
                $itemInHand = $attacker->getInventory()->getItemInHand();
                $isProjectileAttack = $event->getCause() === EntityDamageByEntityEvent::CAUSE_PROJECTILE;
    
                foreach ($itemInHand->getEnchantments() as $enchantmentInstance) {
                    $enchantment = $enchantmentInstance->getType();
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentName = $enchantment->getName();
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                        
                        if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                            $enchantmentData = $enchantmentConfig[$enchantmentName];
                            $level = $enchantmentInstance->getLevel();
    
                            $typeMatched = false;
                            if (is_array($enchantmentData['type'])) {
                                foreach ($enchantmentData['type'] as $type) {
                                    if (($type === 'ATTACK' || $type === 'ATTACK_MOB') && !$isProjectileAttack) {
                                        $typeMatched = true;
                                        break;
                                    }
                                    if (($type === 'SHOOT' || $type === 'SHOOT_MOB') && $isProjectileAttack) {
                                        $typeMatched = true;
                                        break;
                                    }
                                }
                            } else {
                                if (($enchantmentData['type'] === 'ATTACK' || $enchantmentData['type'] === 'ATTACK_MOB') && !$isProjectileAttack) {
                                    $typeMatched = true;
                                }
                                if (($enchantmentData['type'] === 'SHOOT' || $enchantmentData['type'] === 'SHOOT_MOB') && $isProjectileAttack) {
                                    $typeMatched = true;
                                }
                            }
    
                            if (!$typeMatched) {
                                continue;
                            }
    
                            if (isset($enchantmentData['levels']["$level"]['effects'])) {
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
    
                                            EffectHandler::applyPlayerEffects($attacker, $victim, $effects);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif ($attacker instanceof Projectile) {
                $shooter = $attacker->getOwningEntity();
                if ($shooter instanceof Player) {
                    $itemInHand = $shooter->getInventory()->getItemInHand();
                    foreach ($itemInHand->getEnchantments() as $enchantmentInstance) {
                        $enchantment = $enchantmentInstance->getType();
                        if ($enchantment instanceof CustomEnchantment) {
                            $enchantmentName = $enchantment->getName();
                            $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                            
                            if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                                $enchantmentData = $enchantmentConfig[$enchantmentName];
                                $level = $enchantmentInstance->getLevel();
    
                                $typeMatched = false;
                                if (is_array($enchantmentData['type'])) {
                                    foreach ($enchantmentData['type'] as $type) {
                                        if ($type === 'SHOOT' || $type === 'SHOOT_MOB') {
                                            $typeMatched = true;
                                            break;
                                        }
                                    }
                                } else {
                                    if ($enchantmentData['type'] === 'SHOOT' || $enchantmentData['type'] === 'SHOOT_MOB') {
                                        $typeMatched = true;
                                    }
                                }
    
                                if (!$typeMatched) {
                                    continue;
                                }
    
                                if (isset($enchantmentData['levels']["$level"]['effects'])) {
                                    $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;
    
                                    if (mt_rand(1, 100) <= $chance) {
                                        $effects = $enchantmentData['levels']["$level"]['effects'];
                                        $conditions = $enchantmentData['levels']["$level"]['conditions'] ?? [];
                                        $conditionsMet = empty($conditions) || Utils::checkConditions($conditions, $shooter, $victim);
    
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
    
                                                EffectHandler::applyPlayerEffects($shooter, $victim, $effects);
                                            }
                                        }
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
            
                                $typeMatched = false;
                                if (is_array($enchantmentData['type'])) {
                                    foreach ($enchantmentData['type'] as $type) {
                                        if ($type === 'DEFENSE' || $type === 'DEFENSE_MOB' || $type === 'DEFENSE_PROJECTILE') {
                                            $typeMatched = true;
                                            break;
                                        }
                                    }
                                } else {
                                    if ($enchantmentData['type'] === 'DEFENSE' || $enchantmentData['type'] === 'DEFENSE_MOB' || $enchantmentData['type'] === 'DEFENSE_PROJECTILE') {
                                        $typeMatched = true;
                                    }
                                }
            
                                if (!$typeMatched) {
                                    continue;
                                }
            
                                if (isset($enchantmentData['levels']["$level"]['conditions'])) {
                                    $conditions = $enchantmentData['levels']["$level"]['conditions'] ?? [];
                                    $conditionsMet = empty($conditions) || Utils::checkConditions($conditions, $attacker, $victim);
                                    
                                    // Only apply effects if conditions are met and force is false
                                    if ($conditionsMet) {
                                        foreach ($conditions as $condition) {
                                            if (isset($condition['effects'])) {
                                                foreach ($condition['effects'] as $effect) {
                                                    if ($effect['type'] === 'BOOST_CHANCE') {
                                                        if ($condition['force'] === false) { 
                                                            $chance += $effect['value'];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $chance = $enchantmentData['levels']["$level"]['chance'] ?? 100;
                                    foreach ($enchantmentData['levels']["$level"]['effects'] as $effect) {
                                        if ($effect['type'] === 'BOOST_CHANCE') {
                                            $chance += $effect['value'];
                                        }
                                    }
                                }
                                
                                if (mt_rand(1, 100) <= $chance) {
                                    $effects = $enchantmentData['levels']["$level"]['effects'];
                                    $initialDamage = $event->getBaseDamage();
            
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
            
                                            if ($effect['type'] === "CANCEL_EVENT") {
                                                $event->cancel();
                                                var_dump("event cancelled");
                                            }
                                            
                                            EffectHandler::applyPlayerEffects($attacker, $victim, $effects);
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
                                    EffectHandler::applyPlayerEffects($victim, $attacker, $effects);
                                }
                            }
                        }
                    }
                }
            }
        }
    }    
    

    private function applyDamageModification(EntityDamageByEntityEvent $event, Player $attacker, $victim, string $context): void {
        foreach ($attacker->getInventory()->getContents() as $item) {
            $this->applyItemDamageModification($event, $attacker, $victim, $item, $context);
        }
        foreach ($attacker->getArmorInventory()->getContents() as $item) {
            $this->applyItemDamageModification($event, $attacker, $victim, $item, $context);
        }
    }
    
    private function applyItemDamageModification(EntityDamageByEntityEvent $event, Player $source, $target, Item $item, string $context): void {
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

    public function onPlayerHoldItem(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $previousItem = $player->getInventory()->getItemInHand();
        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
    
        foreach ($item->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                if (isset($enchantmentConfig[$enchantmentName])) {
                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                    $level = $enchantmentInstance->getLevel();
                    foreach ($enchantmentData['type'] as $type) {
                        if ($type === 'HELD' && isset($enchantmentData['levels']["$level"]['effects'])) {
                            EffectHandler::applyPlayerEffects($player, null, $enchantmentData['levels']["$level"]['effects']);
                        }
                    }
                }
            }
        }
    
        foreach ($previousItem->getEnchantments() as $enchantmentInstance) {
            $enchantment = $enchantmentInstance->getType();
            if ($enchantment instanceof CustomEnchantment) {
                $enchantmentName = $enchantment->getName();
                if (isset($enchantmentConfig[$enchantmentName])) {
                    $enchantmentData = $enchantmentConfig[$enchantmentName];
                    $level = $enchantmentInstance->getLevel();
                    foreach ($enchantmentData['type'] as $type) {
                        if ($type === 'HELD' && isset($enchantmentData['levels']["$level"]['effects'])) {
                            Utils::removePlayerEffects($player, $enchantmentData['levels']["$level"]['effects']);
                        }
                    }
                }
            }
        }
    }
    
    private function applyEnchantmentEffects(Entity $source, ?Entity $target, Item $item, string $context): void {
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
                            $conditions = $enchantmentData['levels']["$level"]['conditions'] ?? [];
    
                            // Check conditions
                            foreach ($conditions as $condition) {
                                if ($condition['type'] === 'IS_MOB_TYPE' && isset($condition['mobs'])) {
                                    if (!in_array($target, $condition['mobs'])) {
                                        continue 2;
                                    }
                                }
                            }
                            EffectHandler::applyPlayerEffects($source, $target, $effects);
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

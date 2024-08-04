<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantments;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Tasks\ApplyBlockBreakEffectTask;
use ecstsy\AdvancedEnchantments\Utils\AdvancedTriggers;
use ecstsy\AdvancedEnchantments\Utils\EffectHandler;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\block\Farmland;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
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
    
        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener(
            function (Inventory $inventory, int $slot, Item $oldItem): void {
                $this->onArmorSlotChange($inventory, $slot, $oldItem);
            },
            function (Inventory $inventory, array $oldContents): void { /* Handle bulk changes if needed */ }
        ));
    
        $player->getInventory()->getListeners()->add(new CallbackInventoryListener(
            function (Inventory $inventory, int $slot, Item $oldItem): void {
                $this->onInventorySlotChange($inventory, $slot, $oldItem);
            },
            function (Inventory $inventory, array $oldContents): void { /* Handle bulk changes if needed */ }
        ));
    }   
    
    private function onArmorSlotChange(ArmorInventory $inventory, int $slot, Item $oldItem): void
    {
        $player = $inventory->getHolder();
        if ($player instanceof Player) {
            $newItem = $inventory->getItem($slot);
    
            if (!$oldItem->equals($newItem, false)) {
                if (!$oldItem->isNull()) {
                    Utils::removeItemEffects($player, $oldItem);
                }
    
                if (!$newItem->isNull() && Utils::isArmorItem($newItem)) {
                    $newItemEnchantments = $newItem->getEnchantments();
                    if (!empty($newItemEnchantments)) {
                        AdvancedTriggers::applyEnchantmentEffects($player, null, $newItemEnchantments, "EFFECT_STATIC");
                    }
                }
            }
        }
    }
    
    private function onInventorySlotChange(PlayerInventory $inventory, int $slot, Item $oldItem): void
    {
        $player = $inventory->getHolder();
        if ($player instanceof Player) {
            $heldSlot = $player->getInventory()->getHeldItemIndex();
            $newItem = $inventory->getItem($slot);
    
            if ($slot === $heldSlot) {
                if (!$oldItem->equals($newItem, false)) {
                    if (!$oldItem->isNull()) {
                        Utils::removeItemEffects($player, $oldItem);
                    }
    
                    if (!$newItem->isNull()) {
                        $newItemEnchantments = $newItem->getEnchantments();
                        if (!$newItem->isNull() && !Utils::isArmorItem($newItem)) {
                            $newItemEnchantments = $newItem->getEnchantments();
                            if (!empty($newItemEnchantments)) {
                                AdvancedTriggers::applyEnchantmentEffects($player, null, $newItemEnchantments, "HELD");
                            }
                        }
                    }
                }
            }
        }
    }    
    
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
    
        if ($player instanceof Player) {    
            foreach ($transaction->getActions() as $action) {
                if ($action instanceof SlotChangeAction) {
                    $inventory = $action->getInventory();
    
                    if ($inventory instanceof ArmorInventory) {
                        $newItem = $action->getTargetItem();
                        $oldItem = $action->getSourceItem();
    
                        if (!$oldItem->isNull()) {
                            Utils::removeItemEffects($player, $oldItem);
                        }
    
                        if (!$newItem->isNull()) {
                            AdvancedTriggers::applyEnchantmentEffects($player, null, $newItem->getEnchantments(), "EFFECT_STATIC");
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

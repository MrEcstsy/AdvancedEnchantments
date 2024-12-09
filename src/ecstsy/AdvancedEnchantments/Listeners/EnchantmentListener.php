<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantments;
use ecstsy\AdvancedEnchantments\libs\ecstsy\advancedAbilities\triggers\AttackTrigger;
use ecstsy\AdvancedEnchantments\libs\ecstsy\advancedAbilities\triggers\DefenseTrigger;
use ecstsy\AdvancedEnchantments\libs\ecstsy\advancedAbilities\triggers\GenericTrigger;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Tasks\ApplyBlockBreakEffectTask;
use ecstsy\AdvancedEnchantments\Utils\AdvancedTriggers;
use ecstsy\AdvancedEnchantments\Utils\EffectHandler;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\block\Farmland;
use pocketmine\entity\Living;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
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
                    $filteredEnchantments = [];
    
                    foreach ($newItemEnchantments as $enchantmentInstance) {
                        $enchantment = $enchantmentInstance->getType();
                        if ($enchantment instanceof CustomEnchantment) {
                            $enchantmentData = Utils::getConfiguration("enchantments.yml")->getAll()[$enchantment->getName()];
                            
                            if (isset($enchantmentData['type']) && in_array("EFFECT_STATIC", $enchantmentData['type'])) {
                                $filteredEnchantments[] = $enchantmentInstance;  
                            }
                        }
                    }
    
                    if (!empty($filteredEnchantments)) {
                        AdvancedTriggers::applyEnchantmentEffects($player, null, $filteredEnchantments, "EFFECT_STATIC");
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
                            $triggers = Utils::getTriggersContext($newItem);
                            if (!empty($newItemEnchantments)) {
                                foreach ($triggers as $context) {
                                    if ($context === "HELD") {
                                        AdvancedTriggers::applyEnchantmentEffects($player, null, $newItemEnchantments, "HELD");
                                    }
                                }
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
                            $newItemEnchantments = $newItem->getEnchantments();
                            $filteredEnchantments = [];
    
                            foreach ($newItemEnchantments as $enchantmentInstance) {
                                $enchantment = $enchantmentInstance->getType();
                                if ($enchantment instanceof CustomEnchantment) {
                                    $enchantmentData = Utils::getConfiguration("enchantments.yml")->getAll()[$enchantment->getName()];
    
                                    if (isset($enchantmentData['type']) && in_array("EFFECT_STATIC", $enchantmentData['type'])) {
                                        $filteredEnchantments[] = $enchantmentInstance;  
                                    }
                                }
                            }
    
                            if (!empty($filteredEnchantments)) {
                                AdvancedTriggers::applyEnchantmentEffects($player, null, $filteredEnchantments, "EFFECT_STATIC");
                            }
                        }
                    }
                }
            }
        }
    }

    public function onPlayerAttack(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
    
        $attacker = $event->getDamager();
        $victim = $event->getEntity();
    
        if (!$attacker instanceof Player || $attacker->getInventory()->getItemInHand()->isNull()) {
            return;
        }
    
        $item = $attacker->getInventory()->getItemInHand();
        $enchantments = Utils::extractEnchantmentsFromItems([$item]);
    
        if (empty($enchantments)) {
            return;
        }
    
        foreach ($enchantments as &$enchantmentConfig) {
            $level = $enchantmentConfig['level'] ?? null;
            $chance = $enchantmentConfig['config']['levels'][$level]['chance'] ?? 100;
            if ($level !== null) {
                $extraData = ['enchant-level' => $level, "chance" => $chance];
            }
        }
        
        $trigger = new GenericTrigger();
        $trigger->execute($attacker, $victim, $enchantments, 'ATTACK', $extraData);
        
    }    
    
    public function onPlayerDefend(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
    
        $caster = $event->getEntity();
        $attacker = $event->getDamager();
    
        if (!$caster instanceof Living) {
            return;
        }
    
        $armorItems = $caster->getArmorInventory()->getContents();
        $enchantmentsToApply = Utils::extractEnchantmentsFromItems($armorItems);
    
        foreach ($enchantmentsToApply as &$enchantmentConfig) {
            $level = $enchantmentConfig['level'] ?? null;
            if ($level !== null) {
                $enchantmentConfig['enchant-level'] = $level;
            }
        }
    
        if (!empty($enchantmentsToApply)) {
            $trigger = new DefenseTrigger();
            $trigger->execute($attacker, $caster, $enchantmentsToApply, 'DEFENSE', ["enchant-level" => $level]);
        }
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Living) {
            return;
        }

        $armorItems = $entity->getArmorInventory()->getContents();
        $effects = Utils::getEffectsFromItems($armorItems, "FALL_DAMAGE");

        if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            foreach ($effects as $effect) {
                if ($effect['type'] === "CANCEL_EVENT") {
                    $event->cancel();
                    break;
                }
            }
        }
    }

    public function onEntityDamageDecrease(EntityDamageByEntityEvent $event): void {
        $victim = $event->getEntity();
        $attacker = $event->getDamager();

        if ($event->isCancelled()) {
            return;
        }

        if (!$victim instanceof Living || !$attacker instanceof Player) {
            return;
        }

        $armorItems = $victim->getArmorInventory()->getContents();
        $effects = Utils::getEffectsFromItems($armorItems, "DEFENSE");

        foreach ($effects as $effect) {
            if ($effect['type'] === "DECREASE_DAMAGE") {
                $percentage = isset($effect['amount']) && is_numeric($effect['amount']) ? $effect['amount'] : 10;
                $damage = $event->getBaseDamage();
                $reduction = $damage * ($percentage / 100);

                var_dump("before reduction: " . $damage);
                $event->setModifier(-$reduction, EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK);
                var_dump("after reduction: " . $event->getFinalDamage());
            }
        }
    }
    
}

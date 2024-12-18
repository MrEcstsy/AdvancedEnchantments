<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantments;
use ecstsy\AdvancedEnchantments\Utils\AdvancedTriggers;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
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

class EnchantmentListener implements Listener {

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
}

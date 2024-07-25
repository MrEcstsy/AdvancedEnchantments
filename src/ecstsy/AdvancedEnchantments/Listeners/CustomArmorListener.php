<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\EffectHandler;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class CustomArmorListener implements Listener
{

    public array $abilityCooldown = [];

    public static array $activeAbilities = [];


    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $onSlot = function (Inventory $inventory, int $slot, Item $oldItem): void {
            if ($inventory instanceof ArmorInventory) {
                $holder = $inventory->getHolder();
                if ($holder instanceof Player) {
                    $newItem = $inventory->getItem($slot);
                    if ($oldItem instanceof Durable && $newItem instanceof Durable) {
                        if (!$oldItem->equals($newItem, false) && $oldItem->getDamage() !== $newItem->getDamage()) {
                            return;
                        }
                    }

                    foreach ($inventory->getContents() as $piece) {
                        if (($set = $piece->getNamedTag()->getTag("advancedsets")) !== null) {
                            Utils::checkArmorActivation($holder, $inventory, $set->getValue());
                        }
                    }
                }
            }
        };

        $onContent = function (Inventory $inventory, array $oldContents) use ($onSlot): void {
            foreach ($oldContents as $slot => $oldItem) {
                if (!($oldItem ?? VanillaItems::AIR())->equals($inventory->getItem($slot), !$inventory instanceof ArmorInventory)) {
                    $onSlot($inventory, $slot, $oldItem);
                }
            }
        };

        $player->getInventory()->getListeners()->add(new CallbackInventoryListener($onSlot, $onContent));
        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener($onSlot, $onContent));
    }

    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $entity = $event->getEntity();
        $attacker = $event->getDamager();
        $currentTime = time();
    
        if ($attacker instanceof Player) {
            $armorInv = $attacker->getArmorInventory();
            $itemInHand = $attacker->getInventory()->getItemInHand();
            foreach ($armorInv->getContents() as $piece) {
                if (($setTag = $piece->getNamedTag()->getTag("advancedsets")) !== null) {
                    $set = $setTag->getValue();
                    $pieces = Utils::getEquippedArmorPieces($armorInv, $set);
    
                    if (count($pieces) === 4) {
                        $config = $this->getArmorSetConfiguration($set);
                        if ($config !== null) {
                            $eventConfig = $config->get("events");
    
                            if ($eventConfig !== null) {
                                foreach ($eventConfig as $eventType => $eventDetails) {
                                    $chance = $eventDetails['chance'] ?? 100;
                                    $effects = $eventDetails['effects'] ?? [];
                                    $cooldown = $eventDetails['cooldown'] ?? 0;
    
                                    if (mt_rand(1, 100) <= $chance) {
                                        if ($eventType === "ATTACK" && $entity instanceof Living) {
                                            EffectHandler::applyPlayerEffects($attacker, $entity, $effects);
    
                                            foreach ($effects as $effect) {
                                                if (isset($effect['type']) && $effect['type'] === 'INCREASE_DAMAGE' && isset($effect['amount'])) {
                                                    $amount = Utils::parseLevel($effect['amount']);
                                                    $newDamage = $event->getBaseDamage() * (1 + $amount / 100);
                                                    $event->setBaseDamage($newDamage);
                                                }
                                            }
                                        }
    
                                        if ($eventType === "DEFENSE" && $entity instanceof Player) {
                                            $armorInv = $entity->getArmorInventory();
                                            $victimPieces = Utils::getEquippedArmorPieces($armorInv, $set);
    
                                            if (count($victimPieces) === 4) {
                                                EffectHandler::applyPlayerEffects($entity, $attacker, $effects);
    
                                                foreach ($effects as $effect) {
                                                    if (isset($effect['type']) && $effect['type'] === 'DECREASE_DAMAGE' && isset($effect['amount'])) {
                                                        $amount = Utils::parseLevel($effect['amount']);
                                                        $newDamage = $event->getBaseDamage() * (1 - $amount / 100);
                                                        $event->setBaseDamage($newDamage);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }
    }    
    
    private function getArmorSetConfiguration(string $setName): ?Config {
        $directory = Loader::getInstance()->getDataFolder() . "armorSets/";
        $files = scandir($directory);
    
        foreach ($files as $file) {
            if (strcasecmp(pathinfo($file, PATHINFO_FILENAME), $setName) === 0) {
                return Utils::getConfiguration("armorSets/$file");
            }
        }
    
        return null;
    }
}

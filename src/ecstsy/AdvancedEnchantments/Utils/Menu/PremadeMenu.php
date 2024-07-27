<?php

namespace ecstsy\AdvancedEnchantments\Utils\Menu;

use ecstsy\AdvancedEnchantments\libs\muqsit\invmenu\InvMenu;
use ecstsy\AdvancedEnchantments\libs\muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use ecstsy\AdvancedEnchantments\libs\muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat as C;

class PremadeMenu {

    public static function sendPremadeSetupsMenu(): InvMenu {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $inventory = $menu->getInventory();
        $items = [
            0 => VanillaBlocks::BEACON()->asItem()->setCustomName(C::colorize("&r&a200+ Advanced Custom Enchantments"))->setLore([
                C::colorize("&r&8Enchantment Pack #1"),
                "",
                C::colorize("&r&aPack Contains:"),
                C::colorize("&r&8 - &f200+ Most Advanced Enchants Ever"),
                C::colorize("&r&8 - &fComes by default with the plugin"),
                C::colorize("&r&8 - &fMix of Vanilla-Like enchantments and"),
                C::colorize("&r&f incredibly crafted most advanced enchantments ever"),
                "",
                C::colorize("&r&a(!) &fClick to &aDownload &fthis setup."),
                C::colorize("&r&aWARNING&f: Your current files will be overriden.")
            ]),
            1 => VanillaItems::DIAMOND_SWORD()->setCustomName(C::colorize("&r&d200+ PvP/Action Custom Enchantments"))->setLore([
                C::colorize("&r&8Enchantment Pack #2"),
                C::colorize("&r&7Inspired by CosmicPvP enchantments"),
                "",
                C::colorize("&r&aPack Contains:"),
                C::colorize("&r&8 - &f200+ PvP-Oriented enchants"),
                C::colorize("&r&8 - &f27 God Kits"),
                C::colorize("&r&8 - &f7 Premade Armor Sets"),
                C::colorize("&r&8 - &f5 Premade Custom Weapons"),
                "",
                C::colorize("&r&d(!) &fClick to &dDownload &fthis setup."),
                C::colorize("&r&dWARNING&b: &fYour current files will be overriden.")
            ]),
            2 =>VanillaBlocks::GRASS()->asItem()->setCustomName(C::colorize("&r&e50+ PvP/Skyblock Enchantments"))->setLore([
                C::colorize("&r&8Enchantment Pack #3"),
                C::colorize("&r&7Inspired by PvPWars/PvpingMc"),
                "",
                C::colorize("&r&ePacking Contains:"),
                C::colorize("&r&8 - &f50+ PvP-Oriented enchants"),
                C::colorize("&r&8 - &fPre-configured configurations"),
                "",
                C::colorize("&r&e(!) &fClick to &eDownload &fthis setup."),
                C::colorize("&r&eWARNING&f: Your current files will be overriden.")
            ]),
            26 => VanillaItems::PAPER()->setCustomName(C::colorize("&r&aWhat are premade setups?"))->setLore([
                C::colorize("&r&8>> &7Setups are already premade configurations"),
                C::colorize("&r&7for the plugin. Comes with customized config,"),
                C::colorize("&r&7tinkerer configurations and enchantments."),
                C::colorize("&r&aHow can I submit one?"),
                C::colorize("&r&8>> &7You can submit one by contacting MrEcstsy"),
                C::colorize("&r&7on the GitHub Issues tab with cully configured config.yml,"),
                C::colorize("&r&7tinkerer.yml as well as unique enchantments.yml"),
            ]),
        ];

        foreach ($items as $slot => $item) {
            $inventory->setItem($slot, $item);
        }

        $menu->setName(C::colorize("&r&8AE &oPremade Setups"));

        $menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction) {
            
        }));
        return $menu;
    }
}
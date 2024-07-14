<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

class ReloadSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLang()->getNested("commands.no-permission"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $startTime = microtime(true);

        $configFiles = ["config.yml", "enchantments.yml", "groups.yml"];
        $localeDir = Loader::getInstance()->getDataFolder() . "locale/";
        $armorSetsDir = Loader::getInstance()->getDataFolder() . "armorSets/";

        foreach ($configFiles as $file) {
            $cfg = Utils::getConfiguration($file);
            if ($cfg !== null) {
                $cfg->reload();
            } else {
                $sender->sendMessage(C::colorize("&cFailed to reload configuration file: {$file}"));
            }
        }

        $localeFiles = glob($localeDir . "*.yml");
        foreach ($localeFiles as $file) {
            $relativeFile = str_replace(Loader::getInstance()->getDataFolder(), '', $file);
            $cfg = Utils::getConfiguration($relativeFile);
            if ($cfg !== null) {
                $cfg->reload();
            } else {
                $sender->sendMessage(C::colorize("&cFailed to reload locale file: {$relativeFile}"));
            }
        }

        $armorSetFiles = glob($armorSetsDir . "*.yml");
        foreach ($armorSetFiles as $file) {
            $relativeFile = str_replace(Loader::getInstance()->getDataFolder(), '', $file);
            $cfg = Utils::getConfiguration($relativeFile);
            if ($cfg !== null) {
                $cfg->reload();
            } else {
                $sender->sendMessage(C::colorize("&cFailed to reload armor set file: {$relativeFile}"));
            }
        }

        $armorSetsCount = count($armorSetFiles);
        $timeTaken = (microtime(true) - $startTime) * 1000;

        $sender->sendMessage(C::colorize("&r&l&cINFO &r&cThis command only reloads Enchants. To refresh everything else, you may need to reload the plugin / restart the server."));
        $sender->sendMessage(C::colorize("&r&6AE &fConfiguration and enchantments have been reloaded successfully. &7(took: " . round($timeTaken, 2) . "ms)"));
        Loader::getInstance()->getLogger()->info("Loaded " . $armorSetsCount . " armor sets.");
    }

    public function getPermission(): string {
        return "advancedenchantments.reload";
    }
}
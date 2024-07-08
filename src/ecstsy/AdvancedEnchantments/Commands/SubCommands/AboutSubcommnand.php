<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class AboutSubcommnand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $sender->sendMessage(TextFormat::colorize("&r&6AE &eVersion: &f" . Loader::getInstance()->getDescription()->getVersion()));
    }

    public function getPermission(): string
    {
        return "advancedenchantments.default";
    }
}
<?php

namespace ecstsy\AdvancedEnchantments\Commands;

use ecstsy\AdvancedEnchantments\libs\CortexPE\Commando\BaseCommand;
use ecstsy\AdvancedEnchantments\Utils\Menu\EnchanterMenu;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class EnchanterCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::colorize("&r&7In-game only!"));
            return;
        }

        EnchanterMenu::sendEnchanterMenu($sender)->send($sender);
    }

    public function getPermission(): string
    {
        return "advancedenchantments.enchanter";
    }
}
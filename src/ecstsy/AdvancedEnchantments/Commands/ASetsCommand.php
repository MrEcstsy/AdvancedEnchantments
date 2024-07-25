<?php

namespace ecstsy\AdvancedEnchantments\Commands;

use ecstsy\AdvancedEnchantments\Commands\SubCommands\ASetsSubCommand\AGiveSubCommand;
use ecstsy\AdvancedEnchantments\libs\CortexPE\Commando\BaseCommand;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class ASetsCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new AGiveSubCommand(Loader::getInstance(), "give", "Give specific set to Player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $header = C::colorize("&r&8------ &l&6AdvancedEnchantments &r&eArmor Sets &r&8------");

        $messages = [
            " ", 
            "&r&f /asets give <player> <set> &7- &eGive specific set to Player",
            "&r&f /asets givepiece <player> <set> <piece> &7- &eGive set item to Player &r&7(Helmet, Chestplate, Leggings, Boots)",
            "&r&f /asets weapon <player> <weapon name> &7- &eGives a Custom Weapon to player",
            "&r&f /asets list &7- &eList all available Sets",
            "&r&f /asets listweapons &7- &eList all available Weapons",
        ];
        
        if (!$sender instanceof Player) {
            $sender->sendMessage($header);
            foreach ($messages as $message) {
                $sender->sendMessage(C::colorize($message));
            }
            return;
        }

        $sender->sendMessage($header);
        foreach ($messages as $message) {
            $sender->sendMessage(C::colorize($message));
        }
    }

    public function getPermission(): string {
        return "advancedenchantments.asets";
    }
}
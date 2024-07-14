<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\utils\TextFormat as C;
use pocketmine\player\Player;

class GiveSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLang()->getNested("commands.no-permission"));
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("enchantment", false));
        $this->registerArgument(2, new IntegerArgument("level", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only!"));
            return;
        }

        $player = isset($args["name"]) ? Utils::getPlayerByPrefix($args["name"]) : null;
        $enchant = isset($args["enchantment"]) ? $args["enchantment"] : null;
        $level = isset($args["level"]) ? $args["level"] : null;

        if ($player !== null) {
            if ($enchant !== null && $level !== null) {
                $enchantment = StringToEnchantmentParser::getInstance()->parse($enchant);
                
                if ($player->getInventory()->canAddItem(Utils::createEnchantmentBook($enchantment, $level))) {
                    $player->getInventory()->addItem(Utils::createEnchantmentBook($enchantment, $level));
                    $sender->sendMessage(C::colorize(str_replace(["{enchant}", "{level}", "{player}"], [$enchant, $level, $player->getName()], Loader::getInstance()->getLang()->getNested("commands.main.give.success"))));
                } else {
                    $sender->getWorld()->dropItem($sender->getPosition()->asVector3(), Utils::createEnchantmentBook($enchantment, $level));
                }
            }
        }
    }

    public function getPermission(): ?string
    {
        return "advancedenchantments.give";
    }
}
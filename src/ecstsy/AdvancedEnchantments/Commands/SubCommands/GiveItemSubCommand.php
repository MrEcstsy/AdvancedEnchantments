<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class GiveItemSubcommand extends BaseSubCommand {

    private $availableItems = [
        'sslotincreaser', 'whitescroll', 'mystery', 'secret', 'magic',
        'blackscroll', 'randomizer', 'renametag', 'blocktrak', 'fishtrak',
        'stattrak', 'soultracker', 'mobtrak', 'soulgem', 'transmog',
        'holywhitescroll', 'orb'
    ];
    
    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLang()->getNested('commands.no-permission'));
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("item", false));
        $this->registerArgument(2, new IntegerArgument("amount", false));
        
    }   

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            if (empty($args)) {
                $sender->sendMessage("Usage: /$aliasUsed <player> <item> [amount]");
                $sender->sendMessage("&r&fCurrently Available: &7" . implode(", ", $this->availableItems));
                return;
            }           
            return;
        }

        if (empty($args)) {
            $sender->sendMessage("Usage: /$aliasUsed <player> <item> [amount]");
            $sender->sendMessage("&r&fCurrently Available: &7" . implode(", ", $this->availableItems));
            return;
        }

        $player = isset($args["name"]) ? Utils::getPlayerByPrefix($args["name"]) : null;
        $amount = isset($args["amount"]) ? $args["amount"] : 1;
        $item = isset($args["item"]) ? strtolower($args["item"]) : null;

        if ($player === null) {
            $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("commands.offline-player")));
            return;
        } else {
            if (!in_array($item, $this->availableItems)) {

            } elseif (in_array($item, $this->availableItems)) {
                $player->sendMessage(C::colorize(str_replace(["{player}", "{material}", "{amount}"], [$player->getName(), $item, $amount], Loader::getInstance()->getLang()->getNested("commands.aegive.success"))));
                if ($player->getInventory()->canAddItem(Utils::createScroll($item))) {
                    $player->getInventory()->addItem(Utils::createScroll($item, $amount));
                } else {
                    $player->getWorld()->dropItem($player->getLocation()->asVector3(), Utils::createScroll($item, $amount));
                }
            }
        }
    }

    public function getPermission(): string
    {
        return "advancedenchantments.giveitem";
    }
}
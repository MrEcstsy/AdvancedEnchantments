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

        if (!isset($args["name"]) && !isset($args["item"]) && !isset($args["amount"])) {
            $sender->sendMessage(C::colorize(str_replace("{usage}", C::colorize($this->getUsage()), Loader::getInstance()->getLang()->getNested("commands.invalid-usage"))));
            return;
        }

        $player = isset($args["name"]) ? Utils::getPlayerByPrefix($args["name"]) : null;
        $amount = isset($args["amount"]) ? $args["amount"] : null;
        $item = isset($args["item"]) ? strtolower($args["item"]) : null;

        if ($player !== null) {
            if ($player->isOnline()) {
                if (!in_array($item, $this->availableItems)) {
                    $sender->sendMessage($this->getUsage());
                } elseif (in_array($item, $this->availableItems)) {
                    $player->sendMessage(C::colorize(str_replace(["{player}", "{item}", "{amount}"], [$player->getName(), C::colorize(Utils::createScroll($item)->getCustomName()), $amount], Loader::getInstance()->getLang()->getNested("commands.main.giveitem.success"))));
                    if ($amount !== null) {
                        if ($player->getInventory()->canAddItem(Utils::createScroll($item))) {
                            $player->getInventory()->addItem(Utils::createScroll($item, $amount));
                            Utils::playSound($sender, "random.orb");
                        } else {
                            $player->getWorld()->dropItem($player->getLocation()->asVector3(), Utils::createScroll($item, $amount));
                        }
                    } else {
                        $sender->sendMessage($this->getUsage());
                    }
                }
            }
        } else {
            $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("commands.offline-player")));
        }
    }

    public function getUsage(): string
    {
        $messages = [
            "/ae giveitem <player> <item> <count> <group/soul count/success for blackscroll/preset tracker amount>",
            "&r&fCurrently Available: &7" . implode(", ", $this->availableItems)
        ];

        $message = implode("\n", $messages);
        return C::colorize($message);
    }

    public function getPermission(): string
    {
        return "advancedenchantments.giveitem";
    }
}
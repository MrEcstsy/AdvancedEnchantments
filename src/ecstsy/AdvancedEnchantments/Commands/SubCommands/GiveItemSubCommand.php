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
        'slotincreaser', 'whitescroll', 'mystery', 'secret', 'magic',
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

        $this->registerArgument(3, new RawStringArgument("extra1", true)); 
        $this->registerArgument(4, new IntegerArgument("extra2", true)); 
        $this->registerArgument(5, new IntegerArgument("extra3", true));
    }   

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            if (empty($args)) {
                $sender->sendMessage("Usage: /$aliasUsed <player> <item> [amount] [extra1] [extra2] [extra3]");
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
        $amount = isset($args["amount"]) ? $args["amount"] : 1;
        $item = isset($args["item"]) ? strtolower($args["item"]) : null;

        if ($player !== null) {
            if ($player->isOnline()) {
                if (!in_array($item, $this->availableItems)) {
                    $sender->sendMessage($this->getUsage());
                } else {
                    switch ($item) {
                        case 'orb':
                            if (isset($args["extra1"]) && isset($args["extra2"]) && isset($args["extra3"])) {
                                $orbType = $args["extra1"];
                                $max = $args["extra2"];
                                $success = $args["extra3"];
                                $orbItem = Utils::createOrb($orbType, $max, $success, $amount);

                                if ($player->getInventory()->canAddItem($orbItem)) {
                                    $player->getInventory()->addItem($orbItem);
                                } else {
                                    $player->getWorld()->dropItem($player->getLocation()->asVector3(), $orbItem);
                                }
                                $sender->sendMessage(C::colorize(str_replace(["{player}", "{item}", "{amount}"], [$player->getName(), C::colorize($orbItem->getCustomName()), $amount], Loader::getInstance()->getLang()->getNested("commands.main.giveitem.success"))));
                                Utils::playSound($sender, "random.orb");
                            } else {
                                $sender->sendMessage(C::colorize("Usage: /$aliasUsed <player> orb <amount> <orbType> <max> <success>"));
                            }
                            break;
                        case 'randomizer':
                        case 'secret':
                            if (isset($args["extra1"])) {
                                $group = $args["extra1"];
                                $scrollItem = Utils::createScroll($item, $amount, $group);

                                if ($player->getInventory()->canAddItem($scrollItem)) {
                                    $player->getInventory()->addItem($scrollItem);
                                } else {
                                    $player->getWorld()->dropItem($player->getLocation()->asVector3(), $scrollItem);
                                }
                                $sender->sendMessage(C::colorize(str_replace(["{player}", "{item}", "{amount}"], [$player->getName(), C::colorize(Utils::createScroll($item)->getCustomName()), $amount], Loader::getInstance()->getLang()->getNested("commands.main.giveitem.success"))));
                                Utils::playSound($sender, "random.orb");
                            } else {
                                $sender->sendMessage(C::colorize("Usage: /$aliasUsed <player> {$item} <amount> <group>"));
                            }
                            break;
                        default:
                            $scrollItem = Utils::createScroll($item, $amount);
                            if ($player->getInventory()->canAddItem($scrollItem)) {
                                $player->getInventory()->addItem($scrollItem);
                            } else {
                                $player->getWorld()->dropItem($player->getLocation()->asVector3(), $scrollItem);
                            }
                            $sender->sendMessage(C::colorize(str_replace(["{player}", "{item}", "{amount}"], [$player->getName(), C::colorize(Utils::createScroll($item)->getCustomName()), $amount], Loader::getInstance()->getLang()->getNested("commands.main.giveitem.success"))));
                            Utils::playSound($sender, "random.orb");
                            break;
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
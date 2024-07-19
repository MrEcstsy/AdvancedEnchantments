<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class GiveRCBookSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("type", false));
        $this->registerArgument(1, new RawStringArgument("name", false));
        $this->registerArgument(2, new IntegerArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only."));
            return;
        }

        $type = isset($args["type"]) ? strtolower($args["type"]) : null;
        $name = isset($args["name"]) ? $args["name"] : null;
        $amount = isset($args["amount"]) ? $args["amount"] : null;
        
        $player = Utils::getPlayerByPrefix($name);
        $groupConfig = Utils::getConfiguration("groups.yml")->getAll();

        $groupMap = array_change_key_case($groupConfig['groups'], CASE_LOWER);
        $groupMapUpper = array_change_key_case($groupConfig['groups'], CASE_UPPER);
        $availableGroups = array_keys($groupMapUpper);

        if ($type !== null) {
            if (!in_array(strtoupper($type), $availableGroups)) {
                $sender->sendMessage(C::colorize("&r&cCould not find specified group. Maybe try one of these:"));
                $sender->sendMessage(C::colorize("&r&f" . implode(", ", $availableGroups)));
                return;
            }

            $originalGroupName = $groupConfig['groups'][strtoupper($type)]['group-name'];

            if ($name !== null) {
                if ($amount !== null) {
                    if ($player !== null) {
                        if ($player->getInventory()->canAddItem(Utils::createRCBook($originalGroupName, $amount))) {
                            $player->getInventory()->addItem(Utils::createRCBook($originalGroupName, $amount));
                            $sender->sendMessage(C::colorize(str_replace(
                                [
                                    "{player}", "{amount}", "{group}",
                                ],
                                [
                                    $player->getName(), $amount, strtoupper($type)
                                ],
                                Loader::getInstance()->getLang()->getNested("commands.main.givercbook.success"))));
                                Utils::playSound($sender, "random.orb");
                                
                        }
                    } else {
                        $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("commands.offline-player")));
                    }
                } else {
                    $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("commands.invalid-amount")));
                }
            } else {
                $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("commands.offline-player")));
            }
        } else {
            $sender->sendMessage(C::colorize("&r&cCould not find specified group. Maybe try one of these:"));
            $sender->sendMessage(C::colorize("&r&f" . implode(", ", $availableGroups)));
        }
    }

    public function getUsage(): string
    {
        return "/ae givercbook <type> <player> <amount>";
    }
    public function getPermission(): string {
        return "advancedenchantments.give-rcbook";
    }
}
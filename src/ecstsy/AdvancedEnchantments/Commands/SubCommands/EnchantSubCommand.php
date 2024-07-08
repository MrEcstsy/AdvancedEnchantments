<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class EnchantSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLang()->getNested("commands.no-permission"));
        $this->registerArgument(0, new RawStringArgument("enchantment", false));
        $this->registerArgument(1, new IntegerArgument("level", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only!"));
            return;
        }

        $enchant = isset($args["enchantment"]) ? StringToEnchantmentParser::getInstance()->parse($args["enchantment"]) : null;
        $level = isset($args["level"]) ? $args["level"] : null;
        $item = $sender->getInventory()->getItemInHand();

        if ($enchant !== null) {
            if ($enchant instanceof CustomEnchantment) {
                if ($item->getTypeId() !== VanillaItems::AIR()->getTypeId()) {
                    $existingEnchantment = $item->getEnchantment($enchant);
                    if ($existingEnchantment !== null) {
                        $previousLevel = $existingEnchantment->getLevel();
                        if ($level === $previousLevel) {
                            $item->removeEnchantment($enchant);
                            $sender->getInventory()->setItemInHand($item);
                            $sender->sendMessage(C::colorize(str_replace("{enchant}", $enchant->getName(), Loader::getInstance()->getLang()->getNested("commands.main.enchant.removed"))));
                        } elseif ($level !== null && $level <= $enchant->getMaxLevel()) {
                            $item->removeEnchantment($enchant);
                            $item->addEnchantment(new EnchantmentInstance($enchant, $level));
                            $sender->getInventory()->setItemInHand($item);
    
                            if ($level > $previousLevel) {
                                $message = str_replace(
                                    ["{enchant}", "{previous-level}", "{level}"],
                                    [$enchant->getName(), $previousLevel, $level],
                                    Loader::getInstance()->getLang()->getNested("commands.main.enchant.upgraded")
                                );
                            } else {
                                $message = str_replace(
                                    ["{enchant}", "{previous-level}", "{level}"],
                                    [$enchant->getName(), $previousLevel, $level],
                                    Loader::getInstance()->getLang()->getNested("commands.main.enchant.downgraded")
                                );
                            }
                            $sender->sendMessage(C::colorize($message));
                        } else {
                            $maxLevel = $enchant->getMaxLevel();
                            $levelsArray = range(1, $maxLevel);
                            $levels = implode(", ", $levelsArray);
                            $sender->sendMessage(C::colorize(str_replace("{levels}", $levels, Loader::getInstance()->getLang()->getNested("commands.invalid-level"))));
                        }
                    } elseif ($level !== null && $level <= $enchant->getMaxLevel()) {
                        $item->addEnchantment(new EnchantmentInstance($enchant, $level));
                        $sender->getInventory()->setItemInHand($item);
                        $sender->sendMessage(C::colorize(str_replace("{enchant}", $enchant->getName(), Loader::getInstance()->getLang()->getNested("commands.main.enchant.added"))));
                    } else {
                        $maxLevel = $enchant->getMaxLevel();
                        $levelsArray = range(1, $maxLevel);
                        $levels = implode(", ", $levelsArray);
                        $sender->sendMessage(C::colorize(str_replace("{levels}", $levels, Loader::getInstance()->getLang()->getNested("commands.invalid-level"))));
                    }
                } else {
                    $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("commands.not-holding-item")));
                }
            }
        }    
    }

    public function getPermission(): ?string
    {
        return "advancedenchantments.enchant";
    }
}
<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class InfoSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("enchantment", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only."));
            return;
        }

        $enchant = isset($args["enchantment"]) ? $args["enchantment"] : null;
        $lang = Loader::getInstance()->getLang();

        if ($enchant !== null) {
            $enchantment = StringToEnchantmentParser::getInstance()->parse($enchant);
            if ($enchantment instanceof CustomEnchantment) {
                $infoMessages = $lang->getNested("commands.main");
                
                $enchantName = ucfirst($enchantment->getName());
                $description = $enchantment->getDescription();  
                $maxLevel = $enchantment->getMaxLevel();

                $enchantConfig = Utils::getConfiguration("enchantments.yml");
                $enchantmentData = $enchantConfig->get(strtolower($enchantment->getName()), []);
                $appliesTo = $enchantmentData['applies-to'] ?? ["Unknown"];

                foreach ($infoMessages['info'] as $message) {
                    $message = str_replace(
                        ['{enchant}', '{description}', '{applies}', '{max-level}'],
                        [$enchantName, $description, $appliesTo, $maxLevel],
                        $message
                    );
                    $sender->sendMessage(C::colorize($message));
                }
            } else {
                $sender->sendMessage(C::colorize(str_replace("{enchant}", $enchant, $lang->getNested("commands.invalid-enchant"))));
                return;
            }
        } 
    }

    public function getPermission(): string
    {
        return "advancedenchantments.info";
    }
}
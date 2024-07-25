<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands\ASetsSubCommand;

use ecstsy\AdvancedEnchantments\libs\CortexPE\Commando\args\RawStringArgument;
use ecstsy\AdvancedEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\utils\Config;

class AGiveSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("set", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $player = isset($args["name"]) ? Utils::getPlayerByPrefix($args["name"]) : null;
        $set = isset($args["set"]) ? $args["set"] : null;
    
        if ($player !== null) {
            if ($set !== null) {
                $directory = Loader::getInstance()->getDataFolder() . "armorSets/";
                $setFileName = null;
    
                foreach (scandir($directory) as $file) {
                    if (is_file($directory . $file) && strcasecmp(pathinfo($file, PATHINFO_FILENAME), $set) === 0) {
                        $setFileName = $file;
                        break;
                    }
                }
    
                if ($setFileName !== null) {
                    $filePath = $directory . $setFileName;
                    $config = new Config($filePath, Config::YAML);
                    $armorSetConfig = $config->getAll();
    
                    $setName = isset($armorSetConfig['name']) ? $armorSetConfig['name'] : $set;
                    $cArmor = Utils::createArmorSet($set, "ALL");
                    if ($cArmor !== null) {
                        foreach ($cArmor as $cPiece) {
                            if ($player->getInventory()->canAddItem($cPiece)) {
                                $player->getInventory()->addItem($cPiece);
                                if ($sender instanceof Player) {
                                    Utils::playSound($sender, "random.levelup");
                                }
                            } else {
                                $player->getWorld()->dropItem($player->getPosition()->asVector3(), $cPiece);
                                if ($sender instanceof Player) {
                                    Utils::playSound($sender, "random.levelup");
                                }
                            }
                        }
                    }
                    $sender->sendMessage(C::colorize(str_replace(["{set}", "{player}"], [$setName, $player->getName()], Loader::getInstance()->getLang()->getNested("sets.commands.give.success"))));
                } else {
                    $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("sets.commands.invalid-set")));
                }
            } else {
                $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("sets.commands.invalid-set")));
            }
        } else {
            $sender->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("sets.commands.offline-player")));
        }
    }    

    public function getPermission(): string
    {
        return "advancedenchantments.asets-give";
    }
}

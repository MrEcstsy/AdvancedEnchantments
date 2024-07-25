<?php

namespace ecstsy\AdvancedEnchantments\Commands;

use ecstsy\AdvancedEnchantments\libs\CortexPE\Commando\args\IntegerArgument;
use ecstsy\AdvancedEnchantments\libs\CortexPE\Commando\BaseCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\AboutSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\EnchantSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\GiveItemSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\GiveBookSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\GiveRCBookSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\InfoSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\ListSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\ReloadSubCommand;
use ecstsy\AdvancedEnchantments\Commands\SubCommands\UnenchantSubCommand;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class AECommand extends BaseCommand {

    private const ITEMS_PER_PAGE = 14;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument('page', true));

        $this->registerSubCommand(new GiveItemSubCommand(Loader::getInstance(), "giveitem", "Give Plugin Items"));
        $this->registerSubCommand(new AboutSubCommand(Loader::getInstance(), "about", "Information about plugin"));
        $this->registerSubCommand(new EnchantSubCommand(Loader::getInstance(), "enchant", "Enchant held item"));
        $this->registerSubCommand(new UnenchantSubCommand(Loader::getInstance(), "unenchant", "Unenchant held item"));
        $this->registerSubCommand(new ListSubCommand(Loader::getInstance(), "list", "List all enchantments"));
        $this->registerSubCommand(new GiveBookSubCommand(Loader::getInstance(), "givebook", "Give enchantment book"));
        $this->registerSubCommand(new InfoSubCommand(Loader::getInstance(), "info", "Info about enchantment"));
        $this->registerSubCommand(new ReloadSubCommand(Loader::getInstance(), "reload", "Reload plugin configuration"));
        $this->registerSubCommand(new GiveRCBookSubCommand(Loader::getInstance(), "givercbook", "Give RC enchantment book"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('AdvancedEnchantments Commands MV(minified version for console)');
            $sender->sendMessage('/ae reload - reload the plugins configuration');
            $sender->sendMessage('/ae giveitem <player> <item> - Give various plugin items');
            $sender->sendMessage('/ae give <player> <enchantment> <level> - Give Enchantment book with enchant');
            $sender->sendMessage('/ae givebook <player> <enchantment> <level> <count> <success> <destroy> - Give book with specific rates');
            return;
        }

        $page = $args['page'] ?? 1;
        $msgs = [
            "&r&f  /ae market &7- &eCommunity Enchantments",
            "&r&f  /ae enchanter &7- &eOpen Enchanter",
            "&r&f  /asets &7- &eSets commands",
            "&r&f  /aegive &7- &eGive Custom Enchanted Items",
            "&r&f  /tinkerer &7- &eOpen Tinkerer",
            "&r&f  /gkits &7- &eOpen GKits",
            "&r&f  /ae about &7- &eInformation about plugin",
            "&r&f  /ae enchant &2<enchantment> <level> &7- &eEnchant held item",
            "&r&f  /ae unenchant &2<enchantment> &7- &eUnenchant held item",
            "&r&f  /ae list &9[page] &7- &eList all enchantments",
            "&r&f  /ae admin &9[page]&f/&9[enchant to search for] &7- &eOpen Admin Inventory",
            "&r&f  /ae giveitem &2<player> <item> <amount> &7- &eGive Plugin Items",
            "&r&f  /ae greset &2<player> <gkit> &7- &eReset GKit for player",
            "&r&f  /ae tinkereritem &2<player> <amount> &7- &eGive Tinkerer's reward item to player'",
            "&r&f  /ae give &2<player> <enchantment> <level> &7- &eGive Enchantment Book",
            "&r&f  /ae setSouls &2<amount> &7- &eSet Souls on Held Item",
            "&r&f  /ae info &2<enchantment> &7- &eInformation about Enchantment",
            "&r&f  /ae reload &7- &eReload the plugin configuration",
            "&r&f  /ae magicdust &2<group> <rate> <player> <amount> &7- &eGive Magic Dust with specific rate",
            "&r&f  /ae givebook &2<player> <enchantment> <level> <count> <success> <destroy> &7- &eGive Book with specific rates",
            "&r&f  /ae givercbook &2<type> <player> <amount>  &7- &eGive Right-click books",
            "&r&f  /ae premade &7- &eView premade plugin configurations",
            "&r&f  /ae giverandombook &2<player> <group> &a[amount] &7- &eGives random book from tier",
            "&r&f  /ae open &2<player> <enchanter/tinkerer/alchemist> &7- &eForce-open GUI",
            "&r&f  /ae lastchanged &7- &eShows all enchants that were added/removed the last time /ae reload was run",
            "&r&f  /ae zip &7- &eZips up AE's data folder",
        ];

        $totalItems = count($msgs);
        $totalPages = ceil($totalItems / self::ITEMS_PER_PAGE);

        if ($page < 1 || $page > $totalPages) {
            $sender->sendMessage(C::RED . "Invalid page number. Please choose between 1 and " . $totalPages . ".");
            return;
        }

        $start = ($page - 1) * self::ITEMS_PER_PAGE;
        $end = min($start + self::ITEMS_PER_PAGE, $totalItems);

        $header = C::YELLOW . "[<]" . C::DARK_GRAY . " +-----< " . C::GOLD . "AdvancedEnchantments " . C::WHITE . "(Page $page) " . C::DARK_GRAY . ">-----+" . C::YELLOW . " [>]";
        $footer = C::YELLOW . "[<]" . C::DARK_GRAY . " +-----< " . C::GOLD . "AdvancedEnchantments " . C::WHITE . "(Page $page) " . C::DARK_GRAY . ">-----+" . C::YELLOW . " [>]";

        $sender->sendMessage($header);
        $sender->sendMessage(" ");

        for ($i = $start; $i < $end; $i++) {
            $sender->sendMessage(C::colorize($msgs[$i]));
        }

        if ($page === 1) {
            $sender->sendMessage(" "); 
            $sender->sendMessage(C::DARK_GREEN . "  <> " . C::WHITE . "- Required Arguments; " . C::BLUE . "[] " . C::WHITE . "- Optional Arguments");
        }

        $sender->sendMessage(C::GRAY . "* Navigate through help pages using " . C::WHITE . "/ae <page>");
        $sender->sendMessage($footer);
    }

    public function getUsage(): string {
        return Loader::getInstance()->getLang()->getNested("commands.main.unknown-command");
    }
    public function getPermission(): string {
        return 'advancedenchantments.default';
    }
}

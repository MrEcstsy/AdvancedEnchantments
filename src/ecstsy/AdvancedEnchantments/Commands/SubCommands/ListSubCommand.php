<?php

namespace ecstsy\AdvancedEnchantments\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

class ListSubCommand extends BaseSubCommand {

    private const ENCHANTMENTS_PER_PAGE = 15;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only!"));
            return;
        }

        $page = isset($args["page"]) ? max(1, (int)$args["page"]) : 1;

        $enchantmentsConfig = Utils::getConfiguration("enchantments.yml");
        if ($enchantmentsConfig === null) {
            $sender->sendMessage(C::RED . "Failed to load enchantments config.");
            return;
        }
        $enchantments = $enchantmentsConfig->getAll();

        $groupsConfig = Utils::getConfiguration("groups.yml");
        if ($groupsConfig === null) {
            $sender->sendMessage(C::RED . "Failed to load groups config.");
            return;
        }
        $groups = $groupsConfig->get('groups', []);
        $fallbackGroup = $groupsConfig->getNested('settings.fallback-group', 'SIMPLE');

        $groupedEnchantments = [];

        foreach ($enchantments as $enchantmentName => $enchantmentData) {
            $rarity = $enchantmentData['group'] ?? $fallbackGroup; 
            if (isset($groups[$rarity])) {
                $groupedEnchantments[$rarity][$enchantmentName] = $enchantmentData;
            } else {
                $groupedEnchantments[$fallbackGroup][$enchantmentName] = $enchantmentData;
            }
        }

        if (!isset($groupedEnchantments[$fallbackGroup])) {
            $groupedEnchantments = [$fallbackGroup => []] + $groupedEnchantments;
        }

        $sortedEnchantments = [];
        foreach ($groups as $groupName => $groupData) {
            if (isset($groupedEnchantments[$groupName])) {
                foreach ($groupedEnchantments[$groupName] as $enchantmentName => $enchantmentData) {
                    $sortedEnchantments[$enchantmentName] = $enchantmentData;
                }
            }
        }

        $totalEnchantments = count($sortedEnchantments);
        $totalPages = max(1, (int)ceil($totalEnchantments / self::ENCHANTMENTS_PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $start = ($page - 1) * self::ENCHANTMENTS_PER_PAGE;
        $end = min($start + self::ENCHANTMENTS_PER_PAGE, $totalEnchantments);

        $headerFooter = C::YELLOW . "[<] " . C::DARK_GRAY . "+-----< " . C::GOLD . "Custom Enchantments List " . C::GRAY . "(Page: " . $page . "/" . $totalPages . ") " . C::DARK_GRAY . ">-----+" . C::YELLOW . " [>]";
        $sender->sendMessage($headerFooter);

        $i = 0;
        foreach ($sortedEnchantments as $index => $enchantmentData) {
            if ($i >= $start && $i < $end) {
                $rarity = $enchantmentData['group'] ?? $fallbackGroup; 
                $groupId = CEGroups::getGroupId($rarity);
                $groupColor = CEGroups::translateGroupToColor($groupId);
                $displayName = str_replace('{group-color}', $groupColor, $enchantmentData['display']);
                $enchantName = C::colorize("&r&7" . ($i + 1) . ". " . $groupColor . $displayName); 

                $sender->sendMessage($enchantName);
            }
            $i++;
        }

        $sender->sendMessage($headerFooter);
    }

    public function getPermission(): ?string {
        return "advancedenchantments.list";
    }
}

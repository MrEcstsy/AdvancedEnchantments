<?php

namespace ecstsy\AdvancedEnchantments\Enchantments;

use ecstsy\AdvancedEnchantments\utils\Utils;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\utils\TextFormat as C;

final class CEGroups {

    private static array $groups = [];

    private static ?string $fallbackGroup = null;

    public static function init(): void {
        $config = Utils::getConfiguration("groups.yml");
        $groupsConfig = $config->get("groups", []);
        self::$fallbackGroup = strtoupper($config->getNested("settings.fallback-group", "SIMPLE"));

        $id = 1;  
        foreach ($groupsConfig as $groupName => $groupData) {
            self::$groups[strtoupper($groupName)] = [
                'id' => $id,
                'global_color' => $groupData['global-color'] ?? "",
                'group_name' => $groupData['group-name'] ?? "",
                'item' => $groupData['item'] ?? [],
                'slot_increaser' => $groupData['slot-increaser'] ?? [],
                'magic_dust' => $groupData['magic-dust'] ?? []
            ];
            $id++;  // Increment ID for the next group
        }
    }

    public static function getGroupData(string $groupName): ?array {
        return self::$groups[strtoupper($groupName)] ?? self::$groups[self::$fallbackGroup] ?? null;
    }

    public static function getFallbackGroup(): string {
        return self::$fallbackGroup;
    }

    public static function getGroupId(string $groupName): ?int {
        $groupData = self::getGroupData($groupName);
        return $groupData['id'] ?? null;
    }

    public static function translateGroupToColor(int $groupId): string {
        foreach (self::$groups as $groupName => $groupData) {
            if ($groupData['id'] === $groupId) {
                return C::colorize($groupData['global_color']);
            }
        }
        // Return the color for the fallback group if the rarity does not match any group
        $fallbackGroupData = self::getGroupData(self::getFallbackGroup());
        return C::colorize($fallbackGroupData['global_color'] ?? "&7");
    }

    public static function getGroupName(int $groupId): ?string {
        foreach (self::$groups as $groupName => $groupData) {
            if ($groupData['id'] === $groupId) {
                return $groupData['group_name'];
            }
        }
        // Return the name for the fallback group if the rarity does not match any group
        $fallbackGroupData = self::getGroupData(self::getFallbackGroup());
        return $fallbackGroupData['group_name'] ?? "Unknown";
    }

    public static function getGroupNameById(int $groupId): ?string {
        foreach (self::$groups as $groupName => $groupData) {
            if ($groupData['id'] === $groupId) {
                return $groupName;
            }
        }
        // Return the name for the fallback group if the group ID does not match any group
        return self::$fallbackGroup;
    }

    public static function getAllForRarity(string $groupName): array {
        $enchantments = [];
        $enchantmentsConfig = Utils::getConfiguration("enchantments.yml")->getAll();
        
        $upperGroupName = strtoupper($groupName);
    
        foreach ($enchantmentsConfig as $enchantmentName => $enchantmentData) {
            if (isset($enchantmentData['group']) && strtoupper($enchantmentData['group']) === $upperGroupName) {
                $enchantment = StringToEnchantmentParser::getInstance()->parse($enchantmentName);
                if ($enchantment !== null) {
                    $enchantments[] = $enchantment;
                }
            }
        }
    
        return $enchantments;
    }
    

    public static function getRarityForEnchantment(string $enchantmentName): ?string {
        $enchantmentsConfig = Utils::getConfiguration("enchantments.yml");
        $enchantments = $enchantmentsConfig->getAll();
    
        $enchantmentData = $enchantments[$enchantmentName] ?? null;
        if ($enchantmentData !== null && isset($enchantmentData['group'])) {
            return $enchantmentData['group'];
        }
    
        return null; // Return null if enchantment name not found
    }
    

}

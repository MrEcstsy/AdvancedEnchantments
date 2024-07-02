<?php

namespace ecstsy\AdvancedEnchantments\Enchantments;

use ecstsy\AdvancedEnchantments\Utils\EnchantUtils;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\Sword;

class CustomEnchantment extends Enchantment {
    private string $description;

    public function __construct(string $name, int $rarity, string $description, int $maxLevel, int $primaryFlag, int $secondaryFlag = ItemFlags::NONE) {
        $this->description = $description;

        parent::__construct($name, $rarity, $primaryFlag, $secondaryFlag, $maxLevel);
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getLoreLine(int $level): string {
        $groupId = $this->getRarity();
        $groupName = CEGroups::getGroupName($groupId);
        $color = CEGroups::translateGroupToColor($groupId);
    
        return $color . $groupName . " " . Utils::getRomanNumeral($level);
    }
    

    public static function getApplicable(Enchantment $enchantment): string {
        $cached = [];
    
        foreach (array_merge(EnchantUtils::TOOL_TO_ITEMFLAG, EnchantUtils::ARMOR_SLOT_TO_ITEMFLAG) as $class => $flag) {
            if ($enchantment->hasPrimaryItemType($flag)) {
                if (isset(EnchantUtils::ARMOR_SLOT_TO_ITEMFLAG[$class])) {
                    $cached[] = EnchantUtils::armorSlotToType($class);
                } else {
                    $cached[] = strtolower(basename(str_replace('\\', '/', $class)));
                }
            }
        }
        
        return implode(", ", $cached);
    }

    public static function matchesApplicable(Item $item, string $applicable): bool {
        $applicableTypes = explode(", ", $applicable);
        foreach ($applicableTypes as $type) {
            switch ($type) {
                case 'sword':
                    if ($item instanceof Sword) return true;
                    break;
                case 'axe':
                    if ($item instanceof Axe) return true;
                    break;
                case 'pickaxe':
                    if ($item instanceof Pickaxe) return true;
                    break;
                case 'shovel':
                    if ($item instanceof Shovel) return true;
                    break;
                case 'helmet':
                    if ($item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_HEAD) return true;
                    break;
                case 'chestplate':
                    if ($item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_CHEST) return true;
                    break;
                case 'leggings':
                    if ($item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_LEGS) return true;
                    break;
                case 'boots':
                    if ($item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_FEET) return true;
                    break;
                case 'armor': 
                    if ($item instanceof Armor) return true;
                    break;
            }
        }
        return false;
    }
    
    
}
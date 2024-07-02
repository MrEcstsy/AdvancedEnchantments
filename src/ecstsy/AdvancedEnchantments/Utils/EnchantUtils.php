<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\Sword;
use pocketmine\item\Tool;
use pocketmine\player\Player;
use xtcy\ElysiumCore\enchants\tools\HasteEnchantment;

class EnchantUtils
{

    public const TOOL_TO_ITEMFLAG = [
		Pickaxe::class => ItemFlags::PICKAXE,
		Sword::class => ItemFlags::SWORD,
		Axe::class => ItemFlags::AXE,
		Hoe::class => ItemFlags::HOE,
		Shovel::class => ItemFlags::SHOVEL,
		Bow::class => ItemFlags::BOW,
	];

    public const ARMOR_SLOT_TO_ITEMFLAG = [
		ArmorInventory::SLOT_HEAD => ItemFlags::HEAD,
		ArmorInventory::SLOT_CHEST => ItemFlags::TORSO,
		ArmorInventory::SLOT_LEGS => ItemFlags::LEGS,
		ArmorInventory::SLOT_FEET => ItemFlags::FEET,
	];

	public static function armorSlotToType(int $slot): string{
		return match ($slot) {
			ArmorInventory::SLOT_HEAD => "helmet",
			ArmorInventory::SLOT_CHEST => "chestplate",
			ArmorInventory::SLOT_LEGS => "leggings",
			ArmorInventory::SLOT_FEET => "boots",
			default => "undefined"
		};
	}

	public static function getToolItemFlag(Tool $item): int {
		foreach(self::TOOL_TO_ITEMFLAG as $class => $itemFlag) {
			if($item instanceof $class) return $itemFlag;
		}
		throw new \UnexpectedValueException("Unknown item type " . get_class($item));
	}
}
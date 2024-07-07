<?php

namespace ecstsy\AdvancedEnchantments\Enchantments;

use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\event\EventPriority;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\utils\Config;
use pocketmine\utils\RegistryTrait;
use pocketmine\utils\TextFormat;

final class CustomEnchantments {
    use RegistryTrait;

    public static array $ids = [];

    public static array $rarities = [];

    protected static function setup() : void {
        SimplePacketHandler::createInterceptor(Loader::getInstance(), EventPriority::HIGH)
            ->interceptOutgoing(function(InventoryContentPacket $pk, NetworkSession $destination): bool {
                foreach ($pk->items as $i => $item) {
                    $pk->items[$i] = new ItemStackWrapper($item->getStackId(), self::display($item->getItemStack()));
                }
                return true;
            })
            ->interceptOutgoing(function(InventorySlotPacket $pk, NetworkSession $destination): bool {
                $pk->item = new ItemStackWrapper($pk->item->getStackId(), self::display($pk->item->getItemStack()));
                return true;
            })
            ->interceptOutgoing(function(InventoryTransactionPacket $pk, NetworkSession $destination): bool {
                $transaction = $pk->trData;

                foreach ($transaction->getActions() as $action) {
                    $action->oldItem = new ItemStackWrapper($action->oldItem->getStackId(), self::filter($action->oldItem->getItemStack()));
                    $action->newItem = new ItemStackWrapper($action->newItem->getStackId(), self::filter($action->newItem->getItemStack()));
                }
                return true;
            });

        EnchantmentIdMap::getInstance()->register(CustomEnchantmentIds::FAKE_ENCH_ID, new Enchantment("", -1, 1, ItemFlags::ALL, ItemFlags::NONE));

        self::registerEnchantments();
    }

    protected static function register(string $name, int $id, CustomEnchantment $enchantment) : void {
        $map = EnchantmentIdMap::getInstance();
        $map->register($id, $enchantment);
        StringToEnchantmentParser::getInstance()->register($enchantment->getName(), fn() => $enchantment); //todo: needed?

        self::$ids[$enchantment->getName()] = $id;
        self::$rarities[$enchantment->getRarity()][] = $id;
        self::_registryRegister($name, $enchantment);
    }

    public static function getIdFromName(string $name) : ?int {
        return self::$ids[$name] ?? null;
    }

    public static function getAll() : array{
        /**
         * @var CustomEnchantment[] $result
         * @phpstan-var array<string, CustomEnchantment> $result
         */
        $result = self::_registryGetAll();
        return $result;
    }

    public static function display(ItemStack $itemStack): ItemStack {
        $item = TypeConverter::getInstance()->netItemStackToCore($itemStack);
    
        if (count($item->getEnchantments()) > 0) {
            $additionalInformation = TextFormat::RESET . TextFormat::AQUA . $item->getName();
            foreach ($item->getEnchantments() as $enchantmentInstance) {
                $enchantment = $enchantmentInstance->getType();
                if ($enchantment instanceof CustomEnchantment) {
                    $groupId = $enchantment->getRarity();
                    $color = CEGroups::translateGroupToColor($groupId);
                    
                    $enchantmentName = $enchantment->getName();
    
                    $config = Utils::getConfiguration("enchantments.yml");
    
                    if ($config->exists($enchantmentName)) {
                        $displayName = $config->getNested($enchantmentName . '.display');
                        $displayName = str_replace('{group-color}', $color, $displayName);
                    } else {
                        $displayName = $enchantmentName;
                    }
    
                    $additionalInformation .= "\n" . TextFormat::RESET . $displayName . " " . Utils::getRomanNumeral($enchantmentInstance->getLevel());
                }
            }
            
            if ($item->getNamedTag()->getTag(Item::TAG_DISPLAY)) {
                $item->getNamedTag()->setTag("OriginalDisplayTag", $item->getNamedTag()->getTag(Item::TAG_DISPLAY)->safeClone());
            }
            $item = $item->setCustomName($additionalInformation);
        }
        
        return TypeConverter::getInstance()->coreItemStackToNet($item);
    }    

    public static function filter(ItemStack $itemStack): ItemStack {
        $item = TypeConverter::getInstance()->netItemStackToCore($itemStack);
        $tag = $item->getNamedTag();
        if (count($item->getEnchantments()) > 0) $tag->removeTag(Item::TAG_DISPLAY);

        if ($tag->getTag("OriginalDisplayTag") instanceof CompoundTag) {
            $tag->setTag(Item::TAG_DISPLAY, $tag->getTag("OriginalDisplayTag"));
            $tag->removeTag("OriginalDisplayTag");
        }
        $item->setNamedTag($tag);
        return TypeConverter::getInstance()->coreItemStackToNet($item);
    }


    /**
     * @param EnchantmentInstance[] $enchantments
     * @return EnchantmentInstance[]
     */
    public static function sortEnchantmentsByRarity(array $enchantments): array
    {
        usort($enchantments, function (EnchantmentInstance $enchantmentInstance, EnchantmentInstance $enchantmentInstanceB) {
            $type = $enchantmentInstance->getType();
            $typeB = $enchantmentInstanceB->getType();
            return ($typeB instanceof CustomEnchantment ? $typeB->getRarity() : 1) - ($type instanceof CustomEnchantment ? $type->getRarity() : 1);
        });
        return $enchantments;
    }

    protected static function registerEnchantments(): void {
        $config = new Config(Loader::getInstance()->getDataFolder() . "enchantments.yml", Config::YAML);

        foreach ($config->getAll() as $enchantmentName => $enchantmentData) {
            if (!isset($enchantmentData['display'], $enchantmentData['id'], $enchantmentData['description'], $enchantmentData['group'])) {
                continue;
            }

            $id = (int) $enchantmentData['id'];
            $name = strval($enchantmentName);
            $descriptionArray = (array) $enchantmentData['description'];
            $description = implode("\n", $descriptionArray);
            $rarity = (int) CEGroups::getGroupId($enchantmentData['group']);
            $maxLevel = self::getMaxLevel($enchantmentData);
            $flags = self::parseFlags($enchantmentData['applies-to']);
            $enchantment = new CustomEnchantment($name, $id, $rarity, $description, $maxLevel, $flags);

            self::register($name, $id, $enchantment);
        }
    }

    protected static function getMaxLevel(array $enchantmentData): int {
        if (!isset($enchantmentData['levels']) || empty($enchantmentData['levels'])) {
            throw new \InvalidArgumentException("Enchantment '" . $enchantmentData['display'] . "' does not define any levels.");
        }
    
        $levels = $enchantmentData['levels'];
        $maxLevel = max(array_keys($levels));
        return $maxLevel;
    }

    protected static function parseFlags(string $appliesTo): int {
        switch (strtolower($appliesTo)) {
            case 'Pickaxe':
                return ItemFlags::PICKAXE;
            case 'Sword':
                return ItemFlags::SWORD;
            case 'Chestplate':
                return ItemFlags::TORSO;
            case 'Leggings':
                return ItemFlags::LEGS;
            case 'Boots':
                return ItemFlags::FEET;
            case 'All':
                return ItemFlags::ALL;
            case 'Armor':
                return ItemFlags::ARMOR;
            case 'Axe':
                return ItemFlags::AXE;
            case 'Hoe':
                return ItemFlags::HOE;
            case 'Shovel':
                return ItemFlags::SHOVEL;
            case 'Shears':
                return ItemFlags::SHEARS;
            case 'Bow':
                return ItemFlags::BOW;
            case 'Trident':
                return ItemFlags::TRIDENT;
            default:
                return ItemFlags::NONE;
        }
    }

}


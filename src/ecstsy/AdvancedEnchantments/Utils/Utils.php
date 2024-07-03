<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantmentIds;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\entity\Entity;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class Utils {

    public static function getConfiguration(string $fileName): Config {
        $pluginFolder = Loader::getInstance()->getDataFolder();
        $filePath = $pluginFolder . $fileName;

        $config = null;

        if (!file_exists($filePath)) {
            Loader::getInstance()->getLogger()->warning("Configuration file '$fileName' not found.");
        } else {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            switch ($extension) {
                case 'yml':
                case 'yaml':
                    $config = new Config($filePath, Config::YAML);
                    break;

                case 'json':
                    $config = new Config($filePath, Config::JSON);
                    break;

                default:
                Loader::getInstance()->getLogger()->warning("Unsupported configuration file format for '$fileName'.");
                    break;
            }
        }

        return $config;
    }

    /**
     * Returns an online player whose name begins with or equals the given string (case insensitive).
     * The closest match will be returned, or null if there are no online matches.
     *
     * @param string $name The prefix or name to match.
     * @return Player|null The matched player or null if no match is found.
     */
    public static function getPlayerByPrefix(string $name): ?Player {
        $found = null;
        $name = strtolower($name);
        $delta = PHP_INT_MAX;

        /** @var Player[] $onlinePlayers */
        $onlinePlayers = Server::getInstance()->getOnlinePlayers();

        foreach ($onlinePlayers as $player) {
            if (stripos($player->getName(), $name) === 0) {
                $curDelta = strlen($player->getName()) - strlen($name);

                if ($curDelta < $delta) {
                    $found = $player;
                    $delta = $curDelta;
                }

                if ($curDelta === 0) {
                    break;
                }
            }
        }

        return $found;
    }

       /**
     * @param Entity $player
     * @param string $sound
     * @param int $volume
     * @param int $pitch
     * @param int $radius
     */
    public static function playSound(Entity $player, string $sound, $volume = 1, $pitch = 1, int $radius = 5): void
    {
        foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $p) {
            if ($p instanceof Player) {
                if ($p->isOnline()) {
                    $spk = new PlaySoundPacket();
                    $spk->soundName = $sound;
                    $spk->x = $p->getLocation()->getX();
                    $spk->y = $p->getLocation()->getY();
                    $spk->z = $p->getLocation()->getZ();
                    $spk->volume = $volume;
                    $spk->pitch = $pitch;
                    $p->getNetworkSession()->sendDataPacket($spk);
                }
            }
        }
    }

    /**
     * @param Entity $player
     * @param string $particleName
     * @param int $radius
     */
    public static function spawnParticle(Entity $player, string $particleName, int $radius = 5): void {
        $packet = new SpawnParticleEffectPacket();
        $packet->particleName = $particleName;
        $packet->position = $player->getPosition()->asVector3();

        foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $p) {
            if ($p instanceof Player) {
                if ($p->isOnline()) {
                    $p->getNetworkSession()->sendDataPacket($packet);
                }
            }
        }
    }

    public static function translateTime(int $seconds): string
    {
        $timeUnits = [
            'w' => 60 * 60 * 24 * 7,
            'd' => 60 * 60 * 24,
            'h' => 60 * 60,
            'm' => 60,
            's' => 1,
        ];

        $parts = [];

        foreach ($timeUnits as $unit => $value) {
            if ($seconds >= $value) {
                $amount = floor($seconds / $value);
                $seconds %= $value;
                $parts[] = $amount . $unit;
            }
        }

        return implode(', ', $parts);
    }

    public static function setupRewards(array $rewardData, ?Player $player = null): array
    {
        $rewards = [];
        $stringToItemParser = StringToItemParser::getInstance();
        
        foreach ($rewardData as $data) {
            if (!isset($data["item"])) {
                continue; 
            }

            $itemString = $data["item"];
            $item = $stringToItemParser->parse($itemString);
            if ($item === null) {
                continue;
            }

            if (isset($data["command"])) {
                $commandString = $data["command"] ?? null;
                if ($commandString !== null) {
                    Server::getInstance()->dispatchCommand(new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage()), str_replace("{player}", $player->getName(), $commandString));
                }
            }
    
            $amount = $data["amount"] ?? 1;
            $item->setCount($amount);
    
            $name = $data["name"] ?? null;
            if ($name !== null) {
                $item->setCustomName(TextFormat::colorize($name));
            }
    
            $lore = $data["lore"] ?? null;
            if ($lore !== null) {
                $lore = array_map(function ($line) {
                    return TextFormat::colorize($line);
                }, $lore);
                $item->setLore($lore);
            }
    
            $enchantments = $data["enchantments"] ?? null;
            if ($enchantments !== null) {
                foreach ($enchantments as $enchantmentData) {
                    $enchantment = $enchantmentData["enchant"] ?? null;
                    $level = $enchantmentData["level"] ?? 1;
                    if ($enchantment !== null) {
                        $item->addEnchantment(new EnchantmentInstance(StringToEnchantmentParser::getInstance()->parse($enchantment)), $level);
                    }
                }
            }
    
            $nbtData = $data["nbt"] ?? null;
            if ($nbtData !== null) {
                $tag = $nbtData["tag"] ?? "";
                $value = $nbtData["value"] ?? "";
            
                if (is_int($value)) {
                    $item->getNamedTag()->setInt($tag, $value);
                } else {
                    $item->getNamedTag()->setString($tag, $value);
                }
            }            
    
            $rewards[] = $item;
        }

        if (isset($data["display-item"])) {
            $displayItemString = $data["display-item"];
            $displayItem = $stringToItemParser->parse($displayItemString);
            if ($displayItem !== null) {
                $displayItem->setCustomName($item->getCustomName());
                $displayItem->setLore($item->getLore());
                $rewards[] = $displayItem;
            }
        } else {
            $rewards[] = $item;
        }
    
        return $rewards;
    }    

    /**
     * @param int $integer
     * @return string
     */
    public static function getRomanNumeral(int $integer): string
    {
        $romanString = "";
        while ($integer > 0) {
            $romanNumeralConversionTable = [
                'M' => 1000,
                'CM' => 900,
                'D' => 500,
                'CD' => 400,
                'C' => 100,
                'XC' => 90,
                'L' => 50,
                'XL' => 40,
                'X' => 10,
                'IX' => 9,
                'V' => 5,
                'IV' => 4,
                'I' => 1
            ];
            foreach ($romanNumeralConversionTable as $rom => $arb) {
                if ($integer >= $arb) {
                    $integer -= $arb;
                    $romanString .= $rom;
                    break;
                }
            }
        }
        return $romanString;
    }

    public static function secondsToTicks(int $seconds) : int {
        return $seconds * 20;
    }

    /**
     * Fill the borders of the inventory with gray glass.
     *
     * @param Inventory $inventory
     */
    public static function fillBorders(Inventory $inventory, Item $glassType, array $excludedSlots = []): void
    {
        $size = $inventory->getSize();
        $rows = intdiv($size, 9); // Calculate the number of rows, using integer division
    
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < 9; $col++) {
                $slot = $row * 9 + $col;
    
                if (!in_array($slot, $excludedSlots) && ($col === 0 || $col === 8 || $row === 0 || $row === $rows - 1)) {
                    $item = clone $glassType;
                    $item->setCustomName(" ");
                    $inventory->setItem($slot, $item);
                }
            }
        }
    }

    public static function createScroll(string $scroll, int $amount = 1, int $rate = 100): ?Item {
        $item = VanillaItems::AIR()->setCount($amount);
        $cfg = self::getConfiguration("config.yml");
        switch ($scroll) {
            case "whitescroll":
                $item = StringToItemParser::getInstance()->parse($cfg->getNested("white-scroll.item.type"));

                if ($item !== null) {
                    $item->setCount($amount);
                    $item->setCustomName(TextFormat::colorize($cfg->getNested("white-scroll.item.name")));

                    $lore = $cfg->getNested("white-scroll.item.lore");
                    $item->setLore(array_map(function ($line) {
                        return TextFormat::colorize($line);
                    }, $lore));

                    $item->getNamedTag()->setString("advancedscrolls", "whitescroll");
                } else {
                    Loader::getInstance()->getLogger()->warning("Invalid parsed item for scroll: $scroll");
                }   
                break;
            case "blackscroll":
                $item = StringToItemParser::getInstance()->parse($cfg->getNested("items.black-scroll.type"))->setCount($amount);    

                $item->setCustomName(TextFormat::colorize($cfg->getNested("items.black-scroll.name")));

                $lore = $cfg->getNested("items.black-scroll.lore");
                $item->setLore(array_map(function ($line) {
                    return TextFormat::colorize($line);
                }, str_replace("{success}", $rate, $lore)));

                $item->getNamedTag()->setString("advancedscrolls", "blackscroll");
                $item->getNamedTag()->setInt("blackscroll-success", $rate);
                break;

        }

        return $item;
    }

    public static function createArmorSet(string $setType, string $piece, int $amount = 1): ?Item {
        $cfg = Utils::getConfiguration("armorSets/$setType.yml");

        if ($cfg === null) {
            Loader::getInstance()->getLogger()->warning("The set type '$setType' does not exist.");
            return null;
        }

        $validPieces = ['helmet', 'chestplate', 'leggings', 'boots'];
        if (!in_array($piece, $validPieces) && $piece !== 'ALL') {
            Loader::getInstance()->getLogger()->warning("Invalid armor piece specified: '$piece'.");
            return null;
        }

        $materialType = strtoupper($cfg->get('material', 'DIAMOND'));
        $items = $cfg->get('items');

        if ($piece === 'ALL') {
            $itemsToGive = [];
            foreach ($validPieces as $validPiece) {
                if (isset($items[$validPiece])) {
                    $itemsToGive[] = self::createItem($materialType, $items[$validPiece], $validPiece, $amount);
                }
            }
            return $itemsToGive;
        }

        if (!isset($items[$piece])) {
            Loader::getInstance()->getLogger()->warning("The piece '$piece' does not exist in the set type '$setType'.");
            return null;
        }

        return self::createItem($materialType, $items[$piece], $piece, $amount);
    }

    private static function createItem(string $materialType, array $itemConfig, string $piece, int $amount): ?Item {
        $item = null;

        $pieceMap = [
            'helmet' => [
                'DIAMOND' => VanillaItems::DIAMOND_HELMET(),
                'LEATHER' => VanillaItems::LEATHER_HELMET(),
                'IRON' => VanillaItems::IRON_HELMET(),
                'GOLD' => VanillaItems::GOLDEN_HELMET(),
                'CHAIN' => VanillaItems::CHAINMAIL_HELMET(),
            ],
            'chestplate' => [
                'DIAMOND' => VanillaItems::DIAMOND_CHESTPLATE(),
                'LEATHER' => VanillaItems::LEATHER_CHESTPLATE(),
                'IRON' => VanillaItems::IRON_CHESTPLATE(),
                'GOLD' => VanillaItems::GOLDEN_CHESTPLATE(),
                'CHAIN' => VanillaItems::CHAINMAIL_CHESTPLATE(),
            ],
            'leggings' => [
                'DIAMOND' => VanillaItems::DIAMOND_LEGGINGS(),
                'LEATHER' => VanillaItems::LEATHER_LEGGINGS(),
                'IRON' => VanillaItems::IRON_LEGGINGS(),
                'GOLD' => VanillaItems::GOLDEN_LEGGINGS(),
                'CHAIN' => VanillaItems::CHAINMAIL_LEGGINGS(),
            ],
            'boots' => [
                'DIAMOND' => VanillaItems::DIAMOND_BOOTS(),
                'LEATHER' => VanillaItems::LEATHER_BOOTS(),
                'IRON' => VanillaItems::IRON_BOOTS(),
                'GOLD' => VanillaItems::GOLDEN_BOOTS(),
                'CHAIN' => VanillaItems::CHAINMAIL_BOOTS(),
            ],
        ];

        if (isset($pieceMap[$piece][$materialType])) {
            $item = $pieceMap[$piece][$materialType]->setCount($amount);
        } else {
            Loader::getInstance()->getLogger()->warning("Invalid material type: '$materialType'.");
            return null;
        }

        $item->setCustomName(TextFormat::colorize($itemConfig['name']));
        $item->setLore(array_map([TextFormat::class, 'colorize'], $itemConfig['lore']));

        if (isset($itemConfig['enchants'])) {
            foreach ($itemConfig['enchants'] as $enchantConfig) {
                $enchant = $enchantConfig['enchant'];
                $level = self::parseLevel($enchantConfig['level']);

                $enchantment = StringToEnchantmentParser::getInstance()->parse($enchant);
                if ($enchantment !== null) {
                    $item->addEnchantment(new EnchantmentInstance($enchantment, $level));
                }
            }
        }

        return $item;
    }

    public static function parseLevel(string $level): int {
        if (preg_match('/\{(\d+)-(\d+)\}/', $level, $matches)) {
            $min = (int) $matches[1];
            $max = (int) $matches[2];
            return mt_rand($min, $max);
        }
        return (int) $level;
    }

    public static function parseDynamicMessage(string $message): string {
        $pattern = "/<random_word>(.*?)<\/random_word>/";
        preg_match($pattern, $message, $matches);

        if (isset($matches[1])) {
            $wordCandidates = explode(",", $matches[1]);
            $randomIndex = mt_rand(0, count($wordCandidates) - 1);
            $word = $wordCandidates[$randomIndex];

            return str_replace("<random_word>" . $matches[1] . "</random_word>", $word, $message);
        }

        return $message;
    }
    
    /**
     * @param Item $item
     * @return bool
     */
    public static function hasTag(Item $item, string $name, string $value = "true"): bool {
        $namedTag = $item->getNamedTag();
        if ($namedTag instanceof CompoundTag) {
            $tag = $namedTag->getTag($name);
            return $tag instanceof StringTag && $tag->getValue() === $value;
        }
        return false;
    }

    public static function applyDisplayEnchant(Item $item): void {
        $item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(CustomEnchantmentIds::FAKE_ENCH_ID)));
    }

    /**
     * Gets the block instance from a string name
     *
     * @param string $blockName
     * @return Block
     */
    public static function getBlockFromString(string $blockName): Block {
        switch (strtoupper($blockName)) {
            case 'GOLD_ORE':
                return VanillaBlocks::GOLD_ORE();
            case 'IRON_ORE':
                return VanillaBlocks::IRON_ORE();
            case 'COAL_ORE':
                return VanillaBlocks::COAL_ORE();
            case 'EMERALD_ORE':
                return VanillaBlocks::EMERALD_ORE();
            case 'COPPER_ORE':
                return VanillaBlocks::COPPER_ORE();
            case 'REDSTONE_ORE':
                return VanillaBlocks::REDSTONE_ORE();
            case 'LAPIS_ORE':
                return VanillaBlocks::LAPIS_LAZULI_ORE();
            case 'DIAMOND_ORE':
                return VanillaBlocks::DIAMOND_ORE();
            case 'GOLD_BLOCK':
                return VanillaBlocks::GOLD();
            case 'IRON_BLOCK':
                return VanillaBlocks::IRON();
            case 'COAL_BLOCK':
                return VanillaBlocks::COAL();
            case 'EMERALD_BLOCK':
                return VanillaBlocks::EMERALD();
            case 'COPPER_BLOCK':
                return VanillaBlocks::COPPER();
            case 'REDSTONE_BLOCK':
                return VanillaBlocks::REDSTONE();
            case 'LAPIS_BLOCK':
                return VanillaBlocks::LAPIS_LAZULI();
            case 'DIAMOND_BLOCK':
                return VanillaBlocks::DIAMOND();
            default:
                return VanillaBlocks::AIR();
        }
    }

    public static function createRandomCEBook(string $rarity, int $amount = 1): Item {
        $groupData = CEGroups::getGroupData($rarity);
        if (!$groupData) {
            $groupData = CEGroups::getGroupData(CEGroups::getFallbackGroup());
        }

        $config = self::getConfiguration("config.yml");
        $bookConfig = $config->getNested("enchanter-books", []);
        $bookType = $bookConfig['type'] ?? 'book';
        $bookName = $bookConfig['name'] ?? '&r&l{group-color}{group-name} Enchantment Book &r&7(Right Click)';
        $bookLore = $bookConfig['lore'] ?? [
            '&r&7Examine to receive a random',
            '&r&f{group-name} &7enchantment book'
        ];

        $item = StringToItemParser::getInstance()->parse($bookType)->setCount($amount);

        $color = CEGroups::translateGroupToColor($groupData['id']);
        $name = str_replace(['{group-color}', '{group-name}'], [$color, $groupData['group_name']], $bookName);
        $lore = array_map(function ($line) use ($color, $groupData) {
            return TextFormat::colorize(str_replace(['{group-color}', '{group-name}'], [$color, $groupData['group_name']], $line));
        }, $bookLore);

        $item->setCustomName(TextFormat::colorize($name));
        $item->setLore($lore);
        $item->getNamedTag()->setString("random_book", strtolower($rarity));

        return $item;
    }

    public static function createEnchantmentBook(Enchantment $enchantment, int $level = 1, int $successChance = 100, int $destroyChance = 100): ?Item {
        $config = self::getConfiguration("config.yml");
        $bookConfig = $config->getNested("enchantment-book", []);
        $bookItemType = $bookConfig['item']['type'] ?? 'enchanted_book';

        $item = StringToItemParser::getInstance()->parse($bookItemType)->setCount(1);

        $rarity = $enchantment->getRarity();
        $color = CEGroups::translateGroupToColor($rarity);
        $groupName = CEGroups::getGroupName($rarity);

        $name = str_replace(
            ['{group-color}', '{enchant-no-color}', '{level}'],
            [$color, ucfirst($enchantment->getName()), self::getRomanNumeral($level)],
            $bookConfig['name']
        );

        $description = "";
        $appliesTo = "";
        if ($enchantment instanceof CustomEnchantment) {
            $description = $enchantment->getDescription();
            $enchantConfig = self::getConfiguration("enchantments.yml");
            $enchantData = $enchantConfig->get($enchantment->getName(), []);
            $appliesTo = $enchantData['applies-to'] ?? "Unknown";
        }

        $lore = array_map(function ($line) use ($color, $enchantment, $successChance, $destroyChance, $level, $groupName, $description, $appliesTo) {
            return TextFormat::colorize(str_replace(
                ['{group-color}', '{enchant-no-color}', '{description}', '{level}', '{success}', '{destroy}', '{applies-to}', '{max-level}'],
                [$color, ucfirst($enchantment->getName()), $description, self::getRomanNumeral($level), $successChance, $destroyChance, $appliesTo, $enchantment->getMaxLevel()],
                $line
            ));
        }, $bookConfig['lore']);

        $item->setCustomName(TextFormat::colorize($name));
        $item->setLore($lore);
        $item->getNamedTag()->setString("enchant_book", strtolower($enchantment->getName()));
        $item->getNamedTag()->setInt("level", $level);
        $item->getNamedTag()->setInt("successrate", $successChance);
        $item->getNamedTag()->setInt("destroyrate", $destroyChance);

        return $item;
    }
}

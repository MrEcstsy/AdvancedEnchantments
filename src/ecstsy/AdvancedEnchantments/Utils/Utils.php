<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantmentIds;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Farmland;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Zombie;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\Position;

class Utils {

    private static $isProcessing = false;

    public static function getConfiguration(string $fileName): Config {
        $pluginFolder = Loader::getInstance()->getDataFolder();
        $filePath = $pluginFolder . $fileName;

        if (!file_exists($filePath)) {
            Loader::getInstance()->getLogger()->warning("Configuration file '$filePath' not found.");
            return null;
        }
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'yml':
            case 'yaml':
                    return new Config($filePath, Config::YAML);
    
             case 'json':
                return new Config($filePath, Config::JSON);
    
            default:
                Loader::getInstance()->getLogger()->warning("Unsupported configuration file format for '$filePath'.");
                return null;
        }
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
    public static function spawnParticle(Position $position, string $particleName, int $radius = 5): void {
        $blockPosition = $position->asVector3();
        $world = $position->getWorld();

        $packet = new SpawnParticleEffectPacket();
        $packet->position = $blockPosition;
        $packet->particleName = $particleName;

        $bb = new AxisAlignedBB(
            $blockPosition->getX() - $radius,
            $blockPosition->getY() - $radius,
            $blockPosition->getZ() - $radius,
            $blockPosition->getX() + $radius,
            $blockPosition->getY() + $radius,
            $blockPosition->getZ() + $radius
        );

        foreach ($world->getNearbyEntities($bb) as $entity) {
            if ($entity instanceof Player && $entity->isOnline()) {
                $entity->getNetworkSession()->sendDataPacket(clone $packet);
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
                $item = StringToItemParser::getInstance()->parse($cfg->getNested("items.white-scroll.type"));

                if ($item !== null) {
                    $item->setCount($amount);
                    $item->setCustomName(TextFormat::colorize($cfg->getNested("items.white-scroll.name")));

                    $lore = $cfg->getNested("items.white-scroll.lore");
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
                $item->setLore(array_map(function ($line) use ($rate) {
                    return TextFormat::colorize(str_replace("{success}", $rate, $line));
                }, $lore));
        
                $item->getNamedTag()->setString("advancedscrolls", "blackscroll");
                $item->getNamedTag()->setInt("blackscroll-success", $rate);
                break;
            case "transmog":
                $item = StringToItemParser::getInstance()->parse($cfg->getNested("items.transmogscroll.type"))->setCount($amount);

                $item->setCustomName(TextFormat::colorize($cfg->getNested("items.transmogscroll.name")));

                $item->setLore(array_map(function ($line) {
                    return TextFormat::colorize($line);
                }, $cfg->getNested("items.transmogscroll.lore")));

                $item->getNamedTag()->setString("advancedscrolls", "transmog");
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

    public static function createRCBook(string $group, int $amount = 1): Item {
        try {

            $groupId = CEGroups::getGroupId($group);

            if ($groupId === null) {
                throw new \InvalidArgumentException("Invalid group ID for group: $group");
            }

            $groupId = CEGroups::getGroupId($group);
            $color = CEGroups::translateGroupToColor($groupId);
            $config = self::getConfiguration("config.yml");

            if ($config === null) {
                throw new \RuntimeException("Configuration file not found.");
            }

            $bookConfig = $config->getNested("enchanter-books");

            if ($bookConfig === null) {
                throw new \RuntimeException("Enchanter books configuration not found.");
            }

            $type = $bookConfig['type'];
            $name = $bookConfig['name'];
            $lore = $bookConfig['lore'];
                
            if ($type === null || $name === null || $lore === null) {
                throw new \RuntimeException("Enchanter book type, name, or lore is missing in the configuration.");
            }

            $name = str_replace(['{group-color}', '{group-name}'], [$color, ucfirst($group)], $name);
            $lore = array_map(function($line) use ($color, $group) {
                return str_replace(['{group-color}', '{group-name}'], [$color, ucfirst($group)], $line);
            }, $lore);
                
            $item = StringToItemParser::getInstance()->parse($type);

            if ($item === null) {
                throw new \RuntimeException("Failed to parse item type: $type");
            }

            $item->setCount($amount);
            $item->setCustomName(TextFormat::colorize($name));
            $item->setLore(array_map(function($line) {
                return TextFormat::colorize($line);
            }, $lore));
                
            if ($bookConfig['force-glow']) {
                // make the item have the fake ench
            }
                
            $item->getNamedTag()->setString("random_book", $group); 
            
            return $item;

        } catch (\InvalidArgumentException $e) {
            Loader::getInstance()->getLogger()->error($e->getMessage());
        } catch (\RuntimeException $e) {
            Loader::getInstance()->getLogger()->error($e->getMessage());
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->error("An unexpected error occurred: " . $e->getMessage());
        }

        return VanillaItems::AIR();
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

    public static function createEnchantmentBook(Enchantment $enchantment, int $level = 1): ?Item {
        $config = self::getConfiguration("config.yml");
        $bookConfig = $config->getNested("enchantment-book", []);
        $bookItemType = $bookConfig['item']['type'] ?? 'enchanted_book';
        $chancesConfig = $config->getNested("chances", []);

        $item = StringToItemParser::getInstance()->parse($bookItemType)->setCount(1);
    
        $rarity = $enchantment->getRarity();
        $color = CEGroups::translateGroupToColor($rarity);
        $groupName = CEGroups::getGroupName($rarity);
    
        $name = str_replace(
            ['{group-color}', '{enchant-no-color}', '{level}'],
            [$color, ucfirst($enchantment->getName()), self::getRomanNumeral($level)],
            $bookConfig['name']
        );
    
        $descriptionLines = [];
        $appliesTo = "";
        if ($enchantment instanceof CustomEnchantment) {
            $enchantConfig = self::getConfiguration("enchantments.yml");
            $enchantData = $enchantConfig->get($enchantment->getName(), []);
            $descriptionLines = $enchantData['description'] ?? [];
            $appliesTo = $enchantData['applies-to'] ?? "Unknown";
        }
    
        $descriptionText = implode("\n", $descriptionLines);  
    
        $loreLines = [];
        foreach ($bookConfig['lore'] as $line) {
            $line = str_replace(
                ['{group-color}', '{enchant-no-color}', '{level}', '{success}', '{destroy}', '{applies-to}', '{max-level}', '{description}'],
                [$color, ucfirst($enchantment->getName()), self::getRomanNumeral($level), '{success}', '{destroy}', $appliesTo, $enchantment->getMaxLevel(), $descriptionText],
                $line
            );
            $loreLines[] = TextFormat::colorize($line);
        }
    
        $item->setCustomName(TextFormat::colorize($name));
        $item->setLore($loreLines);
        $item->getNamedTag()->setString("enchant_book", strtolower($enchantment->getName()));
        $item->getNamedTag()->setInt("level", $level);

        if ($chancesConfig['random'] ?? false) {
            $successChance = mt_rand(0, 100);
            $destroyChance = mt_rand(0, 100);
        } else {
            $successRange = explode("-", $chancesConfig['success'] ?? "100");
            $destroyRange = explode("-", $chancesConfig['destroy'] ?? "0");
            
            $successChance = isset($successRange[1]) ? mt_rand((int)$successRange[0], (int)$successRange[1]) : (int)$successRange[0];
            $destroyChance = isset($destroyRange[1]) ? mt_rand((int)$destroyRange[0], (int)$destroyRange[1]) : (int)$destroyRange[0];
        }

        $item->getNamedTag()->setInt("successrate", $successChance);
        $item->getNamedTag()->setInt("destroyrate", $destroyChance);

        foreach ($loreLines as &$line) {
            $line = str_replace(
                ['{success}', '{destroy}'],
                [$successChance, $destroyChance],
                $line
            );
        }
        $item->setLore($loreLines);
    
        return $item;
    }

    public static function getSeedItem(?string $seedType): ?Item {
        $seedTypeLower = $seedType !== null ? strtolower($seedType) : null;
    
        switch ($seedTypeLower) {
            case "wheat":
                return VanillaItems::WHEAT_SEEDS();
            case "carrot":
                return VanillaItems::CARROT();
            case "potato":
                return VanillaItems::POTATO();
            case "beetroot":
                return VanillaItems::BEETROOT_SEEDS();
            default:
                return VanillaItems::WHEAT_SEEDS();
        }
    }
    

    public static function getSeedBlock(Item $seedItem): ?Block {
        $blockName = strtolower(str_replace(" ", "_", $seedItem->getName()));
        $block = StringToItemParser::getInstance()->parse($blockName);
        if ($block instanceof Block) {
            return $block;
        }
        return null;
    }

    public static function getTargetPlayer(string $targetType, Player $sourcePlayer): ?Player {
        switch ($targetType) {
            case 'self':
                return $sourcePlayer;
            case 'victim':
                return $sourcePlayer;
            case 'attacker':
                return $sourcePlayer;    
            default:
                return null;
        }
    }

    public static function plantSeeds(Player $player, Block $targetBlock, int $radius, ?string $seedType): void {
        $world = $player->getWorld();
        $seedItem = Utils::getSeedItem($seedType);
        if ($seedItem === null) {
            return;
        }
    
        $inventory = $player->getInventory()->getContents();
        $seedsCount = 0;
        foreach ($inventory as $item) {
            if ($item->getTypeId() === $seedItem->getTypeId()) {
                $seedsCount += $item->getCount();
            }
        }
    
        $positions = [];
        for ($x = -$radius; $x <= $radius; $x++) {
            for ($z = -$radius; $z <= $radius; $z++) {
                $positions[] = $targetBlock->getPosition()->add($x, 0, $z);
            }
        }
    
        foreach ($positions as $position) {
            if ($seedsCount <= 0) {
                break;
            }
            $block = $world->getBlock($position);
            if ($block instanceof Farmland) {
                $aboveBlock = $world->getBlock($position->up());
                if ($aboveBlock->isSolid() === false) {
                    $seedBlock = Utils::getSeedBlock($seedItem);
                    if ($seedBlock !== null) {
                        $world->setBlock($position->up(), $seedBlock);
                        $player->getInventory()->removeItem($seedItem->setCount(1));
                        $seedsCount--;
                    }
                }
            }
        }
    }

    /**
     * Applies the enchantment effects
     *
     * @param Entity $source
     * @param ?Entity $target
     * @param array $effects
     */
    public static function applyPlayerEffects(Entity $source, ?Entity $target, array $effects, ?callable $callback = null): void {
            if (empty($effects)) {
                return;
            }
        
            $effect = array_shift($effects);
        
            if (!isset($effect['type'])) {
                self::applyPlayerEffects($source, $target, $effects, $callback);
                return;
            }

            switch ($effect['type']) {
                case 'PLAY_SOUND':
                    if (isset($effect['sound']) && isset($effect['target'])) {
                        if ($effect['target'] === 'attacker') {
                            if ($source instanceof Living) {
                                Utils::playSound($source, $effect['sound'], $effect['volume'] ?? 1);

                            }
                        }                   
                    }
                    break;
                case "ADD_PARTICLE":
                    if (isset($effect['particle'])) {
                        if ($target instanceof Player) {
                            Utils::spawnParticle($target->getPosition(), $effect['particle']);
                        }                  
                    }
                case 'ADD_POTION':
                    if (isset($effect['potion'], $effect['duration'], $effect['amplifier'], $effect['target'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
                        }
    
                        if ($effect['target'] === 'attacker') {
                            if ($source instanceof Living) {
                                $source->getEffects()->add(new EffectInstance($potion, $effect['duration'], $effect['amplifier']));
                            }
                        } elseif ($effect['target'] === 'victim') {
                            if ($target instanceof Living) {
                                $target->getEffects()->add(new EffectInstance($potion, $effect['duration'], $effect['amplifier']));
                            }
                        }
                    }
                    break;
                case "REMOVE_POTION":
                    if (isset($effect['potion'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
                        }

                        if ($target instanceof Player) {
                            $target->getEffects()->remove($potion);
                        }
                    }
                    break;
                case "DOUBLE_DAMAGE":
                            
                    break;
                case "WAIT":
                    if (isset($effect['time'])) {
                        $task = new ClosureTask(function() use ($source, $target, $effects): void {
                            self::applyPlayerEffects($source, $target, $effects);
                        });
                        Loader::getInstance()->getScheduler()->scheduleDelayedTask($task, $effect['time']);
                        return;
                    }
                    break;
                case "DO_HARM":
                    if (isset($effect['value'])) {
                        $damage = Utils::parseLevel($effect['value']);

                        if (isset($effect['aoe'])) {
                            $radius = $effect['aoe']['radius'] ?? 2;
                            $aoeTarget = $effect['aoe']['target'] ?? 'damageable';
                            $center = $effect['target'] === 'victim' ? $target : $source;

                            foreach ($center->getWorld()->getNearbyEntities($center->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $entity) {
                                if ($aoeTarget === 'damageable' && $entity instanceof Living && $entity !== $target) {
                                    $entity->attack(new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
                                }
                            }
                        } else {
                            if ($effect['target'] === 'victim') {
                                $target->attack(new EntityDamageEvent($target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
                            } elseif ($effect['target'] === 'attacker') {
                                $source->attack(new EntityDamageEvent($source, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
                            }
                        }
                    }
                    break;
                case "MESSAGE":
                    if (isset($effect['text'])) {
                        $message = TextFormat::colorize($effect['text']);
                        if ($source instanceof Player) {
                            if ($effect['target'] === 'self') {
                                $source->sendMessage($message);
                            } elseif ($effect['target'] === 'victim' && $target instanceof Player) {
                                $target->sendMessage($message);
                            } elseif ($effect['target'] === 'attacker' && $source instanceof Player && $target instanceof Player) {
                                $source->sendMessage($message);
                            }
                        }
                    }
                    break;
                case "EFFECT_STATIC":
                    if (isset($effect['effect'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['effect']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['effect'] . "'");
                        }

                        if ($source instanceof Player) {
                            $source->getEffects()->add(new EffectInstance($potion, 20 * 999999, $effect['amplifier'] ?? 0));
                        }
                    }    
                    break;
                case "EXP":
                    if (isset($effect['formula'])) {
                        if ($source instanceof Player) {
                            $hand = $source->getInventory()->getItemInHand();
            
                            foreach ($hand->getEnchantments() as $enchantmentInstance) {
                                $enchantment = $enchantmentInstance->getType();
                                if ($enchantment instanceof CustomEnchantment) {
                                    $level = $enchantmentInstance->getLevel();
                                    if ($callback !== null) {
                                        $callback($effect['formula'], $level);
                                    }
                                }
                            }
                        }
                    }
                    break;
                case "DROP_HEAD":
                    // use CB Heads plugin for this... or use their code?
                    break;    
                case 'PULL_AWAY':
                    if (isset($effect['distance']) && isset($effect['target'])) {
                        $distance = $effect['distance'];
                    
                        if ($effect['target'] === 'victim') {
                            if ($target instanceof Living && $source instanceof Living) {
                                $attackerPosition = $source->getPosition();
                                $targetPosition = $target->getPosition();
                                $direction = $targetPosition->subtract($attackerPosition->getX(), $attackerPosition->getY(), $attackerPosition->getZ())->normalize();
                                $pushVector = $direction->multiply($distance);
                        
                                $target->setMotion($pushVector);
                            }
                        } elseif ($effect['target'] === 'attacker') {
                            if ($source instanceof Living && $target instanceof Living) {
                                $attackerPosition = $target->getPosition();
                                $targetPosition = $source->getPosition();
                                $direction = $targetPosition->subtract($attackerPosition->getX(), $attackerPosition->getY(), $attackerPosition->getZ())->normalize();
                                $pushVector = $direction->multiply($distance);
                        
                                $source->setMotion($pushVector);
                            }
                        }
                    }
                    break;
                    
                case 'BURN':
                    if (isset($effect['time']) && isset($effect['target'])) {
                        if ($effect['target'] === 'victim') {
                            $source->setOnFire($effect['time']);
                        } elseif ($effect['target'] === 'attacker') {
                            $target->setOnFire($effect['time']);
                        }
                    }
                    break;
                case 'ADD_FOOD':
                    if (isset($effect['amount']) && isset($effect['target'])) {
                        if ($effect['target'] === 'victim') {
                            if ($target instanceof Player) {
                                $target->getHungerManager()->addFood($effect['amount']);
                            }
                        } elseif ($effect['target'] === 'attacker') {
                            if ($source instanceof Player) {
                                $source->getHungerManager()->addFood($effect['amount']);
                            }
                        } elseif ($effect['target'] === 'self') {
                            if ($source instanceof Player) {
                                $source->getHungerManager()->addFood($effect['amount']);
                            }
                        }
                    }
                    break;    
                case 'REMOVE_FOOD':
                    if (isset($effect['amount']) && isset($effect['target'])) {
                        if ($effect['target'] === 'victim') {
                            if ($target instanceof Player) {
                                $target->getHungerManager()->setFood($target->getHungerManager()->getFood() - $effect['amount']);
                            }
                        } elseif ($effect['target'] === 'attacker') {
                            if ($source instanceof Player) {
                                $source->getHungerManager()->setFood($source->getHungerManager()->getFood() - $effect['amount']);
                            }
                        }
                    }
                    break;
                case 'GAURD':
                    // Summon a mob on defense
                    break;         
                case 'PULL_CLOSER':
                        if (isset($effect['distance']) && isset($effect['target'])) {
                            $distance = $effect['distance'];
                            $targetEntity = ($effect['target'] === 'victim') ? $target : $source;
                    
                            if ($targetEntity instanceof Entity) {
                                $attackerPosition = $source->getPosition();
                                $targetPosition = $targetEntity->getPosition();
                                $direction = $attackerPosition->subtract($targetPosition->getX(), $targetPosition->getY(), $targetPosition->getZ())->normalize();
                                $pullVector = $direction->multiply($distance);
                    
                                $targetEntity->setMotion($pullVector);
                            }
                        }
                    break;                    
                case 'LIGHTNING':
                    // Strike lighting at the entity
                    break;
                case 'EXTINGUISH':
                    if (isset($effect['target'])) {
                        if ($effect['target'] === 'victim') {
                            $target->extinguish();
                        } elseif ($effect['target'] === 'attacker') {
                            $source->extinguish();
                        }
                    }
                    break;
                case 'ADD_DURABILITY':
                    // Not sure if its possible to do this anymore?
                    break;             
                case 'ADD_DURABILITY_ITEM':
                    if (isset($effect['amount']) && $source instanceof Player) {
                        $item = $source->getInventory()->getItemInHand();
                        if ($item instanceof Durable && $item->getDamage() > 0) {
                            $newDurability = $item->getDamage() - $effect['amount'];
                            $item->setDamage(max(0, $newDurability)); 
                            $source->getInventory()->setItemInHand($item);
                        }
                    }
                    break;
                case 'BOOST':
                    // Launch entity or player into the air e.g launching victim into air when low hp
                    break;
                case 'TELEPORT_BEHIND':
                    // TODO: make teleported player face the oponent
                    if (isset($effect['target'])) {
                        $multiplier = 1.5; 

                        if ($effect['target'] === 'victim' && $source instanceof Living && $target instanceof Living) {
                            $direction = $source->getDirectionVector();
                            $positionBehind = $source->getPosition()->subtract($direction->multiply($multiplier)->getX(), $direction->multiply($multiplier)->getY(), $direction->multiply($multiplier)->getZ());

                            $highestY = $source->getWorld()->getHighestBlockAt((int)$positionBehind->x, (int)$positionBehind->z);
                            $positionBehind->y = $highestY + 1;

                            $target->teleport($positionBehind);
                        } elseif ($effect['target'] === 'attacker' && $source instanceof Living && $target instanceof Living) {
                            $direction = $target->getDirectionVector();
                            $positionBehind = $target->getPosition()->subtract($direction->multiply($multiplier)->getX(), $direction->multiply($multiplier)->getY(), $direction->multiply($multiplier)->getZ());

                            $highestY = $target->getWorld()->getHighestBlockAt((int)$positionBehind->x, (int)$positionBehind->z);
                            $positionBehind->y = $highestY + 1;

                            $source->teleport($positionBehind);
                        }
                    }
                    break;
                case 'ADD_HEALTH':
                    if (isset($effect['amount']) && isset($effect['target'])) {
                        $amount = self::parseLevel($effect['amount']); 

                        if ($effect['target'] === 'victim') {
                            if ($target instanceof Living) {
                                $target->setHealth($target->getHealth() + $amount);
                            }
                        } elseif ($effect['target'] === 'attacker') {
                            if ($source instanceof Living) {
                                $source->setHealth($source->getHealth() + $amount);
                            }
                        } elseif ($effect['target'] === 'self') {
                            if ($source instanceof Living) {
                                $source->setHealth($source->getHealth() + $amount);
                            }
                        }
                    }
                    break;
                case 'CURE':
                    if (isset($effect['potion']) && isset($effect['target'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
                        }

                        if ($potion->isBad()) {
                            if ($effect['target'] === 'victim') {
                                if ($target instanceof Living) {
                                    $target->getEffects()->remove($potion);
                                }
                            } elseif ($effect['target'] === 'attacker') {
                                if ($source instanceof Living) {
                                    $source->getEffects()->remove($potion);
                                }
                            }
                        }
                    }
                    break;
                case 'NEGATE_DAMAGE':
                    // Negate damage
                    break;    
                case 'DISABLE_ACTIVATION':
                    // Prevents an enchantment from activating
                    break;
                case 'ADD_DURABILITY_CURRENT_ITEM':
                    if (isset($effect['amount']) && isset($effect['target'])) {

                        if ($effect['target'] === 'victim') {
                            if ($target instanceof Player) {
                                $item = $target->getInventory()->getItemInHand();
                                if ($item instanceof Durable) {
                                    $newDurability = $item->getDamage() - $effect['amount'];
                                    $item->setDamage(max(0, $newDurability)); 
                                    $target->getInventory()->setItemInHand($item); 
                                }
                            }
                        } elseif ($effect['target'] === 'attacker') {
                            if ($source instanceof Player) {
                                $item = $source->getInventory()->getItemInHand();
                                if ($item instanceof Durable) {
                                    $newDurability = $item->getDamage() - $effect['amount'];
                                    $item->setDamage(max(0, $newDurability)); 
                                    $source->getInventory()->setItemInHand($item); 
                                }
                            }
                        }
                    }
                    break;            
                case 'REMOVE_ENCHANT':
                    // Removes an enchantment from an item
                    // used for the example above and for any other reason ppl can be creative...
                    break;   
                case 'FIREBALL':
                    // Arrows turn into fireballs 
                    break;
                case 'RESET_COMBO':
                    // Resets combo
                    break;
                case 'KILL':
                    // Not sure how this would work, but would kill x amount of entities in a mob stack
                    break;
                case 'STEAL_HEALTH':
                    if (isset($effect['amount']) && isset($effect['target'])) {
                        $amount = self::parseLevel($effect['amount']); 
                    
                        if ($effect['target'] === 'attacker') {
                            $currentHealth = $source->getHealth();
                            $newHealth = max(0, min($currentHealth + $amount, $source->getMaxHealth()));
                            $source->setHealth($newHealth);
                    
                            $currentVictimHealth = $target->getHealth();
                            $newVictimHealth = max(0, $currentVictimHealth - $amount);
                            $target->setHealth($newVictimHealth);
                        } elseif ($effect['target'] === 'victim') {
                            $currentHealth = $target->getHealth();
                            $newHealth = max(0, min($currentHealth + $amount, $target->getMaxHealth()));
                            $target->setHealth($newHealth);
                    
                            $currentAttackerHealth = $source->getHealth();
                            $newAttackerHealth = max(0, $currentAttackerHealth - $amount);
                            $source->setHealth($newAttackerHealth);
                        }
                    }
                    break;
                case 'HALF_DAMAGE':
                    // Intended to make attacker do half the damage, can be paired with 'ADD_DURABILITY_CURRENT_ITEM' to make an enchant that does 'half damage' in exchange for repairing the item
                    break;           
                case 'REPAIR':
                    break;
                case 'REMOVE_RANDOM_ARMOR':
                    break;
                case 'SPAWN_ARROWS':
                    // Spawn arrows over opponent
                    break;         
                case 'KEEP_ON_DEATH':
                    break;
                case 'DISARM':
                    break;
                case 'REVIVE':
                    // 'Revive' the target when killed
                    break;              
                case 'PUMPKIN':
                    // Show the pumpin vignette to the target
                    break;                  
                case "STOP_KNOCKBACK":

                    break;
                case 'REMOVE_SOULS':

                    break;
                case 'MORE_DROPS':

                    break;      
                case 'SHUFFLE_HOTBAR':

                    break;           
                case 'ADD_SOULS':
                        
                    break;
                case 'BLOOD':
                    if (isset($effect['target'])) {
                        if ($effect['target'] === 'attacker') {
                            $source->getWorld()->addParticle($source->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
                        } elseif ($effect['target'] === 'victim') {
                            $target->getWorld()->addParticle($target->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
                        }
                    }
                    break;    
            }

            self::applyPlayerEffects($source, $target, $effects, $callback);
    }

    public static function applyBlockEffects(Entity $source, Block $block, array $effects): void {
        foreach ($effects as $effect) {
            if (!isset($effect['type'])) {
                continue;
            }
    
            switch ($effect['type']) {
                case 'SET_BLOCK':
                    if (isset($effect['from'], $effect['to'])) {
                        $fromBlockName = str_replace(' ', '_', strtoupper($effect['from'])); 
                        $toBlockName = str_replace(' ', '_', strtoupper($effect['to'])); 
        
                        if ($block instanceof Block && $block->getPosition() instanceof Position) {
                            $blockName = str_replace(' ', '_', strtoupper($block->getName())); 
        
                            if ($blockName === $fromBlockName) {
                                $newBlock = Utils::getBlockFromString($toBlockName);
                                if ($newBlock instanceof Block) {
                                    $block->getPosition()->getWorld()->setBlock($block->getPosition(), $newBlock);
                                } 
                            }
                        }
                    }
                    break;
                case "PLANT_SEEDS":
                    if (isset($effect['radius'])) {
                        $radius = (int)$effect['radius'];
                        $seedType = $effect['seed-type'] ?? null;

                        if ($source instanceof Player) {
                            self::plantSeeds($source, $block, $radius, $seedType);
                        }
                    }    
                break;
                case "SMELT":
                    // Smelt mined blocks
                    break;    
                case "EXP":
                    // for increasing xp drop on mined blocks
                    break; 
                case 'BREAK_BLOCK':
                    // Breaks blocks in radius
                break;        
                case 'BREAK_TREE':
                    // Breaks an entire tree
                    break; 
                case 'VEINMINER':

                    break;
                case 'TP_DROPS':
                        // Teleport drops to players inventory sorta like auto pickup
                    break;                                 
            }
        }
    }

    public static function checkConditions(array $conditions, Entity $player, Entity $victim): bool {
        $conditionMet = false;
        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? null;
            $conditionMode = $condition['condition_mode'] ?? 'allow';
            $force = $condition['force'] ?? false;

            if ($type === 'VICTIM_HEALTH') {
                $greaterThan = $condition['greater-than'] ?? 0;
                if ($conditionMode === 'stop' && $victim->getHealth() > $greaterThan) {
                    return false; // Stop if victim's health is greater than the specified value
                } elseif ($conditionMode === 'allow' && $victim->getHealth() <= $greaterThan) {
                    $conditionMet = true; // Allow if victim's health is less than or equal to the specified value
                }
            } elseif ($type === 'IS_SNEAKING') {
                $value = $condition['value'] ?? false;
                $target = $condition['target'];
    
                if ($target === 'victim') {
                    if ($victim instanceof Living && $victim->isSneaking() === $value) {
                        if ($conditionMode === 'allow') {
                            $conditionMet = true; // Allow if the sneaking condition matches
                        } elseif ($conditionMode === 'stop') {
                            return false; // Stop if the sneaking condition matches
                        }
                    }
                } elseif ($target === 'attacker') {
                    if ($player instanceof Living && $player->isSneaking() === $value) {
                        if ($conditionMode === 'allow') {
                            $conditionMet = true; // Allow if the sneaking condition matches
                        } elseif ($conditionMode === 'stop') {
                            return false; // Stop if the sneaking condition matches
                        }
                    }
                }
            } elseif ($type === 'IS_HOLDING') {
                $target = $condition['target'] ?? 'self';
                $value = strtolower($condition['value'] ?? '');
                error_log("Target: $target, Value: $value");
    
                $handItem = null;
    
                if ($target === 'self') {
                    if ($player instanceof Player) {
                        $handItem = $player->getInventory()->getItemInHand();
                    }
                } elseif ($target === 'victim') {
                    if ($victim instanceof Player) {
                        $handItem = $victim->getInventory()->getItemInHand();
                    }
                } elseif ($target === 'attacker') {
                    if ($player instanceof Player) {
                        $handItem = $player->getInventory()->getItemInHand();
                    }
                }
    
                if ($handItem !== null) {
                    if ($value === 'sword') {
                        if ($handItem instanceof Sword) {
                            $conditionMet = ($conditionMode === 'allow');
                        } else {
                            $conditionMet = ($conditionMode === 'stop');
                        }
                    } elseif ($value === 'axe') {
                        if ($handItem instanceof Axe) {
                            $conditionMet = ($conditionMode === 'allow');
                        } else {
                            $conditionMet = ($conditionMode === 'stop');
                        }
                    } elseif ($value === 'bow') {
                        if ($handItem instanceof Bow) {
                            $conditionMet = ($conditionMode === 'allow');
                        } else {
                            $conditionMet = ($conditionMode === 'stop');
                        }
                    }
                } else {
                    $conditionMet = ($conditionMode === 'allow');
                }
            } elseif ($type === 'IS_MOB_TYPE' && $victim !== null) {
                $mobs = $condition['mobs'] ?? [];
                $mobType = $victim->getNetworkTypeId();
                $networkMobTypes = array_map([Utils::class, 'stringToNetworkId'], $mobs);
    
                if ($conditionMode === 'allow' && in_array($mobType, $networkMobTypes)) {
                    $conditionMet = true;
                } elseif ($conditionMode === 'stop' && !in_array($mobType, $networkMobTypes)) {
                    return false;
                }      
            } elseif ($type === 'IS_HOSTILE' && $victim !== null) {
                $isHostile = self::isHostile($victim);
                $value = $condition['value'] ?? false;
    
                if ($conditionMode === 'allow' && $isHostile === $value) {
                    $conditionMet = true;
                } elseif ($conditionMode === 'stop' && $isHostile !== $value) {
                    return false;
                }
            }
        }

        if ($conditionMet && !$force) {
            return true;
        }

        return !$force; // Default to false if no conditions are met
    }

    public static function isHostile(Entity $entity): bool {
        $hostileMobs = [
            EntityIds::SKELETON,
            EntityIds::CREEPER,
            EntityIds::SPIDER,
            EntityIds::ZOMBIE,
            EntityIds::ENDERMAN,
            EntityIds::SLIME,
            EntityIds::WITCH,
            EntityIds::BLAZE,
            EntityIds::ZOMBIE_PIGMAN,
            EntityIds::WITHER_SKELETON,
            EntityIds::STRAY,
            EntityIds::HUSK,
            EntityIds::PHANTOM,
            EntityIds::DROWNED,
            EntityIds::PILLAGER,
            EntityIds::VINDICATOR,
            EntityIds::VEX,
            EntityIds::RAVAGER,
            EntityIds::HOGLIN,
            EntityIds::PIGLIN,
            EntityIds::PIGLIN_BRUTE,
            EntityIds::PIGLIN,
            EntityIds::ZOMBIE_VILLAGER,
            EntityIds::MAGMA_CUBE,
            EntityIds::GHAST,
            EntityIds::SHULKER,
            EntityIds::GUARDIAN,
            EntityIds::ELDER_GUARDIAN,
            EntityIds::WITHER,
            EntityIds::ENDER_DRAGON,
            EntityIds::CAVE_SPIDER,
            EntityIds::SILVERFISH,
        ];
    
        return in_array($entity->getNetworkTypeId(), $hostileMobs, true);
    }
    
    public static function stringToNetworkId(string $networkId): ?string {
        switch ($networkId) {
            case 'SKELETON':
                return EntityIds::SKELETON;
                break;
            case 'CREEPER':
                return EntityIds::CREEPER;
                break;
            case 'SPIDER':
                return EntityIds::SPIDER;
                break;
            case 'ZOMBIE':
                return EntityIds::ZOMBIE;
                break;
            case 'ENDERMAN':
                return EntityIds::ENDERMAN;
                break;
            case 'SLIME':
                return EntityIds::SLIME;
                break;
            case 'WITCH':
                return EntityIds::WITCH;
                break;
            case 'BLAZE':
                return EntityIds::BLAZE;
                break;
            case 'PIG_ZOMBIE':
                return EntityIds::ZOMBIE_PIGMAN;
                break;
            case 'ZOMBIE_PIGMAN':
                return EntityIds::ZOMBIE_PIGMAN;
                break;
            case 'ENDER_DRAGON':
                return EntityIds::ENDER_DRAGON;
                break;
            case 'VINDICATOR':
                return EntityIds::VINDICATOR;
                break;
            case 'WITHER_SKELETON':
                return EntityIds::WITHER_SKELETON;
                break;
            case 'GUARDIAN':
                return EntityIds::GUARDIAN;
                break;
            case 'SHULKER':
                return EntityIds::SHULKER;
                break;
            case 'BAT':
                return EntityIds::BAT;
                break;

            default:
                return null;
                break;
        }
    }
    
    public static function removePlayerEffects(Player $player, array $effects): void {
        foreach ($effects as $effect) {
            if (!isset($effect['type'])) {
                continue;
            }

            switch ($effect['type']) {
                case 'EFFECT_STATIC':
                    if (isset($effect['effect'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['effect']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['effect'] . "'");
                        }

                        $player->getEffects()->remove($potion);
                    }
                    break;

            }
        }
    }

    public static function evaluateFormula(string $formula, int $level): float {
        $formula = str_replace('{level}', $level, $formula);

        try {
            $result = eval("return $formula;");
            return (float) $result;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to evaluate formula: ' . $e->getMessage());
        }
    }

    public static function getStringToBlock(string $block): ?Block {
        $block = VanillaBlocks::AIR();
        switch (strtoupper($block)) {
            case 'NETHERRACK':
                $block = VanillaBlocks::NETHERRACK();
                break;
            case 'STONE':
                $block = VanillaBlocks::STONE();
                break;
            case 'COBBLESTONE':
                $block = VanillaBlocks::COBBLESTONE();
                break;
            case 'GRANITE':
                $block = VanillaBlocks::GRANITE();
                break;
            case 'DIORITE':
                $block = VanillaBlocks::DIORITE();
                break;
            case 'ANDESITE':
                $block = VanillaBlocks::ANDESITE();
                break;
            case 'GRASS_BLOCK':
                $block = VanillaBlocks::GRASS_BLOCK();
                break;
            case 'SAND':
                $block = VanillaBlocks::SAND();
                break;
            case 'SANDSTONE':
                $block = VanillaBlocks::SANDSTONE();
                break;
            case 'DIRT':
                $block = VanillaBlocks::DIRT();
                break;
        }

        return $block;
    }

    public static function applyBlockBreakEffect(Player $player, Block $block, int $radius): void {
        if (self::$isProcessing) {
            return;
        }
    
        if ($radius % 2 == 0) {
            foreach (self::getEffectTypeErrors('BREAK_BLOCK') as $error) {
                $player->sendMessage(TextFormat::colorize($error));
            }
            return;
        }
    
        self::$isProcessing = true;
    
        $world = $block->getPosition()->getWorld();
        $itemInHand = $player->getInventory()->getItemInHand();
        $blockPos = $block->getPosition();
    
        $halfRadius = ($radius - 1) / 2;
    
        for ($y = -$halfRadius; $y <= $halfRadius; $y++) { 
            for ($x = -$halfRadius; $x <= $halfRadius; $x++) {
                for ($z = -$halfRadius; $z <= $halfRadius; $z++) {
                    if ($x == 0 && $y == 0 && $z == 0) {
                        continue; 
                    }
                    $currentBlock = $world->getBlock($blockPos->add($x, $y, $z));
                    if ($currentBlock->getTypeId() !== BlockTypeIds::AIR) {
                        $world->useBreakOn($currentBlock->getPosition(), $itemInHand, $player, true);
                    }
                }
            }
        }
    
        self::$isProcessing = false;
    }    

    public static function getBlocksInRadius(Block $centerBlock, int $radius): array {
        $world = $centerBlock->getPosition()->getWorld();
        $blocks = [];
    
        $minX = $centerBlock->getPosition()->getX() - $radius;
        $maxX = $centerBlock->getPosition()->getX() + $radius;
        $minY = $centerBlock->getPosition()->getY() - $radius;
        $maxY = $centerBlock->getPosition()->getY() + $radius;
        $minZ = $centerBlock->getPosition()->getZ() - $radius;
        $maxZ = $centerBlock->getPosition()->getZ() + $radius;
    
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $currentBlock = $world->getBlockAt($x, $y, $z);
                    if ($currentBlock->getTypeId() !== BlockTypeIds::AIR) {
                        $blocks[] = $currentBlock;
                    }
                }
            }
        }
    
        return $blocks;
    }

    public static function getEffectTypeErrors(string $effectType): ?array {
        switch ($effectType) {
            case 'BREAK_BLOCK':
                $messages = [
                    "&r&4Failed to activate effect '&f" . $effectType . "&r&4'.",
                    "&r&cAdditional Information: &7Trench only supports odd numbers in order to work properly"
                ];
                return $messages;
                break;
        }

        return null;
    }
}


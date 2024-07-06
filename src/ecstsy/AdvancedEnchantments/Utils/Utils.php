<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantmentIds;
use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\block\Block;
use pocketmine\block\Farmland;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
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
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
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

    /**
     * Applies the enchantment effects
     *
     * @param Entity $source
     * @param ?Entity $target
     * @param array $effects
     */
    public static function applyPlayerEffects(Player $source, ?Player $target, array $effects): void {
            if (empty($effects)) {
                return;
            }
        
            $effect = array_shift($effects);
        
            if (!isset($effect['type'])) {
                self::applyPlayerEffects($source, $target, $effects);
                return;
            }

            switch ($effect['type']) {
                case 'PLAY_SOUND':
                    if (isset($effect['sound'])) {
                        if ($source instanceof Player) {
                            Utils::playSound($source, $effect['sound']);
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
                    if (isset($effect['potion'], $effect['duration'], $effect['amplifier'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['potion']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['potion'] . "'");
                        }
    
                        if ($target instanceof Player) {
                            $target->getEffects()->add(new EffectInstance($potion, $effect['duration'], $effect['amplifier']));
                        } 
                    }
                    break;
                case "REMOVE_POTION":
                    if (isset($effect['potion'])) {
                        
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
                    if (isset($effect['value']) && $target instanceof Player) {
                        $damage = Utils::parseLevel($effect['value']);
                        $target->attack(new EntityDamageEvent($target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
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
                    // pushing players / entties away from victim
                    break;
                case 'CANCEL_EVENT':
                    // cancels an event, e.g canceling fall damage, or "absorbing damage"
                    break;    
                case 'BURN':
                    if (isset($effect['time']) && isset($effect['target'])) {
                        if ($effect['target'] === 'victim') {
                            $target->setOnFire($effect['time']);
                        } elseif ($effect['target'] === 'attacker') {
                            $source->setOnFire($effect['time']);
                        }
                    }
                    break;
                case 'ADD_FOOD':
                    break;    
                case 'REMOVE_FOOD':
                    break;
                case 'TP_DROPS':
                    // Teleport drops to players inventory sorta like auto pickup
                    break;          
                case 'GAURD':
                    // Summon a mob on defense
                    break;      
                case 'INCREASE_DAMAGE':
                    // Increase damage, need to implement a condition check so it can be made to work for e.g with zombies, or players or whichever mob
                    break;    
                case 'PULL_CLOSER':
                    // Pulls closer to victim
                    break;
                case 'LIGHTNING':
                    // Strike lighting at the entity
                    break;
                case 'EXTINGUISH':
                    // Removes fire from player, or entity (removes fire from using LIGHTNING type)
                    break;
                case 'ADD_DURABILITY':
                    // Not sure if its possible to do this anymore?
                    break;             
                case 'BOOST':
                    // Launch entity or player into the air e.g launching victim into air when low hp
                    break;
                case 'TELEPORT_BEHIND':
                    // Teleport behind entity / player
                    break;     
                case 'ADD_HEALTH':
                    // Add health to player
                    break;
                case 'CURE':
                    if (isset($effect['type'])) {
                        $potion = StringToEffectParser::getInstance()->parse($effect['type']);
                        if ($potion === null) {
                            throw new \RuntimeException("Invalid potion effect '" . $effect['type'] . "'");
                        }

                        if ($potion->isBad() && $source instanceof Player) {
                            $source->getEffects()->remove($potion);
                        }
                    }
                    break;
                case 'NEGATE_DAMAGE':
                    // Negate damage
                    break;    
                case 'DISABLE_ACTIVATION':
                    // Prevents an enchantment from activating
                    break;
                case 'DECREASE_DAMAGE':
                    // Decrease damage
                    break;
                case 'ADD_DURABILITY_CURRENT_ITEM':
                    // When item breaks the item that has the enchantment with this effect will be remove the enchantment to restore the item to full durability, might have to check when the durability is low e.g at 1
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
            }

            self::applyPlayerEffects($source, $target, $effects);
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

    public static function checkConditions(array $conditions, Player $player, Entity $victim): bool {
        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? null;
            $conditionMode = $condition['condition_mode'] ?? 'allow';
    
            if ($type === 'VICTIM_HEALTH') {
                $greaterThan = $condition['greater-than'] ?? 0;
                if ($conditionMode === 'stop' && $victim->getHealth() > $greaterThan) {
                    return false; // Stop if victim's health is greater than the specified value
                } elseif ($conditionMode === 'allow' && $victim->getHealth() <= $greaterThan) {
                    return true; // Allow if victim's health is less than or equal to the specified value
                }
            } elseif ($type === 'IS_SNEAKING') {
                $target = $condition['target'] ?? 'self';
                $value = $condition['value'] ?? false;
                if ($target === 'self') {
                    $isSneaking = $player->isSneaking();
                } else {
                    if ($victim instanceof Player) {
                        $isSneaking = $victim->isSneaking();
                    }
                }
                if ($conditionMode === 'allow' && $isSneaking === $value) {
                    return true; // Allow if the target's sneaking status matches the value
                } elseif ($conditionMode === 'stop' && $isSneaking !== $value) {
                    return false; // Stop if the target's sneaking status does not match the value
                }
            } elseif ($type === 'IS_HOLDING') {
                $target = $condition['target'] ?? 'self';
                $value = $condition['value'] ?? null;
                
                if ($target === 'self') {
                    $handItem = $player->getInventory()->getItemInHand();
                } else {
                    $handItem = $victim instanceof Player ? $victim->getInventory()->getItemInHand() : null;
                }

                if ($handItem !== null && (strtolower($value) === 'sword' || strtolower($value) === 'SWORD')) {
                    if ($handItem instanceof Sword) {
                        return true; // Allow if the player is holding a Sword
                    }
                }
                
                if ($handItem !== null && (strtolower($value) === 'axe' || strtolower($value) === 'AXE')) {
                    if ($handItem instanceof Axe) {
                        return true; // Allow if the player is holding an Axe
                    }
                }

                if ($handItem !== null && (strtolower($value) === 'bow' || strtolower($value) === 'BOW')) {
                    if ($handItem instanceof Bow) {
                        return true; // Allow if the player is holding a Pickaxe
                    }
                }

                return false; // Stop if the player is not holding a Sword
            }
            
        }
    
        return true;
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
        
}

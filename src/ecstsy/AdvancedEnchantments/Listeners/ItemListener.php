<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantments;
use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\sound\AnvilFallSound;
use pocketmine\world\sound\XpLevelUpSound;
use pocketmine\utils\TextFormat as C;

class ItemListener implements Listener {

    private array $scrollItems = [];

    private array $validItems = [];

    /** @var array $itemRenamer */
	public array $itemRenamer = [];

	/** @var array $lorerenamer */
	public array $lorerenamer = [];

    /** @var array $loremessages */
	public array $loremessages = [];

    /** @var array $itemmessages */
    public array $itemmessages = [];

    public function __construct(Config $config) {
        $this->loadConfigItems($config);
        $this->initializeValidItems();
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        if ($tag->getTag("advancedscrolls" || 'random_book')) {
            $event->cancel();
        }
    }

    private function loadConfigItems(): void {
        $config = Loader::getInstance()->getConfig();
        $this->scrollItems = [];

        foreach ($config->get("items", []) as $key => $data) {
            if (isset($data['type'])) {
                $itemType = strtolower($data['type']);
                $parsedItem = StringToItemParser::getInstance()->parse($itemType);
                if ($parsedItem !== null) {
                    $this->scrollItems[$key] = $parsedItem->getTypeId();
                } else {
                    Loader::getInstance()->getLogger()->warning("Failed to parse item type for $key: $itemType");
                }
            }
        }
    }

    private function initializeValidItems(): void {
        $config = Utils::getConfiguration("config.yml")->getAll();
        $validItems = [];
    
        $applicableTypes = $config["items"]["settings"]["can-apply-to"];
    
        foreach ($applicableTypes as $type) {
            switch ($type) {
                case 'ALL_SWORD':
                    $validItems = array_merge($validItems, [
                        ItemTypeIds::DIAMOND_SWORD,
                        ItemTypeIds::NETHERITE_SWORD,
                        ItemTypeIds::GOLDEN_SWORD,
                        ItemTypeIds::IRON_SWORD,
                        ItemTypeIds::STONE_SWORD,
                        ItemTypeIds::WOODEN_SWORD
                    ]);
                    break;
                case 'ALL_ARMOR':
                    $validItems = array_merge($validItems, [
                        ItemTypeIds::DIAMOND_HELMET,
                        ItemTypeIds::DIAMOND_CHESTPLATE,
                        ItemTypeIds::DIAMOND_LEGGINGS,
                        ItemTypeIds::DIAMOND_BOOTS,
                        ItemTypeIds::NETHERITE_HELMET,
                        ItemTypeIds::NETHERITE_CHESTPLATE,
                        ItemTypeIds::NETHERITE_LEGGINGS,
                        ItemTypeIds::NETHERITE_BOOTS,
                        ItemTypeIds::GOLDEN_HELMET,
                        ItemTypeIds::GOLDEN_CHESTPLATE,
                        ItemTypeIds::GOLDEN_LEGGINGS,
                        ItemTypeIds::GOLDEN_BOOTS,
                        ItemTypeIds::IRON_HELMET,
                        ItemTypeIds::IRON_CHESTPLATE,
                        ItemTypeIds::IRON_LEGGINGS,
                        ItemTypeIds::IRON_BOOTS,
                        ItemTypeIds::CHAINMAIL_HELMET,
                        ItemTypeIds::CHAINMAIL_CHESTPLATE,
                        ItemTypeIds::CHAINMAIL_LEGGINGS,
                        ItemTypeIds::CHAINMAIL_BOOTS,
                        ItemTypeIds::LEATHER_CAP,
                        ItemTypeIds::LEATHER_TUNIC,
                        ItemTypeIds::LEATHER_PANTS,
                        ItemTypeIds::LEATHER_BOOTS
                    ]);
                    break;
                case 'ALL_PICKAXE':
                    $validItems = array_merge($validItems, [
                        ItemTypeIds::DIAMOND_PICKAXE,
                        ItemTypeIds::NETHERITE_PICKAXE,
                        ItemTypeIds::GOLDEN_PICKAXE,
                        ItemTypeIds::IRON_PICKAXE,
                        ItemTypeIds::STONE_PICKAXE,
                        ItemTypeIds::WOODEN_PICKAXE
                    ]);
                    break;
                case 'ALL_AXE':
                    $validItems = array_merge($validItems, [
                        ItemTypeIds::DIAMOND_AXE,
                        ItemTypeIds::NETHERITE_AXE,
                        ItemTypeIds::GOLDEN_AXE,
                        ItemTypeIds::IRON_AXE,
                        ItemTypeIds::STONE_AXE,
                        ItemTypeIds::WOODEN_AXE
                    ]);
                    break;
                case 'ALL_SPADE':
                    $validItems = array_merge($validItems, [
                        ItemTypeIds::DIAMOND_SHOVEL,
                        ItemTypeIds::NETHERITE_SHOVEL,
                        ItemTypeIds::GOLDEN_SHOVEL,
                        ItemTypeIds::IRON_SHOVEL,
                        ItemTypeIds::STONE_SHOVEL,
                        ItemTypeIds::WOODEN_SHOVEL

                    ]);
                    break;
                case 'ALL_HOE':
                    $validItems = array_merge($validItems, [
                        ItemTypeIds::DIAMOND_HOE,
                        ItemTypeIds::NETHERITE_HOE,
                        ItemTypeIds::GOLDEN_HOE,
                        ItemTypeIds::IRON_HOE,
                        ItemTypeIds::STONE_HOE,
                        ItemTypeIds::WOODEN_HOE
                    ]);
                    break;
                case 'BOOK':
                    $validItems = array_merge($validItems, [ItemTypeIds::ENCHANTED_BOOK]);
                    break;
                case 'BOW':
                    $validItems = array_merge($validItems, [ItemTypeIds::BOW]);
                    break;
                case 'ELYTRA':
                    //$validItems[] = ItemTypeIds::ELYTRA;
                    break;
                case 'TRIDENT':
                    $validItems = array_merge($validItems, [20458]);
                    break;
                default:
                    break;
            }
        }
    
        $this->validItems = $validItems;
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        if (($rcBook = $tag->getTag('random_book')) !== null) {
            $event->cancel();
            $group = $rcBook->getValue();
            $groupId = CEGroups::getGroupId($group);
            $color = CEGroups::translateGroupToColor($groupId);
            $enchantments = CEGroups::getAllForRarity($group);
            
            if (!empty($enchantments)) {
                $randomEnchant = $enchantments[array_rand($enchantments)];

                if ($randomEnchant instanceof CustomEnchantment) {
                    $level = mt_rand(1, $randomEnchant->getMaxLevel());
                    
                    $successRate = mt_rand(1, 100);
                    $destroyRate = mt_rand(1, 100); 
                    $enchantmentBook = Utils::createEnchantmentBook($randomEnchant, $level, $successRate, $destroyRate);
                
                    if ($player->getInventory()->canAddItem($enchantmentBook)) {
                        $player->getInventory()->addItem($enchantmentBook);
                    } else {
                        $player->getWorld()->dropItem($player->getPosition(), $enchantmentBook);
                    }
                    
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                    $enchantmentData = $enchantmentConfig[strtolower($randomEnchant->getName())];

                    Utils::playSound($player, "random.levelup");
                    $messages = Utils::getConfiguration("config.yml")->getNested("enchanter-books.message");
                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            $player->sendMessage(C::colorize(str_replace([
                                "{group-color}", "{group-name}", "{enchant-color}", "{level}"
                            ],
                            [
                                $color, $group, str_replace("{group-color}", $color, $enchantmentData["display"]), Utils::getRomanNumeral($level)
                            ],
                            $message)));
                        }
                    }
                }
            } else {
                $player->sendMessage(C::colorize("&r&4Failed to examine '&fRC Book&r&4'"));
                $player->sendMessage(C::colorize("&r&cThere are no enchantments in the group '&f" . $group . "&r&4'"));
            }
        }

        if (($enchantBook = $tag->getTag("enchant_book")) !== null) {
            $event->cancel();
            $enchant = $enchantBook->getValue();
            $enchantment = isset($enchant) ? StringToEnchantmentParser::getInstance()->parse($enchant) : null;

            if ($enchantment !== null) {
                $level = $tag->getTag("level")->getValue() ?: null;
                if ($level !== null) {
                    if ($enchantment instanceof CustomEnchantment) {
                        $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                        $enchantmentData = $enchantmentConfig[strtolower($enchantment->getName())];
                        $descriptionLines = $enchantmentData['description'] ?? [];
                        $descriptionText = implode("\n", $descriptionLines);  

                        $color = CEGroups::translateGroupToColor($enchantment->getRarity());
                        $player->sendMessage(C::colorize("&r&7 * &eEnchantment &7| " . str_replace("{group-color}", $color, $enchantmentData["display"])));
                        $player->sendMessage(C::colorize("&r&7 * &eApplies to &7| &f" . $enchantmentData['applies-to']));
                        $player->sendMessage(C::colorize("&r&7 * &eMax Level &7| &f" . Utils::getRomanNumeral($enchantment->getMaxLevel())));
                        $player->sendMessage(C::colorize("&r&7 * &eDescription &7| &f" . $descriptionText));
                    }
                }
            }
        }
    }

    public function onDropScroll(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if (count($actions) === 2) {
            foreach ($actions as $i => $action) {
                if ($action instanceof SlotChangeAction) {
                    $otherAction = $actions[($i + 1) % 2];
                    if ($otherAction instanceof SlotChangeAction) {
                        $itemClickedWith = $action->getTargetItem();

                        if ($itemClickedWith->getTypeId() !== VanillaItems::AIR()->getTypeId()) {
                            if ($this->isScrollItem($itemClickedWith)) {
                                $itemClicked = $action->getSourceItem();

                                if ($itemClicked->getTypeId() !== VanillaItems::AIR()->getTypeId()) {
                                    if (in_array($itemClicked->getTypeId(), $this->validItems, true)) {
                                        if ($itemClickedWith->getCount() === 1) {
                                            if ($itemClickedWith->getNamedTag()->getTag("advancedscrolls")) {
                                                $scrollType = $itemClickedWith->getNamedTag()->getString("advancedscrolls");
                                                $event->cancel();

                                                if ($scrollType === "whitescroll") {
                                                    $this->applyWhiteScroll($action, $otherAction, $itemClicked);
                                                } elseif ($scrollType === "blackscroll") {
                                                    $this->handleBlackScroll($action, $otherAction, $itemClicked, $itemClickedWith, $transaction);
                                                } elseif ($scrollType === "transmog") {
                                                    $this->handleTransmogScroll($action, $otherAction, $itemClicked, $transaction);
                                                } elseif ($scrollType === "killcounter") {
                                                    $this->handleKillCounterScroll($action, $otherAction, $itemClicked, $transaction);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function applyWhiteScroll(SlotChangeAction $action, SlotChangeAction $otherAction, Item $itemClicked): void {
        $cfg = Utils::getConfiguration("config.yml");
        
        if ($itemClicked->getNamedTag()->getTag("protected") === null) {   
            $itemClicked->getNamedTag()->setString("protected", "true");
            $lore = $itemClicked->getLore();
            $lore[] = C::colorize($cfg->getNested("items.white-scroll.lore-display"));
            $itemClicked->setLore($lore);

            $action->getInventory()->setItem($action->getSlot(), $itemClicked);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
        }
    }

    private function isScrollItem(Item $item): bool {
        $typeId = $item->getTypeId();
        $isScroll = in_array($typeId, $this->scrollItems, true);
        return $isScroll;
    }

    private function handleBlackScroll(SlotChangeAction $action, SlotChangeAction $otherAction, Item $itemClicked, Item $itemClickedWith, InventoryTransaction $transaction): void {
        $enchantments = $itemClicked->getEnchantments();
        if (!empty($enchantments)) {
            $randomKey = array_rand($enchantments);
            $removedEnchantment = $enchantments[$randomKey];
            $itemClicked->removeEnchantment($removedEnchantment->getType());
    
            $lore = $itemClicked->getLore();
            $enchantmentName = $removedEnchantment->getType()->getName();
            $rarity = $removedEnchantment->getType()->getRarity();
            $loreLine = CEGroups::translateGroupToColor($rarity) . $enchantmentName;
            $loreLineIndex = array_search($loreLine, $lore);
            if ($loreLineIndex !== false) {
                unset($lore[$loreLineIndex]);
                $itemClicked->setLore(array_values($lore));
            }
    
            $action->getInventory()->addItem(Utils::createEnchantmentBook(
                $removedEnchantment->getType(), 
                $removedEnchantment->getLevel(), 
                $itemClickedWith->getNamedTag()->getInt("blackscroll-success"), 
                rand(1, 100)
            ));
        }
    
        $action->getInventory()->setItem($action->getSlot(), $itemClicked);
        $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
        $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new XpLevelUpSound(100));
    }    

    private function handleTransmogScroll($action, $otherAction, $itemClicked, $transaction): void {
        $cfg = Utils::getConfiguration("config.yml");
        $enchantments = $itemClicked->getEnchantments();
        $enchantments = CustomEnchantments::sortEnchantmentsByRarity($enchantments);
        $itemName = $itemClicked->getName();
    
        $countFormat = $cfg->getNested("items.transmogscroll.enchants-count-formatting");
    
        // TODO: prevent transmog from adding the count to the item name multiple times when applying more transmog scrolls to it
        if (preg_match('/\s\[\d+\]$/', $itemName)) {
            $itemName = preg_replace('/\s\[\d+\]$/', '', $itemName);
        }
    
        $enchantmentCount = count($enchantments);
        $itemName .= " " . str_replace("{count}", $enchantmentCount, $countFormat);
    
        $itemClicked->setCustomName(C::colorize($itemName));
    
        foreach ($enchantments as $enchantmentInstance) {
            $itemClicked->removeEnchantment($enchantmentInstance->getType());
        }
    
        foreach ($enchantments as $enchantmentInstance) {
            $itemClicked->addEnchantment($enchantmentInstance);
        }
    
        $action->getInventory()->setItem($action->getSlot(), $itemClicked);
        $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
        $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new XpLevelUpSound(100));
    }    

    private function handleKillCounterScroll($action, $otherAction, $itemClicked, $transaction): void {
        if ($itemClicked->getNamedTag()->getTag("killcounter")) {
            $transaction->getSource()->sendMessage(C::colorize("&r&l&c(!) &r&cThis item already has a player kill counter!"));
            $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new AnvilFallSound());
            return;
        }

        $lore = "§r§ePlayer Kills: §60";
        $itemClicked->setLore([$lore]);
        $itemClicked->getNamedTag()->setString("scrolls", "killcounter");
        $action->getInventory()->setItem($action->getSlot(), $itemClicked);
        $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
        $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new XpLevelUpSound(100));
    }

    public function onDropEnchantBook(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());
        $config = Utils::getConfiguration("config.yml");
        $enchantmentsConfig = Utils::getConfiguration("enchantments.yml");
        $lang = Loader::getInstance()->getLang();
        if (count($actions) === 2) {
            foreach ($actions as $i => $action) {
                if ($action instanceof SlotChangeAction
                    && ($otherAction = $actions[($i + 1) % 2]) instanceof SlotChangeAction
                    && ($itemClickedWith = $action->getTargetItem())->getTypeId() === VanillaItems::ENCHANTED_BOOK()->getTypeId()
                    && ($itemClicked = $action->getSourceItem())->getTypeId() !== VanillaItems::AIR()->getTypeId()
                    && $itemClickedWith->getCount() === 1
                    && $itemClickedWith->getNamedTag()->getTag("enchant_book")
                ) {
                    $event->cancel();
                    $scrollType = $itemClickedWith->getNamedTag()->getString("enchant_book");
                    $enchantment = StringToEnchantmentParser::getInstance()->parse($scrollType);
    
                    if ($enchantment instanceof CustomEnchantment) {
                        $customEnchantmentsCount = 0;
                        foreach ($itemClicked->getEnchantments() as $enchantmentInstance) {
                            if ($enchantmentInstance->getType() instanceof CustomEnchantment) {
                                $customEnchantmentsCount++;
                            }
                        }
    
                        if ($customEnchantmentsCount >= $config->getNested("slots.max")) {
                            $transaction->getSource()->sendMessage(C::colorize(Loader::getInstance()->getLang()->getNested("slots.limit-reached")));
                            return;
                        }
    
                        if ($scrollType === strtolower($enchantment->getName())) {
                            $applicable = CustomEnchantment::getApplicable($enchantment);
                            if ($applicable && CustomEnchantment::matchesApplicable($itemClicked, $applicable)) {
                                $requiredEnchants = $enchantmentsConfig->getNested(strtolower($enchantment->getName()) . ".required-enchants", []);
                                $notApplicableWith = $enchantmentsConfig->getNested(strtolower($enchantment->getName()) . ".not-applyable-with", []);
    
                                // Check for required enchantments
                                foreach ($requiredEnchants as $requiredEnchant) {
                                    if (!$itemClicked->hasEnchantment(StringToEnchantmentParser::getInstance()->parse($requiredEnchant))) {
                                        $transaction->getSource()->sendMessage(C::colorize(str_replace(["{enchant1}", "{enchant2}"], [ucfirst($enchantment->getName()), ucfirst($requiredEnchant)], $lang->getNested("applying.requires-enchant"))));
                                        return;
                                    }
                                }
    
                                // Check for non-applicable enchantments
                                foreach ($notApplicableWith as $notApplicableEnchant) {
                                    if ($itemClicked->hasEnchantment(StringToEnchantmentParser::getInstance()->parse($notApplicableEnchant))) {
                                        $transaction->getSource()->sendMessage(C::colorize(str_replace(["{enchant1}", "{enchant2}"], [ucfirst($enchantment->getName()), ucfirst($notApplicableEnchant)], $lang->getNested("applying.not-applicable-with"))));
                                        return;
                                    }
                                }
    
                                $successRate = $itemClickedWith->getNamedTag()->getInt("successrate");
                                $destroyRate = $itemClickedWith->getNamedTag()->getInt("destroyrate");
                                $level = $itemClickedWith->getNamedTag()->getInt("level");
    
                                if ($successRate !== 0 && $destroyRate !== 0 && $level !== 0) {
                                    $existingEnchantment = $itemClicked->getEnchantment($enchantment);
                                    if (!$existingEnchantment || $existingEnchantment->getLevel() < $level) {
                                        if (mt_rand(1, 100) <= $successRate) {
                                            $itemClicked->addEnchantment(new EnchantmentInstance($enchantment, $level));
                                            $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                                            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                                            $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new XpLevelUpSound(100));
                                            $transaction->getSource()->sendMessage(C::colorize($lang->getNested("applying.applied")));
                                            return;
                                        } else {
                                            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                                            if (mt_rand(1, 100) <= $destroyRate) {
                                                if (Utils::hasTag($itemClicked, "protected", "true")) {
                                                    $transaction->getSource()->sendMessage(C::colorize($lang->getNested("items.white-scroll.item-saved")));
    
                                                    $itemClicked->getNamedTag()->removeTag("protected");
                                                    $lore = $itemClicked->getLore();
                                                    $loreLineIndex = array_search(C::colorize($config->getNested("items.white-scroll.lore-display")), $lore);
                                                    if ($loreLineIndex !== false) {
                                                        unset($lore[$loreLineIndex]);
                                                    }
                                                    $itemClicked->setLore(array_values($lore));
                                                    $transaction->getSource()->getInventory()->setItem($action->getSlot(), $itemClicked);
                                                } else {
                                                    $action->getInventory()->setItem($action->getSlot(), VanillaItems::AIR());
                                                    $transaction->getSource()->sendMessage(C::colorize($lang->getNested("destroy.book-failed")));
                                                    Utils::playSound($transaction->getSource(), "random.anvil_break", 2);
                                                    return;
                                                }
                                            } else {
                                                $transaction->getSource()->sendMessage(C::colorize($lang->getNested("chances.book-failed")));
                                                Utils::playSound($transaction->getSource(), "random.anvil_land", 2);
                                            }
                                        }
                                    } else {
                                        if ($existingEnchantment && $existingEnchantment->getLevel() >= $level) {
                                            return;
                                        } else {
                                            $transaction->getSource()->sendMessage(C::colorize($lang->getNested("applying.max-level")));
                                        }
                                    }
                                }
                            } else {
                                $transaction->getSource()->sendMessage(C::colorize($lang->getNested("applying.wrong-material")));
                            }
                        }
                    }
                }
            }
        }
    }    

    /**             ITEM RENAME          */
    public function onItemRename(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $itemRenamer = $this->itemRenamer[$player->getName()] ?? null;
        if ($itemRenamer === null) {
            return;
        }
    
        $message = $event->getMessage();
        $hand = $player->getInventory()->getItemInHand();
        $event->cancel();
    
        switch ($message) {
            case "cancel":
                $this->handleCancel($player);
                break;
            case "confirm":
                $this->handleConfirm($player, $hand);
                break;
            default:
                $this->handleNaming($player, $message);
                break;
        }
    }

    private function handleCancel(Player $player): void
    {
        $player->sendMessage("§r§c§l** §r§cYou have unqueued your Itemtag for this usage.");
        Utils::playSound($player, "mob.enderdragon.flap", 2);
        $player->getInventory()->addItem(Utils::createScroll("itemrename", 1));
        unset($this->itemRenamer[$player->getName()]);
        unset($this->itemmessages[$player->getName()]);
    }

    private function handleConfirm(Player $player, Item $hand): void
    {
        if (!isset($this->itemmessages[$player->getName()])) {
            return;
        }

        $this->sendMessageAndSound($player, "§r§e§l(!) §r§eYour ITEM has been renamed to: '{$this->itemmessages[$player->getName()]}§e'");
        $hand->setCustomName($this->itemmessages[$player->getName()]);
        $player->getInventory()->setItemInHand($hand);
        unset($this->itemRenamer[$player->getName()]);
        unset($this->itemmessages[$player->getName()]);
    }   

    private function handleNaming(Player $player, string $message): void
    {
        if (strlen($message) > 26) {
            $player->sendMessage("§r§cYour custom name exceeds the 36 character limit.");
            return;
        }

        $formatted = C::colorize($message);
        $this->sendMessageAndSound($player, "§r§e§l(!) §r§eItem Name Preview: $formatted");
        $player->sendMessage("§r§7Type '§r§aconfirm§7' if this looks correct, otherwise type '§ccancel§7' to start over.");
        $this->itemmessages[$player->getName()] = $formatted;
    }

    private function sendMessageAndSound(Player $player, string $message): void
    {
        $player->sendMessage($message);
        $player->getLocation()->getWorld()->addSound($player->getLocation(), new XpLevelUpSound(100));
    }
 
    /**              LORE RENAME                 */
    public function onLoreRename(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $lorerenamer = $this->lorerenamer[$player->getName()] ?? null;
        if ($lorerenamer === null) {
            return;
        }
    
        $message = $event->getMessage();
        $hand = $player->getInventory()->getItemInHand();
        $event->cancel();
    
        switch ($message) {
            case "cancel":
                $this->handleLoreCancel($player);
                break;
            case "confirm":
                $this->handleLoreConfirm($player, $hand);
                break;
            default:
                $this->handleLoreNaming($player, $message);
                break;
        }
    }
    
    private function handleLoreCancel(Player $player): void
    {
        $player->sendMessage("§r§c§l** §r§cYou have unqueued your Lore-Renamer for this usage.");
        Utils::playSound($player, "mob.enderdragon.flap", 2);
        $player->getInventory()->addItem(Utils::createSCroll("lorecrystal", 1));
        unset($this->lorerenamer[$player->getName()]);
        unset($this->loremessages[$player->getName()]);
    }
    
    private function handleLoreConfirm(Player $player, Item $hand): void
    {
        if (!isset($this->loremessages[$player->getName()])) {
            return;
        }
    
        $this->sendMessageAndSound($player, "§r§e§l(!) §r§eYour ITEM's lore has been set to: '{$this->loremessages[$player->getName()]}§e'");
        $lore = $hand->getLore();
        $lore[] = $this->loremessages[$player->getName()];
        $hand->setLore($lore);
        $player->getInventory()->setItemInHand($hand);
        unset($this->lorerenamer[$player->getName()]);
        unset($this->loremessages[$player->getName()]);
    }
    
    private function handleLoreNaming(Player $player, string $message): void
    {
        if (strlen($message) > 18) {
            $player->sendMessage("§r§cYour custom lore exceeds the 18 character limit.");
            return;
        }
    
        $formatted = C::colorize($message);
        $this->sendMessageAndSound($player, "§r§e§l(!) §r§eItem Name Preview: $formatted");
        $player->sendMessage("§r§7Type '§r§aconfirm§7' if this looks correct, otherwise type '§ccancel§7' to start over.");
        $this->loremessages[$player->getName()] = $formatted;
    }
}
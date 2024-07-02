<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
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

    public function __construct(Config $config) {
        $this->loadConfigItems($config);
        $this->initializeValidItems();
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        if ($tag->getTag("advancedscrolls")) {
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
        $this->validItems = [
            VanillaItems::DIAMOND_HELMET()->getTypeId(),
            VanillaItems::DIAMOND_CHESTPLATE()->getTypeId(),
            VanillaItems::DIAMOND_LEGGINGS()->getTypeId(),
            VanillaItems::DIAMOND_BOOTS()->getTypeId(),
            VanillaItems::DIAMOND_SWORD()->getTypeId(),
            VanillaItems::DIAMOND_SHOVEL()->getTypeId(),
            VanillaItems::DIAMOND_PICKAXE()->getTypeId(),
            VanillaItems::DIAMOND_AXE()->getTypeId(),
            VanillaItems::DIAMOND_HOE()->getTypeId(),
        ];

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
        $itemClicked->getNamedTag()->setString("protected", "true");
        $lore = $itemClicked->getLore();
        $lore[] = C::colorize("§r§l§fPROTECTED");
        $itemClicked->setLore($lore);

        $action->getInventory()->setItem($action->getSlot(), $itemClicked);
        $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
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
            $loreLine = EnchantUtils::translateRarityToColor($rarity) . $enchantmentName;
            $loreLineIndex = array_search($loreLine, $lore);
            if ($loreLineIndex !== false) {
                unset($lore[$loreLineIndex]);
                $itemClicked->setLore($lore);
            }

            $action->getInventory()->addItem(Utils::createEnchantmentBook(
                $removedEnchantment->getType(), 
                $removedEnchantment->getLevel(), 
                $itemClickedWith->getNamedTag()->getInt("black_scroll"), 
                rand(1, 100)
            ));
        }

        $action->getInventory()->setItem($action->getSlot(), $itemClicked);
        $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
        $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new XpLevelUpSound(100));
    }

    private function handleTransmogScroll($action, $otherAction, $itemClicked, $transaction): void {
        $enchantments = $itemClicked->getEnchantments();
        $enchantments = CustomEnchantments::sortEnchantmentsByRarity($enchantments);
        $itemName = $itemClicked->getName();

        if (preg_match('/ §r§l§8\[§r§f\d+§l§8\]§r/', $itemName)) {
            $itemName = preg_replace('/ §r§l§8\[§r§f\d+§l§8\]§r/', '', $itemName);
        }

        $enchantmentCount = count($enchantments);
        $itemName .= " §r§l§8[§r§f{$enchantmentCount}§l§8]§r";
        $itemClicked->setCustomName($itemName);

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
        if (count($actions) === 2) {
            foreach ($actions as $i => $action) {
                $items = [
                    ItemTypeIds::DIAMOND_HELMET,
                    ItemTypeIds::DIAMOND_CHESTPLATE,
                    ItemTypeIds::DIAMOND_LEGGINGS,
                    ItemTypeIds::DIAMOND_BOOTS,
                    ItemTypeIds::DIAMOND_SWORD,
                    ItemTypeIds::DIAMOND_SHOVEL,
                    ItemTypeIds::DIAMOND_PICKAXE,
                    ItemTypeIds::DIAMOND_AXE,
                    ItemTypeIds::DIAMOND_HOE
                ];
                if ($action instanceof SlotChangeAction
                    && ($otherAction = $actions[($i + 1) % 2]) instanceof SlotChangeAction
                    && ($itemClickedWith = $action->getTargetItem())->getTypeId() === VanillaItems::ENCHANTED_BOOK()->getTypeId()
                    && ($itemClicked = $action->getSourceItem())->getTypeId() !== VanillaItems::AIR()->getTypeId()
                    && in_array($itemClicked->getTypeId(), $items)
                    && $itemClickedWith->getCount() === 1
                    && $itemClickedWith->getNamedTag()->getTag("enchant_book")
                ) {
                    $scrollType = $itemClickedWith->getNamedTag()->getString("enchant_book");
                    $event->cancel();
                    $enchantment = StringToEnchantmentParser::getInstance()->parse($scrollType);

                        if ($enchantment instanceof CustomEnchantment) {
                            $customEnchantmentsCount = 0;
                            foreach ($itemClicked->getEnchantments() as $enchantmentInstance) {
                                if ($enchantmentInstance->getType() instanceof CustomEnchantment) {
                                    $customEnchantmentsCount++;
                                }
                            }
            
                            if ($customEnchantmentsCount >= 7) {
                                $transaction->getSource()->sendMessage(C::colorize("&r&l&c(!) &r&cThis item already has 7 custom enchantments!"));
                                $event->cancel();
                                return;
                            }

                            if ($scrollType === strtolower($enchantment->getName())) {
                                $applicable = CustomEnchantment::getApplicable($enchantment);
                                if ($applicable) {
                                    if (CustomEnchantment::matchesApplicable($itemClicked, $applicable)) {
                                        if (($successRate = $itemClickedWith->getNamedTag()->getInt("successrate")) !== 0 &&
                                            ($destroyRate = $itemClickedWith->getNamedTag()->getInt("destroyrate")) !== 0 &&
                                            ($level = $itemClickedWith->getNamedTag()->getInt("level")) !== 0) {
                                            $existingEnchantment = $itemClicked->getEnchantment($enchantment);
                                            if (!$existingEnchantment || $existingEnchantment->getLevel() < $level) {
                                                if (mt_rand(1, 100) <= $successRate) {
                                                    $itemClicked->addEnchantment(new EnchantmentInstance($enchantment, $level));
                                                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);

                                                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                                                    $transaction->getSource()->getWorld()->addSound($transaction->getSource()->getLocation(), new XpLevelUpSound(100));
                                                    return;
                                                    
                                                } else {
                                                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                                            
                                                    if (mt_rand(1, 100) <= $destroyRate) {
                                                        if (Utils::hasTag($itemClicked, "protected", "true")) {
                                                            $transaction->getSource()->sendToastNotification(C::colorize("&r&l&e(!) &r&eYour Item was protected by the Whitescroll"), C::colorize("&r&7The Whitescroll protected your item from being destroyed by the enchantment book"));
                                                        
                                                            $itemClicked->getNamedTag()->removeTag("protected");
                                                            $lore = $itemClicked->getLore();
                                                            if (isset($lore[array_search("§r§l§fPROTECTED", $lore)])) {
                                                            unset($lore[array_search("§r§l§fPROTECTED", $lore)]);
                                                        }
                                                        $itemClicked->setLore($lore);
                                                        $transaction->getSource()->getInventory()->setItem($action->getSlot(), $itemClicked);
                                                    } else {

                                                        $action->getInventory()->setItem($action->getSlot(), VanillaItems::AIR());
                                                        return;
                                                    }
                                                }
                                            }
                                        } else {
                                            $transaction->getSource()->sendToastNotification(C::colorize("&r&l&c(!) &r&cThis item already has " . ucfirst($enchantment->getName()) . "!"), C::colorize("&r&7The enchantment already exists on the item at the same level or higher."));
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
        unset($this->message[$player->getName()]);
    }

    private function handleConfirm(Player $player, Item $hand): void
    {
        if (!isset($this->message[$player->getName()])) {
            return;
        }

        $this->sendMessageAndSound($player, "§r§e§l(!) §r§eYour ITEM has been renamed to: '{$this->message[$player->getName()]}§e'");
        $hand->setCustomName($this->message[$player->getName()]);
        $player->getInventory()->setItemInHand($hand);
        unset($this->itemRenamer[$player->getName()]);
        unset($this->message[$player->getName()]);
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
        $this->message[$player->getName()] = $formatted;
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
        unset($this->messages[$player->getName()]);
    }
    
    private function handleLoreConfirm(Player $player, Item $hand): void
    {
        if (!isset($this->messages[$player->getName()])) {
            return;
        }
    
        $this->sendMessageAndSound($player, "§r§e§l(!) §r§eYour ITEM's lore has been set to: '{$this->messages[$player->getName()]}§e'");
        $lore = $hand->getLore();
        $lore[] = $this->messages[$player->getName()];
        $hand->setLore($lore);
        $player->getInventory()->setItemInHand($hand);
        unset($this->lorerenamer[$player->getName()]);
        unset($this->messages[$player->getName()]);
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
        $this->messages[$player->getName()] = $formatted;
    }
}

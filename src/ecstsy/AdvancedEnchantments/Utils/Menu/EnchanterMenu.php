<?php

namespace ecstsy\AdvancedEnchantments\Utils\Menu;

use ecstsy\AdvancedEnchantments\Utils\CustomSizedInvMenu;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use ecstsy\essentialsx\Loader;
use jojoe77777\FormAPI\CustomForm;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;


class EnchanterMenu {

    public static function sendEnchanterMenu(Player $player): InvMenu {
        $config = Utils::getConfiguration("menus/enchanter.yml");
        $inventorySize = $config->getNested("inventory.size");
        $menu = CustomSizedInvMenu::create($inventorySize);
        $inventory = $menu->getInventory();
        $playerXP = $player->getXpManager()->getCurrentTotalXp();
        $itemsConfig = $config->getNested("inventory.items");
        
        foreach ($itemsConfig as $slot => $itemConfig) {
            if (strpos($slot, '-') !== false) {
                list($start, $end) = explode('-', $slot);
                $start = (int)$start;
                $end = (int)$end;
    
                if ($start >= 0 && $end < $inventorySize) {
                    for ($i = $start; $i <= $end; $i++) {
                        $pane = StringToItemParser::getInstance()->parse($itemConfig['item']['type']);
                        $pane->setCustomName($itemConfig['name'] ?? ' ');
                        $inventory->setItem($i, $pane);
                    }
                }
            }
        }
        
        foreach ($itemsConfig as $slot => $itemConfig) {
            if (strpos($slot, '-') !== false) {
                
                continue;
            }
    
            $slot = (int)$slot;
            if ($slot < 0 || $slot >= $inventorySize) {
                continue;  
            }

            $item = StringToItemParser::getInstance()->parse($itemConfig['item']['type']);
            if (isset($itemConfig['item']['force-glow']) && $itemConfig['item']['force-glow']) {

            }
    
            if (isset($itemConfig['name'])) {
                $item->setCustomName(C::colorize($itemConfig['name']));
            }
            
            $lore = [];
            if (isset($itemConfig['lore'])) {
                foreach ($itemConfig['lore'] as $line) {
                    $price = $itemConfig['price'] ?? 0;
                    $left = max(0, $price - $playerXP);  
                    $lore[] = C::colorize(str_replace(['{price}', '{left}'], [number_format($price), number_format($left)], $line));
                }
                $item->setLore($lore);
            }
            
            $inventory->setItem((int)$slot, $item);
        }
        
        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $itemClicked = $transaction->getItemClicked();
            $config = Utils::getConfiguration("menus/enchanter.yml")->getAll();

            if (isset($config['inventory']['items'][$slot]) && isset($config['inventory']['items'][$slot]['enchant-group']) && isset($config['inventory']['items'][$slot]['eco']) && isset($config['inventory']['items'][$slot]['price'])) {
                $slotConfig = $config['inventory']['items'][$slot];
                $enchantGroup = $slotConfig['enchant-group'];
                $eco = $slotConfig['eco'];
                $price = $slotConfig['price'];
                $sound = $slotConfig['sound'];
        
                $player->removeCurrentWindow();
                $transaction->then(function (Player $player) use ($enchantGroup, $eco, $price, $sound) {
                    $player->sendForm(self::sendConfirmationForm($player, $enchantGroup, $eco, $price, $sound));
                });
            }
        }));
          
        
        $menu->setName(C::colorize($config->getNested("inventory.name")));
        
        return $menu;
    }
    
public static function sendConfirmationForm(Player $player, string $enchantGroup, string $eco, int $price, string $sound): CustomForm
{
    $config = Utils::getConfiguration("menus/enchanter.yml");
    $form = new CustomForm(function(Player $player, $data) use($enchantGroup, $eco, $price, $sound, $config) {
        if ($data === null) {
            return true;
        }

        $amount = (int) $data[1];
        $amount = max(1, min(2304, $amount));

        $totalPrice = $price * $amount;

        if (self::canAfford($player, $eco, $totalPrice)) {
            $book = Utils::createRCBook($enchantGroup, $amount);
            $inventory = $player->getInventory();

            
            self::deductResources($player, $eco, $totalPrice);
            if ($inventory->canAddItem($book)) {
                $inventory->addItem($book);

                Utils::playSound($player, $sound);

                $messages = $config->getNested("messages.successfull-purchase");
                foreach ($messages as $message) {
                $player->sendMessage(C::colorize(str_replace(
                        ["{amount}", "{enchant-group}", "{price}", "{payment-type}"],
                        [$amount, $enchantGroup, number_format($totalPrice), $eco],
                        $message
                    )));
                }
            } else {
                $messages = $config->getNested("messages.inventory-is-full");
                foreach ($messages as $message) {
                    $player->sendMessage(C::colorize($message));
                }
            }
        } else {
            $messages = $config->getNested("messages.cannot-afford");
            foreach ($messages as $message) {
                $player->sendMessage(C::colorize(str_replace("{exp}", self::getBalanceFormatted($player, $eco), $message)));
            }
        }
    });

    $form->setTitle(C::colorize($config->getNested("confirmation-form.name")));
    $confirmationText = $config->getNested("confirmation-form.accept.text");

    $buttonText = str_replace(
        ['{price}', '{paymentType}'],
        [number_format($price), $eco],
        $confirmationText
    );

    $form->addLabel(C::colorize($buttonText));
    $form->addInput(C::colorize("&r&aEnter Amount:"), "1"); // Default value 1

    return $form;
}

    
    public static function canAfford(Player $player, string $eco, int $price): bool
    {
        switch ($eco) {
            case "exp":
                return $player->getXpManager()->getCurrentTotalXp() >= $price;
            case "xplevel":
                return $player->getXpManager()->getXpLevel() >= $price;
            case "BedrockEconomy":
                // Implement the check for BedrockEconomy
                break;
            case "essentialsx":
                return Loader::getPlayerManager()->getSession($player)->getBalance() >= $price;
                break;
            default:
                return false;
        }
    }

    public static function getBalanceFormatted(Player $player, string $eco): string
    {
        switch ($eco) {
            case "exp":
                return number_format($player->getXpManager()->getCurrentTotalXp());
            case "xplevel":
                return number_format($player->getXpManager()->getXpLevel());
            case "BedrockEconomy":
                // Implement the check for BedrockEconomy
                break;
            case "essentialsx":
                return number_format(Loader::getPlayerManager()->getSession($player)->getBalance());
                break;
            default:
                return 0;
        }
    }
    
    public static function deductResources(Player $player, string $eco, int $price): void
    {
        switch ($eco) {
            case "exp":
                $player->getXpManager()->subtractXp($price);
                break;
            case "xplevels":
                $player->getXpManager()->subtractXpLevels($price);
                break;
            case "BedrockEconomy":
                // Implement the deduction for BedrockEconomy
                break;
            case "essentialsx":
                Loader::getPlayerManager()->getSession($player)->subtractBalance($price);
                break;
        }
    }
    
}
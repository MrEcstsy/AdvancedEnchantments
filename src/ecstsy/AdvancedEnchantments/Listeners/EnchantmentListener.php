<?php

namespace ecstsy\AdvancedEnchantments\Listeners;

use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantment;
use ecstsy\AdvancedEnchantments\Utils\Utils;
use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

class EnchantmentListener implements Listener {

    private array $cooldowns = [];

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $block = $event->getBlock();

        if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
            foreach ($item->getEnchantments() as $enchantmentInstance) {
                $enchantment = $enchantmentInstance->getType();

                if ($enchantment instanceof CustomEnchantment) {
                    $enchantmentName = $enchantment->getName();
                    $enchantmentConfig = Utils::getConfiguration("enchantments.yml")->getAll();
                    if ($enchantmentConfig !== null && isset($enchantmentConfig[$enchantmentName])) {
                        $enchantmentData = $enchantmentConfig[$enchantmentName];

                        if ($enchantmentData['type'] === 'MINING') {
                            $playerName = $player->getName();
                            $level = $enchantmentInstance->getLevel();

                            if ($this->hasCooldown($playerName, $enchantmentName)) {
                                continue;
                            }

                            if (isset($enchantmentData['levels'][$level])) {
                                $chance = $enchantmentData['levels'][$level]['chance'];
                                if (mt_rand(1, 100) <= $chance) {
                                    $this->applyEffects($player, $block, $enchantmentData['levels'][$level]['effects']);

                                    $cooldown = $enchantmentData['levels'][$level]['cooldown'];
                                    $this->setCooldown($playerName, $enchantmentName, $cooldown);
                                    
                                    $color = CEGroups::translateGroupToColor($enchantment->getRarity());
                                    if (isset($enchantmentData['settings']['showActionBar']) && $enchantmentData['settings']['showActionBar']) {
                                        $actionBarMessage = C::WHITE . "Used " . $color . ucfirst($enchantmentName) . " " . Utils::getRomanNumeral($level);
                                        $player->sendActionBarMessage($actionBarMessage);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function setCooldown(string $playerName, string $enchantmentName, int $time) {
        $this->cooldowns[$playerName][$enchantmentName] = time() + $time;
    }

    public function hasCooldown(string $playerName, string $enchantmentName): bool {
        if (isset($this->cooldowns[$playerName][$enchantmentName])) {
            if (time() < $this->cooldowns[$playerName][$enchantmentName]) {
                return true;
            } else {
                unset($this->cooldowns[$playerName][$enchantmentName]);
            }
        }
        return false;
    }

    public function getCooldownTime(string $playerName, string $enchantmentName): int {
        if (isset($this->cooldowns[$playerName][$enchantmentName])) {
            return max(0, $this->cooldowns[$playerName][$enchantmentName] - time());
        }
        return 0;
    }

    /**
     * Applies the enchantment effects
     *
     * @param Player $player
     * @param Block $block
     * @param array $effects
     */
    private function applyEffects(Player $player, Block $block, array $effects): void {
        foreach ($effects as $effect) {
            if (!isset($effect['type'])) {
                continue; 
            }

            switch ($effect['type']) {
                case 'PLAY_SOUND':
                    if (isset($effect['sound'])) {
                        Utils::playSound($player, $effect['sound']);
                    }
                    break;
                case "ADD_PARTICLE":
                    if (isset($effect['particle'])) {
                        Utils::spawnParticle($block->getPosition(), $effect['particle']);
                    }
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
                case 'ADD_POTION':
                    if (isset($effect['potion'])) {
                        // for adding potion effects by chance when damaged or damaging
                    }
                    break;    
                case "REMOVE_POTION":
                    if (isset($effect['potion'])) {
                        
                    }
                    break;
                case "PLANT_SEEDS":
                    if (isset($effect['seed'])) {
                        
                    }    
                    break;
                case "DOUBLE_DAMAGE":
                            
                    break;
                case "WAIT":
                    //  TODO: Implememt wait method in ticks
                    //  then run the next effect
                    // e.g: - type: "WAIT"
                    //        duration: 20
                    //      - type: "DO_HARM"
                    //        damage: {1-3}
                    // This would wait 20 ticks before running the next effect
                    
                    break;
                case "DO_HARM":

                    break;    
                case "MESSAGE":
                    if (isset($effect['text'])) {
                        $player->sendMessage(C::colorize($effect['message'])); // TODO add target player e.g: victim, attacker, self
                    }
                    break;
                case "EFFECT_STATIC":
                    if (isset($effect['effect'])) {
                        // for adding potion effects when worn / held
                    }    
                    break;
                case "SMELT":
                    // Smelt mined blocks
                    break;    
                case "EXP":
                    // for increasing xp drop on mined blocks
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
                    // set attackers on fire
                    break;
            }
        }
    }
}

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
                case 'ADD_FOOD':
                    break;    
                case 'REMOVE_FOOD':
                    break;
                case 'VEINMINER':
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
                case 'BREAK_TREE':
                    // Breaks an entire tree
                    break;
                case 'ADD_HEALTH':
                    // Add health to player
                    break;
                case 'CURE':
                    // Remove a bad potion effect
                    break;
                case 'NEGATE_DAMAGE':
                    // Negate damage
                    break;
                case 'BREAK_BLOCK':
                    // Breaks blocks in radius
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
            }
            

        }
        return true; 
    }
    
}

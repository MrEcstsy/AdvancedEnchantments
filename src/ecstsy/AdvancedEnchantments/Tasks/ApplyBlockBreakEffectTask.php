<?php

namespace ecstsy\AdvancedEnchantments\Tasks;

use ecstsy\AdvancedEnchantments\Loader;
use pocketmine\player\Player;
use pocketmine\block\Block;
use pocketmine\scheduler\AsyncTask;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class ApplyBlockBreakEffectTask extends AsyncTask {

    private string $playerName;
    private string $worldName;
    private string $blockPos;
    private int $radius;

    public function __construct(string $playerName, string $worldName, array $blockPos, int $radius) {
        $this->playerName = $playerName;
        $this->worldName = $worldName;
        $this->blockPos = serialize($blockPos);
        $this->radius = $radius;
    }

    public function onRun(): void {
        var_dump("onRun started");
        $blockPos = unserialize($this->blockPos);

        if ($this->radius % 2 == 0) {
            $this->setResult(['error' => 'Radius must be an odd number']);
            var_dump("Radius error set");
            return;
        }

        $halfRadius = ($this->radius - 1) / 2;
        $blocksToBreak = [];

        for ($y = -$halfRadius; $y <= $halfRadius; $y++) {
            for ($x = -$halfRadius; $x <= $halfRadius; $x++) {
                for ($z = -$halfRadius; $z <= $halfRadius; $z++) {
                    if ($x == 0 && $y == 0 && $z == 0) {
                        continue; 
                    }
                    $blocksToBreak[] = [$blockPos[0] + $x, $blockPos[1] + $y, $blockPos[2] + $z];
                }
            }
        }

        var_dump("Blocks to break calculated: " . count($blocksToBreak));
        $this->setResult(['blocks' => $blocksToBreak]);
    }

    public function onCompletion(): void {
        var_dump("onCompletion started");
        $server = Loader::getInstance()->getServer();
        $player = $server->getPlayerExact($this->playerName);
        if ($player === null) {
            var_dump("Player not found");
            return;
        }

        $world = $server->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            var_dump("World not found");
            return;
        }

        $result = $this->getResult();
        if (isset($result['error'])) {
            $player->sendMessage(TextFormat::colorize($result['error']));
            var_dump("Error sent to player: " . $result['error']);
            return;
        }

        $itemInHand = $player->getInventory()->getItemInHand();
        foreach ($result['blocks'] as [$x, $y, $z]) {
            $block = $world->getBlockAt($x, $y, $z);
            if ($block->getTypeId() !== \pocketmine\block\BlockTypeIds::AIR) {
                $world->useBreakOn($block->getPosition(), $itemInHand, $player, true);
                var_dump("Block broken at: ($x, $y, $z)");
            }
        }
        var_dump("onCompletion ended");
    }
}

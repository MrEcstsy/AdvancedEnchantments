<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

class TargetHandler {

    public static function handleTarget(string $targetType, array $contextData, Entity $entity): array {
        switch ($targetType) {
            case TargetType::VICTIM:
                return [$params['victim'] ?? null];

            case TargetType::ATTACKER:
                return [$params['attacker'] ?? null];
                
                case TargetType::SELF:
                    return [$entity];
            default:
                throw new \InvalidArgumentException("Unknown target type: $targetType");
        }
    }

    private static function getBlockAtLocation(Vector3 $location): Block {
        // Implementation here
    }

    private static function getNearestPlayers(World $world, float $radius): array {
        // Implementation here
    }

    private static function getEntityAtEyeHeight(Entity $entity): ?Entity {
        // Implementation here
    }

    private static function getTrenchArea(Vector3 $location, int $size): array {
        // Implementation here
    }

    private static function getBlockInDistance(Vector3 $location, float $distance): ?Block {
        // Implementation here
    }

    private static function getVeinmineBlocks(Vector3 $location, float $distance, array $whitelist): array {
        // Implementation here
    }

    public static function getAOEEntities(World $world, int $radius, string $targetType, Player $attacker): array {
        $entities = [];
        foreach ($world->getNearbyEntities($attacker->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $entity) {
            if ($entity === $attacker) {
                continue;
            }
            switch (strtoupper($targetType)) {
                case 'ALL':
                    $entities[] = $entity;
                    break;
                case 'MOBS':
                    if ($entity instanceof Living && !$entity instanceof Player) {
                        $entities[] = $entity;
                    }
                    break;
                case 'DAMAGEABLE':
                    if ($entity instanceof Living) {
                        $entities[] = $entity;
                    }
                    break;
                case 'UNDAMAGEABLE':
                    if (AllyChecks::isAlly($attacker, $entity)) {
                        $entities[] = $entity;
                    }
                    break;
            }
        }
    
        return $entities;
    }

    private static function getEntitiesInSight(Entity $entity, float $maxDistance, int $limit, float $angle): array {
        // Implementation here
    }
}

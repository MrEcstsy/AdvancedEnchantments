<?php

namespace ecstsy\AdvancedEnchantments\Enchantments;

class CustomEnchantmentIds {

    private static $nextId = 1000; // Starting ID for custom enchantments

    public const FAKE_ENCH_ID = -1;
    
    public static function getNextId(): int {
        return self::$nextId++;
    }

}

<?php

declare(strict_types=1);

namespace ecstsy\AdvancedEnchantments\Utils\CustomInventory;

use ecstsy\AdvancedEnchantments\Loader;
use ecstsy\AdvancedEnchantments\libs\muqsit\invmenu\InvMenu;
use ecstsy\AdvancedEnchantments\libs\muqsit\invmenu\InvMenuHandler;

final class CustomSizedInvMenu {

    public static function create(int $size) : InvMenu{
		static $ids_by_size = [];
		if(!isset($ids_by_size[$size])){
			$id = Loader::TYPE_DYNAMIC_PREFIX . $size;
			InvMenuHandler::getTypeRegistry()->register($id, CustomSizedInvMenuType::ofSize($size));
			$ids_by_size[$size] = $id;
		}
		return InvMenu::create($ids_by_size[$size]);
	}
}
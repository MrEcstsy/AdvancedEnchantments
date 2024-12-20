<?php

namespace ecstsy\AdvancedEnchantments;

use ecstsy\AdvancedEnchantments\libs\DaPigGuy\libPiggyEconomy\libPiggyEconomy;
use ecstsy\AdvancedEnchantments\Commands\AECommand;
use ecstsy\AdvancedEnchantments\Commands\ASetsCommand;
use ecstsy\AdvancedEnchantments\Commands\EnchanterCommand;
use ecstsy\AdvancedEnchantments\Enchantments\CEGroups;
use ecstsy\AdvancedEnchantments\Enchantments\CustomEnchantments;
use ecstsy\AdvancedEnchantments\libs\ecstsy\advancedAbilities\AdvancedAbilityHandler;
use ecstsy\AdvancedEnchantments\libs\JackMD\ConfigUpdater\ConfigUpdater;
use ecstsy\AdvancedEnchantments\Listeners\EnchantmentListener;
use ecstsy\AdvancedEnchantments\Listeners\ItemListener;
use ecstsy\AdvancedEnchantments\Utils\CustomInventory\CustomSizedInvMenuType;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use ecstsy\AdvancedEnchantments\Utils\LanguageManager;
use ecstsy\AdvancedEnchantments\libs\libCustomPack\libCustomPack;
use ecstsy\AdvancedEnchantments\libs\muqsit\invmenu\InvMenuHandler;
use ecstsy\AdvancedEnchantments\Listeners\CustomArmorListener;
use ecstsy\AdvancedEnchantments\Utils\AdvancedEffect;
use ecstsy\AdvancedEnchantments\Utils\AdvancedTriggers;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\resourcepacks\ZippedResourcePack;
use Symfony\Component\Filesystem\Path;

class Loader extends PluginBase {

    use SingletonTrait;

    private LanguageManager $languageManager;

    private static ?ZippedResourcePack $pack;

    public $economyProvider;

    public const TYPE_DYNAMIC_PREFIX = "muqsit:customsizedinvmenu_";

    public const CFG_VERSION = 1;

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
        $resources = ["enchantments.yml", "groups.yml"];
	    
        foreach ($resources as $resource) {
            $this->saveResource($resource);
        }
	    
        ConfigUpdater::checkUpdate($this, $this->getConfig(), "version", self::CFG_VERSION);

        $subDirectories = ["locale", "armorSets", "menus"];

        foreach ($subDirectories as $directory) {
            $this->saveAllFilesInDirectory($directory);
        }

        $config = $this->getConfig();
        $language = $config->get("language", "en-us");
	    
        $this->languageManager = new LanguageManager($this, $language);

        $this->getLogger()->info("AdvancedEnchantments enabled with language: " . $language);

        $this->getServer()->getCommandMap()->registerAll("AdvancedEnchantments", [
            new AECommand($this, "advancedenchantments", "View the advanced enchantments commands", ["ae", "advancedenchantment"]),
            new EnchanterCommand($this, "enchanter", "Open Enchanter", $config->getNested("commands.enchanter.aliases")),
            new ASetsCommand($this, "asets", "View the armor sets commands")
        ]);
	    
        $listeners = [new ItemListener($this->getConfig()), new EnchantmentListener(), new CustomArmorListener()];

        foreach ($listeners as $listener) {
            $this->getServer()->getPluginManager()->registerEvents($listener, $this);
        }

        CEGroups::init();
        AdvancedTriggers::init();
        AdvancedEffect::init();
        
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

	if (!AdvancedAbilityHandler::isRegistered()) {
            AdvancedAbilityHandler::register($this);
        }	

        CustomEnchantments::getAll();

        libCustomPack::registerResourcePack(self::$pack = libCustomPack::generatePackFromResources($this));
        $this->getLogger()->info("Resource pack loaded");

        $packet = StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
		$tag = $packet->identifiers->getRoot();
		assert($tag instanceof CompoundTag);
		$id_list = $tag->getListTag("idlist");
		assert($id_list !== null);
		$id_list->push(CompoundTag::create()
			->setString("bid", "")
			->setByte("hasspawnegg", 0)
			->setString("id", CustomSizedInvMenuType::ACTOR_NETWORK_ID)
			->setByte("summonable", 0)
		);

        libPiggyEconomy::init();
	    
        if ($this->getConfig()->getNested("economy.enabled") === "true") {
            $this->economyProvider = libPiggyEconomy::getProvider($this->getConfig()->get("economy"));
        } else {
            $this->getLogger()->warning("Economy has not been enabled.");
        }
    }

    public function onDisable(): void {
        libCustomPack::unregisterResourcePack(self::$pack);
        $this->getLogger()->info("Resource pack unloaded");
        
        unlink(Path::join($this->getDataFolder(), self::$pack->getPackName() . ".mcpack"));
    }

    private function saveAllFilesInDirectory(string $directory): void {
        $resourcePath = $this->getFile() . "resources/$directory/";
        if (!is_dir($resourcePath)) {
            $this->getLogger()->warning("Directory $directory does not exist.");
            return;
        }

        $files = scandir($resourcePath);
        if ($files === false) {
            $this->getLogger()->warning("Failed to read directory $directory.");
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $this->saveResource("$directory/$file");
        }
    }

    public function getLang(): LanguageManager {
        return $this->languageManager;
    }
}

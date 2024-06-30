<?php

namespace ecstsy\AdvancedEnchantments;

use ecstsy\AdvancedEnchantments\Commands\AECommand;
use ecstsy\AdvancedEnchantments\Listeners\ItemListener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use ecstsy\AdvancedEnchantments\utils\LanguageManager;

class Loader extends PluginBase {

    use SingletonTrait;

    private LanguageManager $languageManager;

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
        $resources = ["config.yml", "enchantments.yml"];
        foreach ($resources as $resource) {
            $this->saveResource($resource);
        }

        $subDirectories = ["locale", "armorSets"];

        foreach ($subDirectories as $directory) {
            $this->saveAllFilesInDirectory($directory);
        }

        $config = $this->getConfig();

        $language = $config->get("language", "en-us");
        $this->languageManager = new LanguageManager($this, $language);

        $this->getLogger()->info("AdvancedEnchantments enabled with language: " . $language);

        $this->getServer()->getCommandMap()->registerAll("AdvancedEnchantments", [
            new AECommand($this, "advancedenchantments", "View the advanced enchantments commands", ["ae", "advancedenchantment"]),

        ]);

        $listeners = [new ItemListener($this)];

        foreach ($listeners as $listener) {
            $this->getServer()->getPluginManager()->registerEvents($listener, $this);
        }
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

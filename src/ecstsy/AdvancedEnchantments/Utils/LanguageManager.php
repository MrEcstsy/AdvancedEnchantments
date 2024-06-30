<?php

namespace ecstsy\AdvancedEnchantments\Utils;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class LanguageManager {
    
    private Config $config;
    private string $filePath;

    public function __construct(PluginBase $plugin, string $languageKey) {
        $pluginDataDir = $plugin->getDataFolder();
        
        $localeDir = $pluginDataDir . '/locale/';
        
        $files = scandir($localeDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === $languageKey && pathinfo($file, PATHINFO_EXTENSION) === 'yml') {
                $this->filePath = $localeDir . $file;
                break;
            }
        }
        
        if (!isset($this->filePath) || !file_exists($this->filePath)) {
            throw new \RuntimeException("Language file not found for language key '$languageKey' in directory: " . $localeDir);
        }
        
        $this->config = new Config($this->filePath, Config::YAML);
    }

    public function get(string $key): string {
        return $this->config->get($key, "Translation not found: " . $key);
    }

    public function getNested(string $key): string {
        return $this->config->getNested($key, "Translation not found: " . $key);
    }
    
    public function reload(): void {
        $this->config->reload();
    }
    
    public function getFilepath(): string {
        return $this->filePath;
    }
}


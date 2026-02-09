#!/usr/bin/env php
<?php

/**
 * Validates that translation YAML files in the given directory are parseable.
 * Usage: php scripts/validate-translations-yaml.php [directory]
 * Default directory: src/Resources/translations (relative to bundle root).
 */

$root = dirname(__DIR__);
$dir = isset($argv[1])
    ? (str_starts_with($argv[1], '/') ? $argv[1] : $root . '/' . $argv[1])
    : $root . '/src/Resources/translations';
if (!is_dir($dir)) {
    echo "Directory not found: {$dir}\n";
    exit(1);
}

require_once $root . '/vendor/autoload.php';

$files = array_merge(glob($dir . '/*.yaml') ?: [], glob($dir . '/*.yml') ?: []);
$failed = 0;

foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content === false) {
        echo "ERROR: Cannot read {$file}\n";
        $failed++;
        continue;
    }
    try {
        \Symfony\Component\Yaml\Yaml::parse($content);
    } catch (Throwable $e) {
        echo "ERROR: Invalid YAML in {$file}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}

echo "OK: " . count($files) . " translation file(s) validated.\n";
exit(0);

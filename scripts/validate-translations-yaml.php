#!/usr/bin/env php
<?php

/**
 * Validates that translation YAML files in the given directory are parseable
 * and that all files have the same keys as the reference (English) file.
 *
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
$parsed = [];

foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content === false) {
        echo "ERROR: Cannot read {$file}\n";
        $failed++;
        continue;
    }
    try {
        $parsed[$file] = \Symfony\Component\Yaml\Yaml::parse($content);
    } catch (Throwable $e) {
        echo "ERROR: Invalid YAML in {$file}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}

/** Flatten YAML array to dot-separated leaf keys (e.g. signature_coordinates_type.pdf_url.label). */
function flattenKeys(array $arr, string $prefix = ''): array {
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? $k : $prefix . '.' . $k;
        if (is_array($v) && !array_is_list($v)) {
            $out = array_merge($out, flattenKeys($v, $key));
        } else {
            $out[] = $key;
        }
    }
    return $out;
}

$refFile = $dir . '/nowo_pdf_signable.en.yaml';
if (!isset($parsed[$refFile])) {
    echo "WARN: Reference file (en) not found, skipping key comparison.\n";
} else {
    $refKeys = flattenKeys($parsed[$refFile]);
    sort($refKeys);
    foreach ($parsed as $path => $data) {
        if ($path === $refFile) {
            continue;
        }
        $langKeys = flattenKeys($data);
        sort($langKeys);
        $missing = array_diff($refKeys, $langKeys);
        $extra = array_diff($langKeys, $refKeys);
        if ($missing !== []) {
            echo "ERROR: " . basename($path) . " missing keys: " . implode(', ', $missing) . "\n";
            $failed++;
        }
        if ($extra !== []) {
            echo "WARN: " . basename($path) . " extra keys (not in en): " . implode(', ', $extra) . "\n";
        }
    }
}

if ($failed > 0) {
    exit(1);
}

echo "OK: " . count($files) . " translation file(s) validated.\n";
exit(0);

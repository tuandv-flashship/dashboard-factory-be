#!/usr/bin/env php
<?php

/**
 * Refactor Request Classes for Apiato 13.x
 * 
 * This script removes the deprecated `$access` property and empty `$urlParameters`
 * from Request classes according to Apiato 13.x best practices.
 * 
 * Usage: php scripts/refactor-requests-apiato13.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv);
$basePath = dirname(__DIR__);

// Find all Request files
$requestsPath = $basePath . '/app/Containers/AppSection';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($requestsPath)
);

$modifiedCount = 0;
$skippedCount = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Request.php')) {
        continue;
    }

    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Pattern 1: Remove $access property block (multiline)
    $patterns = [
        // Match protected array $access = [...]; with various formats
        '/\s*protected\s+array\s+\$access\s*=\s*\[\s*[^]]*\];\s*\n/s',
        
        // Match ALL $urlParameters = [...]; (removed in Apiato 13.x)
        '/\s*protected\s+array\s+\$urlParameters\s*=\s*\[\s*[^]]*\];\s*\n/s',
    ];

    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, "\n", $content);
    }

    // Ensure blank line between $decode and rules() method
    $content = preg_replace('/(\];\s*)(public function rules)/', "$1\n    $2", $content);

    // Clean up multiple blank lines
    $content = preg_replace('/\n{3,}/', "\n\n", $content);

    // Only write if content changed
    if ($content !== $originalContent) {
        $relativePath = str_replace($basePath . '/', '', $filePath);
        
        if ($dryRun) {
            echo "Would modify: $relativePath\n";
        } else {
            file_put_contents($filePath, $content);
            echo "Modified: $relativePath\n";
        }
        $modifiedCount++;
    } else {
        $skippedCount++;
    }
}

echo "\n";
echo "=================================\n";
echo "Refactor Complete!\n";
echo "=================================\n";
echo "Modified: $modifiedCount files\n";
echo "Skipped: $skippedCount files (no changes needed)\n";

if ($dryRun) {
    echo "\n(Dry run - no files were actually modified)\n";
    echo "Run without --dry-run to apply changes.\n";
}

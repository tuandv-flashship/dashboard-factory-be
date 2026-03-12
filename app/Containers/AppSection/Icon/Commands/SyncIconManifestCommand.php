<?php

namespace App\Containers\AppSection\Icon\Commands;

use App\Containers\AppSection\Icon\Supports\IconManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'icon:sync-manifest', description: 'Sync icon manifest from Tabler Icons or a local SVG directory')]
final class SyncIconManifestCommand extends Command
{
    protected $signature = 'icon:sync-manifest
        {--source=github : Source to sync from: "github" or a local directory path containing SVG files}
        {--force : Overwrite existing manifest without confirmation}';

    protected $description = 'Generate icons-manifest.json from Tabler Icons (GitHub) or a local SVG directory';

    public function handle(): int
    {
        $source = $this->option('source');

        $icons = $source === 'github'
            ? $this->fetchFromGitHub()
            : $this->scanLocalDirectory($source);

        if ($icons === null) {
            return self::FAILURE;
        }

        // Sort alphabetically
        usort($icons, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $manifestPath = config('icon.manifest_path');
        File::ensureDirectoryExists(dirname($manifestPath));

        $json = json_encode($icons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put($manifestPath, $json);

        IconManager::clearCache();

        $this->components->info(sprintf(
            'Manifest generated: %s icons saved to %s',
            number_format(count($icons)),
            $manifestPath
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, label: string}>|null
     */
    private function scanLocalDirectory(string $path): ?array
    {
        if (! File::isDirectory($path)) {
            $this->components->error("Directory not found: $path");

            return null;
        }

        $files = File::glob($path . '/*.svg');

        if (empty($files)) {
            $this->components->error("No SVG files found in: $path");

            return null;
        }

        $this->components->info(sprintf('Scanning %s SVG files...', number_format(count($files))));

        $icons = [];
        foreach ($files as $file) {
            $basename = str_replace('.svg', '', basename($file));
            $icons[] = [
                'name' => $basename,
                'label' => $this->humanize($basename),
            ];
        }

        return $icons;
    }

    /**
     * @return array<int, array{name: string, label: string}>|null
     */
    private function fetchFromGitHub(): ?array
    {
        $this->components->info('Fetching icon list from Tabler Icons GitHub...');

        $response = Http::withoutVerifying()
            ->timeout(60)
            ->get('https://api.github.com/repos/tabler/tabler-icons/releases/latest');

        if ($response->failed()) {
            $this->components->error('Failed to fetch from GitHub API: ' . ($response->reason() ?: 'Unknown error'));

            return null;
        }

        $tagName = str_replace('v', '', $response->json('tag_name', ''));
        $this->components->info("Latest release: v$tagName");

        // Fetch the outline icons directory listing via GitHub Trees API
        $treeResponse = Http::withoutVerifying()
            ->timeout(60)
            ->get("https://api.github.com/repos/tabler/tabler-icons/git/trees/main", [
                'recursive' => '1',
            ]);

        if ($treeResponse->failed()) {
            $this->components->error('Failed to fetch repository tree.');

            return null;
        }

        $tree = $treeResponse->json('tree', []);
        $icons = [];

        foreach ($tree as $item) {
            $path = $item['path'] ?? '';
            if (
                str_starts_with($path, 'icons/outline/')
                && str_ends_with($path, '.svg')
                && ($item['type'] ?? '') === 'blob'
            ) {
                $basename = str_replace('.svg', '', basename($path));
                $icons[] = [
                    'name' => $basename,
                    'label' => $this->humanize($basename),
                ];
            }
        }

        if (empty($icons)) {
            $this->components->error('No outline icons found in repository tree.');

            return null;
        }

        return $icons;
    }

    private function humanize(string $name): string
    {
        return Str::of($name)->replace('-', ' ')->title()->toString();
    }
}

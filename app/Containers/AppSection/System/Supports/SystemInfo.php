<?php

namespace App\Containers\AppSection\System\Supports;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

final class SystemInfo
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getComposerData(): array
    {
        $path = base_path('composer.json');
        if (! File::exists($path)) {
            return [];
        }

        $contents = File::get($path);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, string> $packages
     * @return array<int, array<string, mixed>>
     */
    public function getPackages(array $packages): array
    {
        $list = [];

        foreach ($packages as $name => $version) {
            if ($name === 'php') {
                continue;
            }

            $packageFile = base_path('vendor/' . $name . '/composer.json');
            if (! File::exists($packageFile)) {
                continue;
            }

            $composer = $this->readJsonFile($packageFile);

            $list[] = [
                'name' => $name,
                'version' => $version,
                'dependencies' => Arr::get($composer, 'require', []),
                'dev_dependencies' => Arr::get($composer, 'require-dev', []),
            ];
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSystemEnv(): array
    {
        return [
            'framework_version' => $this->app->version(),
            'timezone' => $this->app['config']->get('app.timezone'),
            'debug_mode' => $this->app->hasDebugModeEnabled(),
            'storage_dir_writable' => File::isWritable($this->app->storagePath()),
            'cache_dir_writable' => File::isReadable($this->app->bootstrapPath('cache')),
            'app_size' => 'N/A',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerEnv(): array
    {
        return [
            'php_version' => phpversion(),
            'memory_limit' => @ini_get('memory_limit'),
            'max_execution_time' => @ini_get('max_execution_time'),
            'server_software' => Request::server('SERVER_SOFTWARE'),
            'server_os' => function_exists('php_uname') ? php_uname() : 'N/A',
            'database_connection_name' => DB::getDefaultConnection(),
            'ssl_installed' => request()->isSecure(),
            'cache_driver' => Cache::getDefaultDriver(),
            'session_driver' => Session::getDefaultDriver(),
            'queue_connection' => Queue::getDefaultDriver(),
            'allow_url_fopen_enabled' => @ini_get('allow_url_fopen'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'curl' => extension_loaded('curl'),
            'exif' => extension_loaded('exif'),
            'pdo' => extension_loaded('pdo'),
            'fileinfo' => extension_loaded('fileinfo'),
            'tokenizer' => extension_loaded('tokenizer'),
            'imagick_or_gd' => extension_loaded('imagick') || extension_loaded('gd'),
            'zip' => extension_loaded('zip'),
            'iconv' => extension_loaded('iconv'),
            'json' => extension_loaded('json'),
            'opcache_enabled' => extension_loaded('Zend OPcache') && @ini_get('opcache.enable'),
            'post_max_size' => @ini_get('post_max_size'),
            'upload_max_filesize' => @ini_get('upload_max_filesize'),
            'max_file_uploads' => @ini_get('max_file_uploads'),
            'max_input_time' => @ini_get('max_input_time'),
            'max_input_vars' => @ini_get('max_input_vars'),
            'display_errors' => @ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'date_timezone' => @ini_get('date.timezone') ?: date_default_timezone_get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDatabaseInfo(): array
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        $info = [
            'driver' => $connection->getDriverName(),
            'database' => $connection->getDatabaseName(),
        ];

        try {
            if ($connection->getDriverName() === 'mysql') {
                $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                $info['version'] = $version;

                $variables = $pdo->query("SHOW VARIABLES LIKE '%max_connections%'")->fetch();
                if ($variables) {
                    $info['max_connections'] = $variables['Value'] ?? 'N/A';
                }

                $charset = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch();
                if ($charset) {
                    $info['charset'] = $charset['Value'] ?? 'N/A';
                }

                $collation = $pdo->query("SHOW VARIABLES LIKE 'collation_database'")->fetch();
                if ($collation) {
                    $info['collation'] = $collation['Value'] ?? 'N/A';
                }
            }
        } catch (\Throwable) {
        }

        return $info;
    }

    public function getRequiredPhpVersion(array $composer): string|null
    {
        $required = Arr::get($composer, 'require.php');
        if (! is_string($required) || $required === '') {
            return null;
        }

        return $this->normalizePhpConstraint($required);
    }

    public function matchesPhpRequirement(string|null $required): bool|null
    {
        if (! $required) {
            return null;
        }

        return version_compare(PHP_VERSION, $required, '>=');
    }

    public function getServerIp(): string|null
    {
        $ip = request()->server('SERVER_ADDR');
        if ($ip) {
            return $ip;
        }

        $hostname = gethostname();
        if ($hostname) {
            return gethostbyname($hostname);
        }

        return null;
    }

    public function getAppSize(): int
    {
        return $this->calculateDirectorySize($this->app->basePath());
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, 2) . ' ' . $units[$power];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        $contents = File::get($path);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function calculateDirectorySize(string $directory): int
    {
        $size = 0;

        foreach (File::allFiles($directory) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function normalizePhpConstraint(string $constraint): string|null
    {
        $constraint = trim($constraint);
        $constraint = preg_replace('/^[^0-9]*/', '', $constraint);
        $constraint = explode('||', $constraint)[0] ?? $constraint;
        $constraint = trim($constraint);
        $constraint = preg_replace('/[^0-9.].*$/', '', $constraint);

        return $constraint !== '' ? $constraint : null;
    }
}

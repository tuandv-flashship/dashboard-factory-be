<?php

namespace App\Containers\AppSection\Media\Providers;

use App\Containers\AppSection\Media\Commands\ClearChunkUploadsCommand;
use App\Containers\AppSection\Media\Storage\BunnyCDN\BunnyCDNAdapter;
use App\Containers\AppSection\Media\Storage\BunnyCDN\BunnyCDNClient;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

final class MediaServiceProvider extends ParentServiceProvider
{
    public function boot(MediaSettingsStore $settings, ConfigRepository $config): void
    {
        $this->applyChunkSettings($config, $settings);
        $this->applyUploadPathSettings($config, $settings);
        $this->applyDiskSettings($config, $settings);
        $this->registerStorageExtensions();
        $this->registerScheduler();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearChunkUploadsCommand::class,
            ]);
        }
    }

    private function registerStorageExtensions(): void
    {
        Storage::extend('bunnycdn', function ($app, array $config): FilesystemAdapter {
            $hostname = (string) ($config['hostname'] ?? '');
            $pullZoneUrl = $hostname !== '' ? $this->normalizeUrl($hostname) : '';

            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    (string) ($config['storage_zone'] ?? ''),
                    (string) ($config['api_key'] ?? ''),
                    (string) ($config['region'] ?? '')
                ),
                $pullZoneUrl
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }

    private function applyChunkSettings(ConfigRepository $config, MediaSettingsStore $settings): void
    {
        $config->set(
            'media.chunk.enabled',
            $settings->getBool('media_chunk_enabled', (bool) $config->get('media.chunk.enabled', false))
        );
        $config->set(
            'media.chunk.chunk_size',
            $settings->getInt('media_chunk_size', (int) $config->get('media.chunk.chunk_size', 0))
        );
        $config->set(
            'media.chunk.max_file_size',
            $settings->getInt('media_max_file_size', (int) $config->get('media.chunk.max_file_size', 0))
        );
    }

    private function applyUploadPathSettings(ConfigRepository $config, MediaSettingsStore $settings): void
    {
        if (! $settings->getBool('media_customize_upload_path', (bool) $config->get('media.customize_upload_path', false))) {
            return;
        }

        $customPath = trim((string) $settings->get('media_upload_path', $config->get('media.upload_path', 'storage')), '/');
        $root = $customPath !== '' ? public_path($customPath) : public_path('storage');
        $url = $customPath !== '' ? rtrim(asset($customPath), '/') : rtrim(asset('storage'), '/');

        $config->set('filesystems.disks.public.root', $root);
        $config->set('filesystems.disks.public.url', $url);
    }

    private function applyDiskSettings(ConfigRepository $config, MediaSettingsStore $settings): void
    {
        $driver = (string) $settings->get(
            'media_driver',
            $config->get('media.driver', $config->get('media.disk', 'public'))
        );

        if ($driver !== '') {
            $config->set('media.driver', $driver);
        }

        $config->set('filesystems.disks.s3.key', $settings->get('media_aws_access_key_id', $config->get('filesystems.disks.s3.key')));
        $config->set('filesystems.disks.s3.secret', $settings->get('media_aws_secret_key', $config->get('filesystems.disks.s3.secret')));
        $config->set('filesystems.disks.s3.region', $settings->get('media_aws_default_region', $config->get('filesystems.disks.s3.region')));
        $config->set('filesystems.disks.s3.bucket', $settings->get('media_aws_bucket', $config->get('filesystems.disks.s3.bucket')));
        $config->set('filesystems.disks.s3.url', $settings->get('media_aws_url', $config->get('filesystems.disks.s3.url')));
        $config->set('filesystems.disks.s3.endpoint', $settings->get('media_aws_endpoint', $config->get('filesystems.disks.s3.endpoint')));
        $config->set(
            'filesystems.disks.s3.use_path_style_endpoint',
            $settings->getBool('media_aws_use_path_style_endpoint', (bool) $config->get('filesystems.disks.s3.use_path_style_endpoint', false))
        );

        $config->set('filesystems.disks.r2.key', $settings->get('media_r2_access_key_id', $config->get('filesystems.disks.r2.key')));
        $config->set('filesystems.disks.r2.secret', $settings->get('media_r2_secret_key', $config->get('filesystems.disks.r2.secret')));
        $config->set('filesystems.disks.r2.bucket', $settings->get('media_r2_bucket', $config->get('filesystems.disks.r2.bucket')));
        $config->set('filesystems.disks.r2.url', $settings->get('media_r2_url', $config->get('filesystems.disks.r2.url')));
        $config->set('filesystems.disks.r2.endpoint', $settings->get('media_r2_endpoint', $config->get('filesystems.disks.r2.endpoint')));
        $config->set(
            'filesystems.disks.r2.use_path_style_endpoint',
            $settings->getBool('media_r2_use_path_style_endpoint', (bool) $config->get('filesystems.disks.r2.use_path_style_endpoint', true))
        );

        $config->set('filesystems.disks.do_spaces.key', $settings->get('media_do_spaces_access_key_id', $config->get('filesystems.disks.do_spaces.key')));
        $config->set('filesystems.disks.do_spaces.secret', $settings->get('media_do_spaces_secret_key', $config->get('filesystems.disks.do_spaces.secret')));
        $config->set('filesystems.disks.do_spaces.region', $settings->get('media_do_spaces_default_region', $config->get('filesystems.disks.do_spaces.region')));
        $config->set('filesystems.disks.do_spaces.bucket', $settings->get('media_do_spaces_bucket', $config->get('filesystems.disks.do_spaces.bucket')));
        $config->set('filesystems.disks.do_spaces.endpoint', $settings->get('media_do_spaces_endpoint', $config->get('filesystems.disks.do_spaces.endpoint')));
        $config->set(
            'filesystems.disks.do_spaces.use_path_style_endpoint',
            $settings->getBool('media_do_spaces_use_path_style_endpoint', (bool) $config->get('filesystems.disks.do_spaces.use_path_style_endpoint', false))
        );

        if ($settings->getBool('media_do_spaces_cdn_enabled', false)) {
            $customDomain = trim((string) $settings->get('media_do_spaces_cdn_custom_domain', ''), '/');
            if ($customDomain !== '') {
                $config->set('filesystems.disks.do_spaces.url', $this->normalizeUrl($customDomain));
            } else {
                $endpoint = (string) $config->get('filesystems.disks.do_spaces.endpoint', '');
                $bucket = (string) $config->get('filesystems.disks.do_spaces.bucket', '');
                $host = parse_url($endpoint, PHP_URL_HOST);
                if ($host && $bucket !== '' && str_contains($host, 'digitaloceanspaces.com')) {
                    $cdnHost = str_replace('digitaloceanspaces.com', 'cdn.digitaloceanspaces.com', $host);
                    $config->set('filesystems.disks.do_spaces.url', 'https://' . $bucket . '.' . $cdnHost);
                }
            }
        }

        $config->set('filesystems.disks.wasabi.key', $settings->get('media_wasabi_access_key_id', $config->get('filesystems.disks.wasabi.key')));
        $config->set('filesystems.disks.wasabi.secret', $settings->get('media_wasabi_secret_key', $config->get('filesystems.disks.wasabi.secret')));
        $config->set('filesystems.disks.wasabi.region', $settings->get('media_wasabi_default_region', $config->get('filesystems.disks.wasabi.region')));
        $config->set('filesystems.disks.wasabi.bucket', $settings->get('media_wasabi_bucket', $config->get('filesystems.disks.wasabi.bucket')));
        $config->set('filesystems.disks.wasabi.root', $settings->get('media_wasabi_root', $config->get('filesystems.disks.wasabi.root')));

        $wasabiRegion = (string) $config->get('filesystems.disks.wasabi.region', '');
        $wasabiBucket = (string) $config->get('filesystems.disks.wasabi.bucket', '');
        if ($wasabiRegion !== '' && $wasabiBucket !== '') {
            $config->set('filesystems.disks.wasabi.url', 'https://' . $wasabiBucket . '.s3.' . $wasabiRegion . '.wasabisys.com');
            $config->set('filesystems.disks.wasabi.endpoint', 'https://s3.' . $wasabiRegion . '.wasabisys.com');
        }

        $config->set('filesystems.disks.bunnycdn.storage_zone', $settings->get('media_bunnycdn_zone', $config->get('filesystems.disks.bunnycdn.storage_zone')));
        $config->set('filesystems.disks.bunnycdn.hostname', $settings->get('media_bunnycdn_hostname', $config->get('filesystems.disks.bunnycdn.hostname')));
        $config->set('filesystems.disks.bunnycdn.api_key', $settings->get('media_bunnycdn_key', $config->get('filesystems.disks.bunnycdn.api_key')));
        $config->set('filesystems.disks.bunnycdn.region', $settings->get('media_bunnycdn_region', $config->get('filesystems.disks.bunnycdn.region')));

        $config->set('filesystems.disks.backblaze.key', $settings->get('media_backblaze_access_key_id', $config->get('filesystems.disks.backblaze.key')));
        $config->set('filesystems.disks.backblaze.secret', $settings->get('media_backblaze_secret_key', $config->get('filesystems.disks.backblaze.secret')));
        $config->set('filesystems.disks.backblaze.region', $settings->get('media_backblaze_default_region', $config->get('filesystems.disks.backblaze.region')));
        $config->set('filesystems.disks.backblaze.bucket', $settings->get('media_backblaze_bucket', $config->get('filesystems.disks.backblaze.bucket')));
        $config->set('filesystems.disks.backblaze.endpoint', $settings->get('media_backblaze_endpoint', $config->get('filesystems.disks.backblaze.endpoint')));
        $config->set(
            'filesystems.disks.backblaze.use_path_style_endpoint',
            $settings->getBool('media_backblaze_use_path_style_endpoint', (bool) $config->get('filesystems.disks.backblaze.use_path_style_endpoint', false))
        );

        if ($settings->getBool('media_backblaze_cdn_enabled', false)) {
            $customDomain = trim((string) $settings->get('media_backblaze_cdn_custom_domain', ''), '/');
            if ($customDomain !== '') {
                $config->set('filesystems.disks.backblaze.url', $this->normalizeUrl($customDomain));
            }
        }
    }

    private function normalizeUrl(string $value): string
    {
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return rtrim($value, '/');
        }

        return 'https://' . ltrim($value, '/');
    }

    private function registerScheduler(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! config('media.chunk.enabled', false)) {
                return;
            }

            if (! config('media.chunk.clear.schedule.enabled', true)) {
                return;
            }

            $cron = (string) config('media.chunk.clear.schedule.cron', '25 * * * *');

            $schedule->command(ClearChunkUploadsCommand::class)
                ->cron($cron);
        });
    }
}

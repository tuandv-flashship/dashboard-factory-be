<?php

namespace App\Containers\AppSection\System\Tasks;

use App\Containers\AppSection\System\Supports\SystemInfo;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetSystemInfoTask extends ParentTask
{
    public function __construct(private readonly SystemInfo $systemInfo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $composer = $this->systemInfo->getComposerData();
        $requiredPhp = $this->systemInfo->getRequiredPhpVersion($composer);

        return [
            'system_env' => $this->systemInfo->getSystemEnv(),
            'server_env' => $this->systemInfo->getServerEnv(),
            'database_info' => $this->systemInfo->getDatabaseInfo(),
            'requirements' => [
                'required_php_version' => $requiredPhp,
                'matches' => $this->systemInfo->matchesPhpRequirement($requiredPhp),
            ],
            'server_ip' => $this->systemInfo->getServerIp(),
        ];
    }
}

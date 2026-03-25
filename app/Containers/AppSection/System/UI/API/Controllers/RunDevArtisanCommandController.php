<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use App\Containers\AppSection\System\UI\API\Requests\RunDevArtisanCommandRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class RunDevArtisanCommandController extends ApiController
{
    public function __invoke(RunDevArtisanCommandRequest $request): JsonResponse
    {
        $command = (string) $request->input('command');
        $options = (array) $request->input('options', []);

        $php = $this->findCliPhp();
        $args = [$php, 'artisan', $command];
        foreach ($options as $key => $value) {
            if ($value === true) {
                $args[] = $key;
            } elseif ($value !== false && $value !== null) {
                $args[] = "{$key}={$value}";
            }
        }

        $process = new Process($args, base_path());
        $process->setTimeout(120);
        $process->run();

        $exitCode = $process->getExitCode();
        $output = trim($process->getOutput() . $process->getErrorOutput());

        return response()->json([
            'data' => [
                'command' => $command,
                'options' => $options,
                'exit_code' => $exitCode,
                'output' => $output,
                'success' => $exitCode === 0,
            ],
        ], $exitCode === 0 ? 200 : 500);
    }

    private function findCliPhp(): string
    {
        // If PHP_BINARY is php-cgi, look for sibling 'php' CLI binary.
        $binary = PHP_BINARY;
        if (str_contains($binary, 'php-cgi')) {
            $cliPath = dirname($binary) . '/php';
            if (is_executable($cliPath)) {
                return $cliPath;
            }
        }

        // Use Symfony's finder as fallback.
        $finder = new PhpExecutableFinder();
        $found = $finder->find(false);

        return $found ?: $binary;
    }
}

<?php

namespace App\Actions;

use App\Actions\Iperf3\RunSpeedtest as RunIperf3Speedtest;
use App\Actions\Ookla\RunSpeedtest as RunOoklaSpeedtest;
use App\Enums\ResultService;
use Lorisleiva\Actions\Concerns\AsAction;

class RunSpeedtest
{
    use AsAction;

    public function handle(
        bool $scheduled = false,
        ?ResultService $service = null,
        ?int $serverId = null,
        ?int $dispatchedBy = null,
        ?string $host = null,
        ?int $port = null,
        ?int $parallel = null,
    ): mixed {
        $configuredService = config('speedtest.service', ResultService::Ookla->value);

        $service = $service ?? ResultService::tryFrom((string) $configuredService) ?? ResultService::Ookla;

        return match ($service) {
            ResultService::Iperf3 => RunIperf3Speedtest::run(
                scheduled: $scheduled,
                host: $host,
                port: $port,
                parallel: $parallel,
                dispatchedBy: $dispatchedBy,
            ),
            default => RunOoklaSpeedtest::run(
                scheduled: $scheduled,
                serverId: $serverId,
                dispatchedBy: $dispatchedBy,
            ),
        };
    }
}

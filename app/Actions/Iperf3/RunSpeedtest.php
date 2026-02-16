<?php

namespace App\Actions\Iperf3;

use App\Enums\ResultService;
use App\Enums\ResultStatus;
use App\Events\SpeedtestWaiting;
use App\Jobs\CheckForInternetConnectionJob;
use App\Jobs\Iperf3\RunSpeedtestJob;
use App\Jobs\Ookla\BenchmarkSpeedtestJob;
use App\Jobs\Ookla\CompleteSpeedtestJob;
use App\Jobs\Ookla\SkipSpeedtestJob;
use App\Jobs\Ookla\StartSpeedtestJob;
use App\Models\Result;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RunSpeedtest
{
    use AsAction;

    public function handle(bool $scheduled = false, ?string $host = null, ?int $port = null, ?int $parallel = null, ?int $dispatchedBy = null): mixed
    {
        $host = $host ?? config('speedtest.iperf3.host');
        $port = $port ?? config('speedtest.iperf3.port');
        $parallel = $parallel ?? config('speedtest.iperf3.parallel', 1);

        $result = Result::create([
            'data->server->name' => $host,
            'data->server->host' => $host,
            'data->server->port' => $port,
            'data->iperf3->parallel' => max($parallel, 1),
            'service' => ResultService::Iperf3,
            'status' => ResultStatus::Waiting,
            'scheduled' => $scheduled,
            'dispatched_by' => $dispatchedBy,
        ]);

        SpeedtestWaiting::dispatch($result);

        Bus::batch([
            [
                new StartSpeedtestJob($result),
                new CheckForInternetConnectionJob($result),
                new SkipSpeedtestJob($result),
                new RunSpeedtestJob($result),
                new BenchmarkSpeedtestJob($result),
                new CompleteSpeedtestJob($result),
            ],
        ])->catch(function (Batch $batch, ?Throwable $e) {
            Log::error(sprintf('Speedtest batch "%s" failed for an unknown reason.', $batch->id));
        })->name('iPerf3 Speedtest')->dispatch();

        return $result;
    }
}

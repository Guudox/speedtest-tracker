<?php

namespace App\Jobs\Iperf3;

use App\Enums\ResultStatus;
use App\Events\SpeedtestFailed;
use App\Events\SpeedtestRunning;
use App\Models\Result;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class RunSpeedtestJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Result $result,
    ) {}

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->result->update([
            'status' => ResultStatus::Running,
        ]);

        SpeedtestRunning::dispatch($this->result);

        try {
            $forward = $this->runPass(reverse: false);
            $reverse = $this->runPass(reverse: true);
        } catch (Throwable $exception) {
            $message = $exception instanceof ProcessFailedException
                ? (trim($exception->getProcess()->getErrorOutput()) ?: trim($exception->getMessage()))
                : trim($exception->getMessage());

            $this->result->update([
                'data->type' => 'log',
                'data->level' => 'error',
                'data->message' => $message,
                'status' => ResultStatus::Failed,
            ]);

            $this->batch()->cancel();

            SpeedtestFailed::dispatch($this->result);

            return;
        }

        $upload = $this->extractBandwidthBytesPerSecond($forward);
        $download = $this->extractBandwidthBytesPerSecond($reverse);
        $uploadBytes = $this->extractTransferredBytes($forward);
        $downloadBytes = $this->extractTransferredBytes($reverse);
        $ping = $this->extractPingMilliseconds($forward) ?? $this->extractPingMilliseconds($reverse) ?? 0.0;

        $this->result->update([
            'ping' => $ping,
            'download' => $download,
            'upload' => $upload,
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'data->server->name' => Arr::get($forward, 'start.connected.0.remote_host', $this->result->server_name),
            'data->server->host' => Arr::get($forward, 'start.connected.0.remote_host', $this->result->server_host),
            'data->server->port' => Arr::get($forward, 'start.connected.0.remote_port', $this->result->server_port),
            'data->iperf3->forward' => $forward,
            'data->iperf3->reverse' => $reverse,
            'data->interface->externalIp' => Arr::get($forward, 'start.connected.0.local_host', $this->result->ip_address),
        ]);
    }

    private function runPass(bool $reverse): array
    {
        $host = $this->result->server_host ?? config('speedtest.iperf3.host');
        $port = (int) ($this->result->server_port ?? config('speedtest.iperf3.port'));
        $duration = (int) config('speedtest.iperf3.duration', 10);
        $parallel = (int) ($this->result->data['iperf3']['parallel'] ?? config('speedtest.iperf3.parallel', 1));
        $bind = config('speedtest.iperf3.bind');

        if (blank($host)) {
            throw new \RuntimeException('No iPerf3 host is configured. Set IPERF3_HOST or provide a host when running the test.');
        }

        $command = array_filter([
            'iperf3',
            '--client',
            $host,
            '--port',
            (string) $port,
            '--json',
            '--time',
            (string) max($duration, 1),
            '--parallel',
            (string) max($parallel, 1),
            $bind ? '--bind' : null,
            $bind,
            $reverse ? '--reverse' : null,
        ]);

        $process = new Process($command);
        $process->setTimeout(max(20, $duration + 15));
        $process->mustRun();

        $decoded = json_decode($process->getOutput(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractBandwidthBytesPerSecond(array $output): int
    {
        $sent = Arr::get($output, 'end.sum_sent.bits_per_second');
        $received = Arr::get($output, 'end.sum_received.bits_per_second');
        $bitsPerSecond = max((float) ($sent ?? 0), (float) ($received ?? 0));

        return (int) round($bitsPerSecond / 8);
    }

    private function extractTransferredBytes(array $output): ?int
    {
        $sent = Arr::get($output, 'end.sum_sent.bytes');
        $received = Arr::get($output, 'end.sum_received.bytes');
        $bytes = max((float) ($sent ?? 0), (float) ($received ?? 0));

        return $bytes > 0 ? (int) round($bytes) : null;
    }

    private function extractPingMilliseconds(array $output): ?float
    {
        $meanRttMicroseconds = Arr::get($output, 'end.streams.0.sender.mean_rtt')
            ?? Arr::get($output, 'end.streams.0.receiver.mean_rtt');

        if (blank($meanRttMicroseconds)) {
            return null;
        }

        return round(((float) $meanRttMicroseconds) / 1000, 3);
    }
}

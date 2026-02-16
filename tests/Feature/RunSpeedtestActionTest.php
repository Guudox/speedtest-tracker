<?php

use App\Actions\RunSpeedtest;
use App\Enums\ResultService;
use Illuminate\Support\Facades\Bus;

test('run speedtest action defaults to ookla service', function () {
    Bus::fake();

    config()->set('speedtest.service', ResultService::Ookla->value);

    $result = RunSpeedtest::run();

    expect($result->service)->toBe(ResultService::Ookla);

    Bus::assertBatched(fn ($batch) => $batch->name === 'Ookla Speedtest');
});

test('run speedtest action dispatches iperf3 service when requested', function () {
    Bus::fake();

    $result = RunSpeedtest::run(
        service: ResultService::Iperf3,
        host: 'iperf3.example.com',
        port: 5202,
    );

    expect($result->service)->toBe(ResultService::Iperf3)
        ->and($result->data['server']['host'])->toBe('iperf3.example.com')
        ->and($result->data['server']['port'])->toBe(5202);

    Bus::assertBatched(fn ($batch) => $batch->name === 'iPerf3 Speedtest');
});

test('run speedtest action uses configured default service', function () {
    Bus::fake();

    config()->set('speedtest.service', ResultService::Iperf3->value);
    config()->set('speedtest.iperf3.host', 'iperf3.default.example.com');

    $result = RunSpeedtest::run();

    expect($result->service)->toBe(ResultService::Iperf3)
        ->and($result->data['server']['host'])->toBe('iperf3.default.example.com');

    Bus::assertBatched(fn ($batch) => $batch->name === 'iPerf3 Speedtest');
});

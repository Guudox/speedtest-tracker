<?php

namespace App\Livewire\Topbar;

use App\Actions\RunSpeedtest;
use App\Enums\ResultService;
use App\Actions\GetOoklaSpeedtestServers;
use App\Helpers\Ookla;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Size;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Actions extends Component implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    public bool $showDashboard = true;

    public function dashboardAction(): Action
    {
        return Action::make('metrics')
            ->iconButton()
            ->icon('tabler-chart-histogram')
            ->color('gray')
            ->url(url: route('home'))
            ->extraAttributes([
                'id' => 'dashboardAction',
            ]);
    }

    public function speedtestAction(): Action
    {
        return Action::make('speedtest')
            ->schema([
                Select::make('service')
                    ->label(__('results.service'))
                    ->default(function (): string {
                        $configured = ResultService::tryFrom((string) config('speedtest.service', ResultService::Ookla->value));

                        return in_array($configured, [ResultService::Ookla, ResultService::Iperf3], true)
                            ? $configured->value
                            : ResultService::Ookla->value;
                    })
                    ->options([
                        ResultService::Ookla->value => ResultService::Ookla->getLabel(),
                        ResultService::Iperf3->value => ResultService::Iperf3->getLabel(),
                    ])
                    ->live(),
                Select::make('server_id')
                    ->label(__('results.select_server'))
                    ->helperText(__('results.select_server_helper'))
                    ->options(function (Get $get): array {
                        if (($get('service') ?? config('speedtest.service', ResultService::Ookla->value)) !== ResultService::Ookla->value) {
                            return [];
                        }

                        return array_filter([
                            __('results.manual_servers') => Ookla::getConfigServers(),
                            __('results.closest_servers') => GetOoklaSpeedtestServers::run(),
                        ]);
                    })
                    ->searchable()
                    ->visible(fn (Get $get): bool => ($get('service') ?? config('speedtest.service', ResultService::Ookla->value)) === ResultService::Ookla->value),
                TextInput::make('host')
                    ->label('iPerf3 host')
                    ->placeholder(config('speedtest.iperf3.host'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => ($get('service') ?? config('speedtest.service', ResultService::Ookla->value)) === ResultService::Iperf3->value),
                TextInput::make('port')
                    ->label('iPerf3 port')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->placeholder((string) config('speedtest.iperf3.port', 5201))
                    ->visible(fn (Get $get): bool => ($get('service') ?? config('speedtest.service', ResultService::Ookla->value)) === ResultService::Iperf3->value),
                TextInput::make('parallel')
                    ->label('Concurrent connections')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(128)
                    ->placeholder((string) config('speedtest.iperf3.parallel', 1))
                    ->visible(fn (Get $get): bool => ($get('service') ?? config('speedtest.service', ResultService::Ookla->value)) === ResultService::Iperf3->value),
            ])
            ->action(function (array $data) {
                $service = ResultService::tryFrom((string) ($data['service'] ?? config('speedtest.service', ResultService::Ookla->value)))
                    ?? ResultService::Ookla;
                $serverId = $data['server_id'] ?? null;

                RunSpeedtest::run(
                    service: $service,
                    serverId: $serverId,
                    host: $data['host'] ?? null,
                    port: filled($data['port'] ?? null) ? (int) $data['port'] : null,
                    parallel: filled($data['parallel'] ?? null) ? (int) $data['parallel'] : null,
                    dispatchedBy: Auth::id(),
                );

                Notification::make()
                    ->title(__('results.speedtest_started'))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('results.speedtest'))
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('results.start'))
            ->button()
            ->size(request()->is('filament*') ? Size::Medium : Size::Large)
            ->color('primary')
            ->label(__('results.speedtest'))
            ->icon('tabler-rocket')
            ->iconPosition(IconPosition::Before)
            ->hidden(! Auth::check() && Auth::user()->is_admin)
            ->extraAttributes([
                'id' => 'speedtestAction',
            ]);
    }

    public function render()
    {
        return view('livewire.topbar.actions');
    }
}

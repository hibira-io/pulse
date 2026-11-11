@php
    $bytes = function (int $n): string {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($n < 1024 || $unit === 'TB') {
                return ($unit === 'B' ? $n : number_format($n, $n < 10 ? 1 : 0)).' '.$unit;
            }
            $n /= 1024;
        }
    };
    $share = fn (int $used, int $limit): ?int => $limit > 0 ? (int) min(100, round($used * 100 / $limit)) : null;
@endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Hibira"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="{{ __('hibira-pulse::card.periods.'.($this->period ?: '1_hour')) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.arrows-left-right />
        </x-slot:icon>
        @if (! $usage['error'])
            <x-slot:actions>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                        <div class="h-0.5 w-3 rounded-full bg-[#9333ea]"></div>
                        {{ __('hibira-pulse::card.connections') }}
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                        <div class="h-0.5 w-3 rounded-full bg-[rgba(107,114,128,0.5)]"></div>
                        {{ __('hibira-pulse::card.messages') }}
                    </div>
                </div>
            </x-slot:actions>
        @endif
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.30s="">
        @if ($usage['error'])
            <div class="hp-error" role="alert">{{ $usage['error'] }}</div>
        @else
            <div class="hp-stats">
                @php($connShare = $share($usage['connections_now'], $usage['max_connections']))
                <div class="hp-stat">
                    <div class="hp-label">{{ __('hibira-pulse::card.connections_now') }}</div>
                    <div class="hp-value">{{ number_format($usage['connections_now']) }}</div>
                    <div class="hp-sub">
                        {{ __('hibira-pulse::card.peak', ['n' => number_format($usage['connections_peak'])]) }}
                        @if ($usage['max_connections'] > 0)
                            · {{ __('hibira-pulse::card.of_limit', ['n' => number_format($usage['max_connections'])]) }}
                        @endif
                    </div>
                    @if ($connShare !== null)
                        <div class="hp-bar" title="{{ $connShare }}%"><div class="hp-fill {{ $connShare >= 90 ? 'hp-hot' : '' }}" style="width: {{ $connShare }}%"></div></div>
                    @endif
                </div>

                @php($msgShare = $share($usage['messages_today'], $usage['daily_messages']))
                <div class="hp-stat">
                    <div class="hp-label">{{ __('hibira-pulse::card.messages_today') }}</div>
                    <div class="hp-value">{{ number_format($usage['messages_today']) }}</div>
                    <div class="hp-sub">
                        @if ($usage['daily_messages'] > 0)
                            {{ __('hibira-pulse::card.of_limit', ['n' => number_format($usage['daily_messages'])]) }}
                        @else
                            {{ __('hibira-pulse::card.in_period', ['n' => number_format($usage['messages_total'])]) }}
                        @endif
                    </div>
                    @if ($msgShare !== null)
                        <div class="hp-bar" title="{{ $msgShare }}%"><div class="hp-fill {{ $msgShare >= 90 ? 'hp-hot' : '' }}" style="width: {{ $msgShare }}%"></div></div>
                    @endif
                </div>

                <div class="hp-stat">
                    <div class="hp-label">{{ __('hibira-pulse::card.data_sent') }}</div>
                    <div class="hp-value">{{ $bytes($usage['bytes_total']) }}</div>
                    <div class="hp-sub">{{ __('hibira-pulse::card.periods.'.($this->period ?: '1_hour')) }}</div>
                </div>
            </div>

            <div wire:ignore class="hp-chart" x-data="hibiraPulseChart({ usage: @js($usage) })">
                <canvas x-ref="canvas" class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
            </div>

            @if ($usage['channels_error'])
                <div class="hp-error" role="alert">{{ $usage['channels_error'] }}</div>
            @elseif (is_array($usage['channels']))
                <h3 class="hp-heading">{{ __('hibira-pulse::card.busiest_channels') }}</h3>
                @if ($usage['channels'] === [])
                    <p class="hp-empty">{{ __('hibira-pulse::card.no_channels') }}</p>
                @else
                    <x-pulse::table>
                        <colgroup>
                            <col width="100%" />
                            <col width="0%" />
                        </colgroup>
                        <x-pulse::thead>
                            <tr>
                                <x-pulse::th>{{ __('hibira-pulse::card.channel') }}</x-pulse::th>
                                <x-pulse::th class="text-right">{{ __('hibira-pulse::card.subscriptions') }}</x-pulse::th>
                            </tr>
                        </x-pulse::thead>
                        <tbody>
                            @foreach ($usage['channels'] as $channel)
                                <tr wire:key="{{ $channel['name'] }}-spacer" class="h-2 first:h-0"></tr>
                                <tr wire:key="{{ $channel['name'] }}-row">
                                    <x-pulse::td class="max-w-[1px]">
                                        <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $channel['name'] }}">{{ $channel['name'] }}</code>
                                    </x-pulse::td>
                                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                        {{ number_format($channel['subscriptions']) }}
                                    </x-pulse::td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-pulse::table>
                @endif
            @endif
        @endif
    </x-pulse::scroll>
</x-pulse::card>

@script
<script>
Alpine.data('hibiraPulseChart', (config) => ({
    init() {
        let chart = new Chart(this.$refs.canvas, {
            type: 'line',
            data: {
                labels: this.labels(config.usage),
                datasets: [
                    {
                        label: @js(__('hibira-pulse::card.connections')),
                        borderColor: '#9333ea',
                        data: config.usage.peak_connections,
                        yAxisID: 'connections',
                        order: 0,
                    },
                    {
                        label: @js(__('hibira-pulse::card.messages')),
                        borderColor: 'rgba(107,114,128,0.5)',
                        data: config.usage.messages,
                        yAxisID: 'messages',
                        order: 1,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                layout: { autoPadding: false, padding: { top: 1 } },
                datasets: {
                    line: {
                        borderWidth: 2,
                        borderCapStyle: 'round',
                        pointHitRadius: 10,
                        pointStyle: false,
                        tension: 0.2,
                        spanGaps: false,
                    },
                },
                scales: {
                    x: { display: false },
                    connections: { display: false, min: 0, suggestedMax: 1 },
                    messages: { display: false, min: 0, suggestedMax: 1 },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        position: 'nearest',
                        intersect: false,
                    },
                },
            },
        })

        Livewire.on('hibira-pulse-chart-update', ({ usage }) => {
            if (chart === undefined || usage.error) {
                return
            }
            chart.data.labels = this.labels(usage)
            chart.data.datasets[0].data = usage.peak_connections
            chart.data.datasets[1].data = usage.messages
            chart.update()
        })
    },
    labels(usage) {
        return usage.labels.map((label) => label.length > 10 ? formatDate(label) : label)
    },
}))
</script>
@endscript

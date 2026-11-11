<?php

namespace Hibira\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Hibira\Pulse\HibiraApi;
use Hibira\Pulse\HibiraApiException;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Livewire\Livewire;

/**
 * <livewire:hibira.usage cols="6" />: your Hibira app's connections,
 * messages and data sent next to the plan's limits, and the busiest
 * channels right now.
 */
#[Lazy]
class HibiraCard extends Card
{
    public function render(): Renderable
    {
        [$usage, $time, $runAt] = $this->remember(
            fn () => $this->read(App::make(HibiraApi::class)),
            ttl: max(1, (int) config('hibira-pulse.cache_seconds', 60)),
        );

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('hibira-pulse-chart-update', usage: $usage);
        }

        return View::make('hibira-pulse::livewire.hibira', [
            'usage' => $usage,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    /** @return array<string, mixed> */
    protected function read(HibiraApi $api): array
    {
        if ($missing = $api->missing()) {
            return ['error' => __('hibira-pulse::card.missing', ['keys' => implode(', ', array_map(
                fn ($k) => ['token' => 'HIBIRA_API_TOKEN', 'app_id' => 'HIBIRA_APP_ID'][$k],
                $missing,
            ))])];
        }

        try {
            $daily = $api->daily(7);
            $weekly = $this->period === '7_days';
            $series = $weekly ? $this->fromDaily($daily) : $this->fromRecent($api->recent($this->hours()));
        } catch (HibiraApiException $e) {
            return ['error' => $e->getMessage()];
        } catch (ConnectionException) {
            return ['error' => __('hibira-pulse::card.unreachable')];
        }

        $limits = (array) ($daily['limits'] ?? []);
        $todayMessages = (int) (collect($daily['messages'] ?? [])->last() ?? 0);
        $peaks = $series['peak_connections'];

        $channels = null;
        $channelsError = null;
        $limit = (int) config('hibira-pulse.channels', 10);
        if ($limit > 0 && $api->canListChannels()) {
            try {
                $channels = $api->channels($limit);
            } catch (HibiraApiException|ConnectionException $e) {
                $channelsError = $e instanceof HibiraApiException ? $e->getMessage() : __('hibira-pulse::card.unreachable');
            }
        }

        return [
            'error' => null,
            'labels' => $series['labels'],
            'messages' => $series['messages'],
            'peak_connections' => $peaks,
            'bytes_sent' => $series['bytes_sent'],
            'connections_now' => (int) (end($peaks) ?: 0),
            'connections_peak' => $peaks === [] ? 0 : (int) max($peaks),
            'max_connections' => (int) ($limits['max_connections'] ?? 0),
            'messages_total' => array_sum($series['messages']),
            'messages_today' => $todayMessages,
            'daily_messages' => (int) ($limits['daily_messages'] ?? 0),
            'bytes_total' => array_sum($series['bytes_sent']),
            'channels' => $channels,
            'channels_error' => $channelsError,
        ];
    }

    protected function hours(): int
    {
        return match ($this->period) {
            '6_hours' => 6,
            '24_hours' => 24,
            default => 1,
        };
    }

    /**
     * @param  array<string, mixed>  $recent
     * @return array{labels: list<string>, messages: list<int>, peak_connections: list<int>, bytes_sent: list<int>}
     */
    protected function fromRecent(array $recent): array
    {
        $start = CarbonImmutable::parse((string) ($recent['start'] ?? 'now'))->utc();
        $slot = max(1, (int) ($recent['slot_seconds'] ?? 300));
        $messages = array_map('intval', (array) ($recent['messages'] ?? []));

        return [
            'labels' => array_map(fn ($i) => $start->addSeconds($i * $slot)->format('Y-m-d H:i:s'), array_keys($messages)),
            'messages' => $messages,
            'peak_connections' => array_map('intval', (array) ($recent['peak_connections'] ?? [])),
            'bytes_sent' => array_map('intval', (array) ($recent['bytes_sent'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $daily
     * @return array{labels: list<string>, messages: list<int>, peak_connections: list<int>, bytes_sent: list<int>}
     */
    protected function fromDaily(array $daily): array
    {
        return [
            'labels' => array_map('strval', (array) ($daily['days'] ?? [])),
            'messages' => array_map('intval', (array) ($daily['messages'] ?? [])),
            'peak_connections' => array_map('intval', (array) ($daily['peak_connections'] ?? [])),
            'bytes_sent' => array_map('intval', (array) ($daily['bytes_sent'] ?? [])),
        ];
    }

    protected function css()
    {
        return __DIR__.'/../../resources/css/hibira-pulse.css';
    }
}

<?php

namespace Hibira\Pulse;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Reads what the card shows: usage and plan limits from the account API
 * (hibira.io/api/v1, personal API token) and, with the app's key and
 * secret, the occupied channels from the Channels HTTP API.
 */
class HibiraApi
{
    /** @param array<string, mixed> $config */
    public function __construct(protected Factory $http, protected array $config)
    {
    }

    /** @return list<string> the config keys the card can't work without */
    public function missing(): array
    {
        return array_values(array_filter(['token', 'app_id'], fn ($k) => blank($this->config[$k] ?? null)));
    }

    public function canListChannels(): bool
    {
        return filled($this->config['key'] ?? null) && filled($this->config['secret'] ?? null) && filled($this->config['app_id'] ?? null);
    }

    /**
     * 5-minute slots over the last $hours (1–24): {start, slot_seconds,
     * messages[], peak_connections[], bytes_sent[], limits}.
     *
     * @return array<string, mixed>
     */
    public function recent(int $hours): array
    {
        return $this->account('apps/'.rawurlencode((string) $this->config['app_id']).'/usage/recent', [
            'hours' => max(1, min(24, $hours)),
        ]);
    }

    /**
     * One figure per UTC day over the last $days: {days[], messages[],
     * peak_connections[], bytes_sent[], limits}.
     *
     * @return array<string, mixed>
     */
    public function daily(int $days): array
    {
        return $this->account('apps/'.rawurlencode((string) $this->config['app_id']).'/usage', ['days' => max(1, $days)]);
    }

    /**
     * The occupied channels with the most subscribers, busiest first.
     *
     * @return list<array{name: string, subscriptions: int}>
     */
    public function channels(int $limit): array
    {
        $path = '/apps/'.$this->config['app_id'].'/channels';
        $query = $this->sign('GET', $path, ['info' => 'subscription_count']);
        $scheme = ($this->config['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
        $port = (int) ($this->config['port'] ?? ($scheme === 'https' ? 443 : 80));
        $default = $scheme === 'https' ? 443 : 80;
        $base = $scheme.'://'.($this->config['host'] ?? 'ws.hibira.io').($port === $default ? '' : ':'.$port);

        $response = $this->http->acceptJson()->timeout(5)->get($base.$path, $query);
        $this->throwUnless($response);

        $channels = [];
        foreach ((array) $response->json('channels', []) as $name => $info) {
            $channels[] = ['name' => (string) $name, 'subscriptions' => (int) ($info['subscription_count'] ?? 0)];
        }
        usort($channels, fn ($a, $b) => [$b['subscriptions'], $a['name']] <=> [$a['subscriptions'], $b['name']]);

        return array_slice($channels, 0, max(0, $limit));
    }

    /**
     * The Pusher-protocol request signature.
     *
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    public function sign(string $method, string $path, array $params, ?int $time = null): array
    {
        $params += [
            'auth_key' => (string) $this->config['key'],
            'auth_timestamp' => (string) ($time ?? time()),
            'auth_version' => '1.0',
        ];
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k.'='.$v;
        }
        $params['auth_signature'] = hash_hmac('sha256', $method."\n".$path."\n".implode('&', $pairs), (string) $this->config['secret']);

        return $params;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function account(string $path, array $query): array
    {
        $response = $this->request()->get(rtrim((string) ($this->config['api_url'] ?? 'https://hibira.io/api/v1'), '/').'/'.$path, $query);
        $this->throwUnless($response);

        return (array) $response->json();
    }

    protected function request(): PendingRequest
    {
        return $this->http->acceptJson()->timeout(5)->withToken((string) $this->config['token']);
    }

    protected function throwUnless(Response $response): void
    {
        if ($response->successful()) {
            return;
        }
        $message = $response->json('error');
        throw new HibiraApiException(
            is_string($message) && $message !== '' ? $message : 'Hibira answered HTTP '.$response->status().'.',
            $response->status(),
            is_string($response->json('code')) ? $response->json('code') : null,
        );
    }
}

<?php

namespace Hibira\Pulse\Tests;

use Hibira\Pulse\HibiraApi;
use Hibira\Pulse\Livewire\HibiraCard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

class HibiraCardTest extends TestCase
{
    protected function fakeHibira(array $channels = ['presence-room' => ['subscription_count' => 4], 'orders' => ['subscription_count' => 12], 'news' => ['subscription_count' => 1]]): void
    {
        Http::fake([
            'hibira.io/api/v1/apps/app_1/usage/recent*' => Http::response([
                'start' => '2026-11-11T10:00:00Z', 'slot_seconds' => 300,
                'messages' => [10, 20, 30], 'peak_connections' => [5, 9, 7], 'bytes_sent' => [1024, 2048, 1024],
                'limits' => ['max_connections' => 100, 'daily_messages' => 200000],
            ]),
            'hibira.io/api/v1/apps/app_1/usage*' => Http::response([
                'days' => ['2026-11-09', '2026-11-10', '2026-11-11'],
                'messages' => [1000, 2000, 1234], 'peak_connections' => [40, 60, 9], 'bytes_sent' => [10, 20, 4096],
                'limits' => ['max_connections' => 100, 'daily_messages' => 200000],
            ]),
            'ws.hibira.io/apps/app_1/channels*' => Http::response(['channels' => $channels]),
        ]);
    }

    public function test_it_shows_usage_against_the_plan_limits(): void
    {
        $this->fakeHibira();

        Livewire::withoutLazyLoading()->test(HibiraCard::class)
            ->assertSee('Connections now')
            ->assertSeeInOrder(['Connections now', '7', 'Peak 9', 'of 100 on your plan'])
            ->assertSeeInOrder(['Messages today', '1,234', 'of 200,000 on your plan'])
            ->assertSeeInOrder(['Data sent', '4.0 KB', 'past hour'])
            ->assertSee('width: 7%', false);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/usage/recent?hours=1')
            && $r->hasHeader('Authorization', 'Bearer hkb_pat_test'));
    }

    public function test_it_lists_the_busiest_channels_first(): void
    {
        $this->fakeHibira();
        config(['hibira-pulse.channels' => 2]);

        Livewire::withoutLazyLoading()->test(HibiraCard::class)
            ->assertSeeInOrder(['Busiest channels', 'orders', '12', 'presence-room', '4'])
            ->assertDontSee('>news<', false);

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'ws.hibira.io/apps/app_1/channels')) {
                return false;
            }
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['info'] === 'subscription_count' && $q['auth_key'] === 'key1'
                && $q === app(HibiraApi::class)->sign('GET', '/apps/app_1/channels', ['info' => 'subscription_count'], (int) $q['auth_timestamp']);
        });
    }

    public function test_seven_days_reads_the_daily_usage(): void
    {
        $this->fakeHibira();

        Livewire::withoutLazyLoading()->withQueryParams(['period' => '7_days'])->test(HibiraCard::class)
            ->assertSeeInOrder(['Data sent', '4.0 KB', 'past 7 days'])
            ->assertSee('Peak 60');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/usage/recent'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/apps/app_1/usage?days=7'));
    }

    public function test_without_key_and_secret_it_skips_the_channel_list(): void
    {
        $this->fakeHibira();
        config(['hibira-pulse.secret' => null]);

        Livewire::withoutLazyLoading()->test(HibiraCard::class)
            ->assertSee('Messages today')
            ->assertDontSee('Busiest channels');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/channels'));
    }

    public function test_it_names_missing_settings(): void
    {
        Http::fake();
        config(['hibira-pulse.token' => null]);

        Livewire::withoutLazyLoading()->test(HibiraCard::class)
            ->assertSee('Set HIBIRA_API_TOKEN in .env to show your Hibira usage.');

        Http::assertNothingSent();
    }

    public function test_it_shows_the_api_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'The API token is invalid or has been revoked.', 'code' => 'unauthorized'], 401)]);

        Livewire::withoutLazyLoading()->test(HibiraCard::class)
            ->assertSee('The API token is invalid or has been revoked.')
            ->assertDontSee('Messages today');
    }

    public function test_it_speaks_the_app_locale(): void
    {
        $this->fakeHibira();
        app()->setLocale('zh_TW');

        Livewire::withoutLazyLoading()->test(HibiraCard::class)
            ->assertSee('目前連線數')
            ->assertSee('過去 1 小時');
    }

    public function test_the_card_is_registered_for_the_dashboard(): void
    {
        $html = Blade::render('<livewire:hibira.usage cols="6" />');

        $this->assertStringContainsString('wire:name="hibira.usage"', $html);
        $this->assertStringContainsString('lg:col-span-6', $html);
    }

    public function test_the_signature_follows_the_pusher_protocol(): void
    {
        $signed = app(HibiraApi::class)->sign('GET', '/apps/app_1/channels', ['info' => 'subscription_count'], 1700000000);

        $this->assertSame(
            hash_hmac('sha256', "GET\n/apps/app_1/channels\nauth_key=key1&auth_timestamp=1700000000&auth_version=1.0&info=subscription_count", 'secret1'),
            $signed['auth_signature'],
        );
    }
}

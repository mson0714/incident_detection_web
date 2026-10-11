<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CctvPlaybackTest extends TestCase
{
    private const PAGE = 'https://opencctv.org/cameras/vietnam/ho-chi-minh-city/11b-nguyen-binh-khiem-209690';
    private const IMAGE = 'https://giaothong.hochiminhcity.gov.vn/render/ImageHandler.ashx?id=662b7f651afb9c00172dc992';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_selected_camera_resolves_public_original_image_and_caches_metadata_only(): void
    {
        Http::fake([self::PAGE => Http::response('<a href="'.self::IMAGE.'">Open feed URL</a>')]);
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/api/cctv/209690/playback')->assertOk()
                ->assertJsonPath('data.type', 'snapshot')
                ->assertJsonPath('data.image_url', self::IMAGE)
                ->assertJsonPath('data.refresh_seconds', 20);
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === self::PAGE);
    }

    public function test_unknown_ids_do_not_trigger_outbound_requests(): void
    {
        $this->getJson('/api/cctv/99999999/playback')->assertNotFound();
        $this->getJson('/api/cctv/invalid/playback')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_signed_proxy_private_and_deceptive_urls_are_not_used(): void
    {
        Http::fake([self::PAGE => Http::response(implode('', [
            '<img src="https://opencctv.org/api/feed/private?token=secret">',
            '<a href="http://127.0.0.1/image">Private</a>',
            '<a href="https://giaothong.hochiminhcity.gov.vn.evil.example/render/ImageHandler.ashx?id=662b7f651afb9c00172dc992">Wrong host</a>',
            '<a href="'.self::IMAGE.'&amp;token=secret">Signed URL</a>',
        ]))]);
        $this->getJson('/api/cctv/209690/playback')->assertOk()
            ->assertJsonPath('data.type', 'external_only')
            ->assertJsonPath('data.image_url', null);
    }

    public function test_remote_failure_is_not_cached_as_a_permanent_unsupported_camera(): void
    {
        Http::fake([self::PAGE => Http::sequence()->push('', 403)->push('<a href="'.self::IMAGE.'">Image</a>')]);
        $this->getJson('/api/cctv/209690/playback')->assertStatus(502);
        $this->getJson('/api/cctv/209690/playback')->assertOk()->assertJsonPath('data.type', 'snapshot');
    }

    public function test_source_redirect_is_not_followed(): void
    {
        Http::fake([self::PAGE => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        $this->getJson('/api/cctv/209690/playback')->assertStatus(502);
        Http::assertSentCount(1);
    }

    public function test_connection_timeout_returns_retryable_error(): void
    {
        Http::fake([self::PAGE => Http::failedConnection()]);
        $this->getJson('/api/cctv/209690/playback')->assertStatus(504);
    }
}

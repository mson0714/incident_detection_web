<?php

namespace Tests\Feature;

use Tests\TestCase;

class CctvCatalogTest extends TestCase
{
    public function test_catalog_works_without_database_or_remote_requests(): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
        $response = $this->getJson('/api/cctv')->assertOk()
            ->assertJsonPath('meta.catalog_total', 1283)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(24, 'data');
        $rows = $response->json('data');
        $this->assertSame('OpenCCTV', $rows[0]['source']);
        $this->assertSame('resolve_on_select', $rows[0]['playback']);
        $this->assertSame('unverified', $rows[0]['status']);
        $this->assertArrayNotHasKey('stream_url', $rows[0]);
    }

    public function test_search_accepts_vietnamese_with_or_without_accents(): void
    {
        $ascii = $this->getJson('/api/cctv?q=nguyen%20binh%20khiem')->assertOk()->json('data');
        $accented = $this->getJson('/api/cctv?'.http_build_query(['q' => 'NGUYỄN BỈNH KHIÊM']))->assertOk()->json('data');
        $this->assertNotEmpty($ascii);
        $this->assertSame($ascii, $accented);
        $this->getJson('/api/cctv?q=zzzz-not-a-camera')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_region_pagination_and_bounds(): void
    {
        $region = $this->getJson('/api/cctv?'.http_build_query(['region' => 'Bình Dương']))
            ->assertOk()->assertJsonPath('meta.total', 2)->json('data');
        $this->assertSame(['Bình Dương'], array_values(array_unique(array_column($region, 'region'))));
        $first = $this->getJson('/api/cctv?per_page=6')->assertOk()->assertJsonCount(6, 'data')->json('data');
        $next = $this->getJson('/api/cctv?page=2&per_page=6')->assertOk()->json('data');
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($next, 'id')));
        $last = $this->getJson('/api/cctv?page=999999')->assertOk()->json('meta');
        $this->assertSame($last['last_page'], $last['current_page']);
        foreach (['page=0', 'per_page=999', 'q[]=x', 'region[]=x'] as $query) {
            $this->getJson('/api/cctv?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/cctv?q=&region=')->assertOk();
    }

    public function test_missing_catalog_is_an_explicit_error(): void
    {
        config(['cctv.catalog_path' => base_path('does-not-exist-cctv.csv')]);
        $this->getJson('/api/cctv')->assertStatus(503)->assertJsonMissingPath('data');
    }

    public function test_catalog_rejects_unsafe_links_and_deduplicates_with_bom(): void
    {
        $handle = tmpfile();
        try {
            fwrite($handle, "\xEF\xBB\xBFname,page_url\n");
            foreach ([
                ['Test road', 'https://opencctv.org/cameras/vietnam/hanoi/test-road-123'],
                ['Test road', 'https://opencctv.org/cameras/vietnam/hanoi/test-road-123'],
                ['Unsafe', 'javascript:alert(1)'],
                ['Private', 'http://127.0.0.1/private'],
                ['Deceptive host', 'https://opencctv.org.evil.example/cameras/vietnam/hanoi/test-1'],
                ['Query', 'https://opencctv.org/cameras/vietnam/hanoi/test-1?token=x'],
                ['', 'https://opencctv.org/cameras/vietnam/hanoi/test-2'],
            ] as $row) fputcsv($handle, $row, ',', '"', '');
            fflush($handle);
            config(['cctv.catalog_path' => stream_get_meta_data($handle)['uri']]);
            $this->getJson('/api/cctv')->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', '123')->assertJsonPath('data.0.region', 'Hà Nội');
        } finally {
            fclose($handle);
        }
    }

    public function test_direct_local_cctv_route_serves_the_app(): void
    {
        $this->get('/cctv')->assertOk()->assertViewIs('app');
    }
}

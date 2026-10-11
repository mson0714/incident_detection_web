<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CctvController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:6|max:48',
            'q' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:80',
        ]);

        $rows = $this->catalog();

        $query = Str::lower(Str::ascii(trim((string) ($validated['q'] ?? '')), 'vi'));
        $region = trim((string) ($validated['region'] ?? ''));
        $filtered = array_values(array_filter($rows, function (array $row) use ($query, $region): bool {
            $haystack = Str::lower(Str::ascii($row['name'].' '.$row['region'].' '.$row['id'], 'vi'));
            return ($query === '' || str_contains($haystack, $query))
                && ($region === '' || $row['region'] === $region);
        }));

        $regions = array_values(array_unique(array_column($rows, 'region')));
        sort($regions);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 24);
        $total = count($filtered);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return response()->json([
            'data' => array_slice($filtered, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'total' => $total,
                'catalog_total' => count($rows),
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'catalog_updated_at' => config('cctv.catalog_updated_at'),
                'source' => 'OpenCCTV public camera index',
            ],
            'regions' => $regions,
        ]);
    }

    public function playback(string $id)
    {
        $camera = collect($this->catalog())->firstWhere('id', $id);
        abort_unless($camera, 404, 'Camera not found');

        // Resolve only a selected catalog page. Never proxy images, signed feeds or arbitrary URLs.
        try {
            $data = Cache::remember('cctv:public-image:v1:'.sha1($camera['page_url']), 21600, function () use ($camera) {
                $page = Http::connectTimeout(4)->timeout(8)->withoutRedirecting()->get($camera['page_url']);
                abort_unless($page->successful(), 502, 'Camera source is unavailable');
                $html = html_entity_decode($page->body(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = null;
                if (preg_match('~href=["\'](https://giaothong\.hochiminhcity\.gov\.vn/render/ImageHandler\.ashx\?id=[a-f0-9]{24})["\']~i', $html, $match)) {
                    $url = $match[1];
                }

                return [
                    'type' => $url ? 'snapshot' : 'external_only',
                    'image_url' => $url,
                    'refresh_seconds' => 20,
                    'source' => $url ? 'Cổng thông tin giao thông TP.HCM' : 'OpenCCTV',
                    'page_url' => $camera['page_url'],
                ];
            });
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            return response()->json(['message' => 'Camera source timed out'], 504);
        }

        return response()->json(['data' => $data]);
    }

    private function catalog(): array
    {
        $path = config('cctv.catalog_path');
        if (! is_readable($path)) {
            abort(503, 'CCTV catalog is unavailable');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            abort(503, 'CCTV catalog is unavailable');
        }
        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers) $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        if (! $headers || ! in_array('name', $headers, true) || ! in_array('page_url', $headers, true)) {
            fclose($handle);
            abort(503, 'CCTV catalog is invalid');
        }

        $rows = [];
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($values) !== count($headers)) continue;
            $row = array_combine($headers, $values);
            $url = trim($row['page_url']);
            $name = trim($row['name']);
            // Catalog entries are outbound links, never arbitrary URLs for a server-side proxy.
            if ($name === '' || ! preg_match('#^https://opencctv\.org/cameras/vietnam/(?:[a-z0-9-]+/)+[a-z0-9-]+-([0-9]+)$#D', $url, $match)) continue;
            $rows[$url] = [
                'id' => $match[1],
                'name' => $name,
                'region' => $this->regionFromUrl($url),
                'page_url' => $url,
                'source' => 'OpenCCTV',
                'playback' => 'resolve_on_select',
                'status' => 'unverified',
            ];
        }
        fclose($handle);

        return $rows;
    }

    private function regionFromUrl(string $url): string
    {
        if (preg_match('#/cameras/vietnam/([^/]+)/#', $url, $matches)) {
            return match ($matches[1]) {
                'ho-chi-minh-city' => 'TP. Hồ Chí Minh',
                'binh-duong-province' => 'Bình Dương',
                'hanoi' => 'Hà Nội',
                default => ucwords(str_replace('-', ' ', $matches[1])),
            };
        }

        return 'Khác';
    }
}

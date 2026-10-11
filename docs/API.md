# Urban AI API

HTTP/JSON contract for the Laravel backend used by the Vue dashboard. The API serves persisted observations, forecasts, alert events, model metrics and pipeline runs. Loading a page never trains a model or creates an alert.

## Conventions

- Base URL: `/api` (local: `http://localhost:8000/api`).
- Content type: `application/json; charset=utf-8`.
- Timestamps are ISO-8601 strings in UTC (for example `2026-09-13T07:00:00.000000Z`).
- PM2.5 values use `ug/m3`; `horizon` is an integer hour (`1`, `6`, or `24`).
- Validation failures return HTTP `422`; missing stations return HTTP `404`; database health failures return HTTP `503`.
- Most responses include `meta` (served time, city, timezone and latest data timestamp) and `rule` (the locked alert rule).

Validation error envelope (Laravel default):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "station_id": ["The selected station_id is invalid."]
  }
}
```

The `meta` object has this shape:

```json
{
  "mode": "synthetic",
  "city": "Ho Chi Minh City",
  "timezone": "Asia/Ho_Chi_Minh",
  "served_at": "2026-09-13T07:02:00.000000Z",
  "data_as_of": "2026-09-13T07:00:00.000000Z"
}
```

The `rule` object describes the configured h=6 event engine (`threshold`, `open_count`, `close_count`, `cooldown_hours`, `rule_version`, and `description`). It is informational for clients; clients must not recalculate it.

## Endpoints

### `GET /api/cctv`

Read-only catalog for the `/cctv` screen. Reads `docs/data/opencctv-vietnam-traffic.csv` locally; does not use the database, fetch remote images, verify camera uptime, or proxy streams.

| Query | Contract |
| --- | --- |
| `q` | Optional string, up to 120 characters; case/accent-insensitive match on name, region or camera ID |
| `region` | Optional exact region label from `regions`, up to 80 characters |
| `page` | Integer >= 1, default 1; clamped to the last page |
| `per_page` | Integer 6-48, default 24 |

Response shape (one illustrative row, counts reflect the bundled catalog):

```json
{
  "data": [{
    "id": "353925",
    "name": "An Binh Ward",
    "region": "Bình Dương",
    "page_url": "https://opencctv.org/cameras/vietnam/binh-duong-province/an-binh-ward/an-binh-ward-353925",
    "source": "OpenCCTV",
    "playback": "resolve_on_select",
    "status": "unverified"
  }],
  "meta": {
    "total": 1283,
    "catalog_total": 1283,
    "current_page": 1,
    "last_page": 54,
    "per_page": 24,
    "catalog_updated_at": "2026-10-04",
    "source": "OpenCCTV public camera index"
  },
  "regions": ["Bình Dương", "Hà Nội", "TP. Hồ Chí Minh"]
}
```

`total` is the filtered count; `catalog_total` counts unique accepted page URLs, not online streams. Region labels follow the source catalog, not current administrative boundaries. Empty searches return `data: []`, `total: 0`, and page 1 of 1. Missing/unreadable files or invalid headers return `503` without `data`; invalid queries return `422`.

Only HTTPS OpenCCTV Vietnam camera-page URLs without query strings are accepted; duplicate URLs and unsafe entries are skipped. There is no `stream_url` in the catalog. The local-video player is browser-only and has no upload endpoint.

### `GET /api/cctv/{id}/playback`

Resolves one existing numeric catalog ID (30 requests/minute per client). Reads only that camera's allowlisted OpenCCTV page, with redirects disabled and an 8-second timeout. Accepts only a public original-image link matching `https://giaothong.hochiminhcity.gov.vn/render/ImageHandler.ashx?id=<24 hex characters>`, without extra query parameters. Does not fetch or proxy image bytes, signed OpenCCTV feeds, arbitrary URLs or credentials. Successful metadata is cached for 6 hours; no database is required with a file/array cache driver.

Returns `data: {type: "snapshot", image_url, refresh_seconds: 20, source, page_url}` when found, or `type: "external_only", image_url: null` otherwise. Unknown IDs return `404`, upstream HTTP failures `502`, connection/timeouts `504`, rate limits `429`. Availability of a URL does not prove that its camera is online or that the image is fresh.

The browser displays the original image directly, refreshes only the selected camera every 20 seconds, and pauses on hidden tabs or viewer teardown. Failed refreshes retain the last image with a stale label. Received time is local browser time, not a camera capture timestamp. Snapshot playback is not continuous video.

### `GET /api/health`

Checks application and database connectivity. This route is suitable for Docker/load-balancer probes and does not require authentication.

Healthy response (`200`):

```json
{
  "status": "ok",
  "database": "ok",
  "mode": "synthetic"
}
```

Unavailable database response (`503`):

```json
{ "status": "error", "database": "unavailable" }
```

### `GET /api/dashboard`

Returns the aggregate payload used by the overview screen.

```json
{
  "meta": { "mode": "synthetic", "city": "Ho Chi Minh City", "timezone": "Asia/Ho_Chi_Minh", "served_at": "2026-09-13T07:02:00Z", "data_as_of": "2026-09-13T07:00:00Z" },
  "summary": {
    "station_count": 3,
    "online_count": 3,
    "open_alerts": 1,
    "mean_pm25": 42.1,
    "data_freshness_minutes": 2.0
  },
  "rule": { "rule_version": "pm25-two-consecutive-v1", "threshold": 35.0, "horizon_hour": 6, "open_count": 2, "close_count": 2, "cooldown_hours": 6, "description": "..." },
  "stations": [],
  "pipeline_summary": { "status": "success", "last_success_at": "2026-09-13T07:02:00Z" }
}
```

`stations` uses the station item shape documented below. `mean_pm25` averages only active stations with usable fresh readings and is `null` when none are available. `pipeline_summary.status` follows the latest run by `started_at`; `last_success_at` independently retains the latest successful completion.

### `GET /api/stations`

Returns all stations ordered by ID. There are currently no query parameters.

```json
{
  "data": [
    {
      "id": "ST001",
      "name": "Tram Quan trac Trung tam",
      "district": "Quan 1",
      "latitude": 10.7769,
      "longitude": 106.7009,
      "status": "active",
      "current": {
        "timestamp": "2026-09-13T07:00:00Z",
        "pm25": 42.1,
        "temperature": 30.2,
        "humidity": 81.0,
        "wind_speed": 2.4,
        "quality_flag": "ok"
      },
      "forecast": {
        "forecast_id": 10,
        "forecast_origin": "2026-09-13T07:00:00Z",
        "generated_at": "2026-09-13T07:02:00Z",
        "target_timestamp": "2026-09-13T13:00:00Z",
        "horizon_hour": 6,
        "predicted_pm25": 58.1,
        "model_version": "xgb-v1",
        "data_snapshot_id": "snapshot-20260913-0700",
        "data_quality": "synthetic",
        "status": "available"
      },
      "risk": "elevated",
      "freshness_minutes": 2.0,
      "missing_rate": 0.02
    }
  ],
  "meta": {},
  "rule": {}
}
```

Every station item also contains `forecasts: []`: up to three forecast items ordered by `horizon_hour` (1, 6, 24), all from the same latest `forecast_origin` and `model_version`. The snapshot is chosen deterministically by newest origin, then generated time, then row ID. Missing horizons remain absent; they are not filled from an older snapshot. The singular `forecast` field remains the h=6 forecast or `null`, and `risk` continues to describe h=6 only.

`status` is computed from the latest reading and the configured freshness window. `risk` is `elevated`, `normal`, or `unknown`; unknown means missing/stale data and must not be displayed as safe.

### `GET /api/stations/{id}`

Returns one station plus up to 72 most recent reading rows, a single common-origin/model forecast snapshot, alert events and the stored explanation rows for that snapshot's h=6 forecast.

The response has `data` (the station item above), `history` (`timestamp`, `pm25`, `quality_flag`, `valid`), `forecasts` (forecast items), `events` (alert items), `explanations` (`feature`, `value`, `contribution`), `meta`, and `rule`. `valid` is computed from the configured PM2.5 bounds, quality flag, and non-future timestamp. A missing ID returns:

```json
{ "message": "Station not found" }
```

### `GET /api/forecast`

Reads the current persisted snapshot for one station. `station_id` is required; omit `horizon` to return every supported horizon from that same origin/model. A requested horizon absent from the current snapshot returns `data: []` rather than falling back to an older origin.

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `station_id` | string | yes | Existing station ID, for example `ST001`. |
| `horizon` | integer | no | `1`, `6`, or `24`; omit for all horizons. |

Example request: `/api/forecast?station_id=ST001&horizon=6`.

```json
{
  "data": [
    {
      "forecast_id": 10,
      "forecast_origin": "2026-09-13T07:00:00Z",
      "generated_at": "2026-09-13T07:02:00Z",
      "target_timestamp": "2026-09-13T13:00:00Z",
      "horizon_hour": 6,
      "predicted_pm25": 58.1,
      "model_version": "xgb-v1",
      "data_snapshot_id": "snapshot-20260913-0700",
      "data_quality": "synthetic",
      "valid": true,
      "status": "available"
    }
  ],
  "meta": {},
  "rule": {}
}
```

A forecast also includes `valid`; it is false for an out-of-range value, invalid metadata, unsupported quality flag, or a non-available row. A forecast is returned with `status: "stale"` when its origin or generated time is older than the configured forecast freshness window (90 minutes by default). Stale data must not be presented as current or as evidence of safety. An empty result is a successful response with `data: []`.

### `GET /api/hotspots`

Returns currently elevated stations. The MVP only supports h=6.

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `horizon` | integer | no | Must be `6`; defaults to `6`. |

Response is `{ "data": [station items], "rule": {}, "meta": {} }`, filtered to items whose `risk` is `elevated`. Unknown/stale stations are not silently treated as normal.

### `GET /api/alerts`

Returns event records (not one alert per forecast), newest `last_seen_at` first, with 20 records per page.

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `status` | `open\|closed\|unknown` | no | Filter by event state. |
| `date` | `YYYY-MM-DD` | no | Filters opening times in the configured local calendar day, from midnight inclusive to next midnight exclusive. |
| `station_id` | string | no | Existing station ID. |
| `page` | integer >= 1 | no | Pagination page; default `1`. |
| `current` | boolean | no | When true, restricts results to the configured rule version and h=6 (used by the overview). |

```json
{
  "data": [
    {
      "id": 12,
      "station_id": "ST001",
      "station_name": "Tram Quan trac Trung tam",
      "status": "open",
      "opened_at": "2026-09-13T06:00:00Z",
      "last_seen_at": "2026-09-13T07:00:00Z",
      "closed_at": null,
      "close_reason": null,
      "predicted_pm25": 58.1,
      "threshold": 35.0,
      "model_version": "xgb-v1",
      "rule_version": "pm25-two-consecutive-v1",
      "target_timestamp": "2026-09-13T13:00:00Z",
      "notification_count": 1
    }
  ],
  "meta": { "total": 1, "current_page": 1, "last_page": 1 },
  "rule": {}
}
```

The engine opens after two consecutive valid h=6 forecasts above the threshold and closes after two consecutive values at or below it. A data gap resets the streak and is logged as `unknown`; an existing episode is not falsely closed. Station-row transaction locks serialize evaluation before deduplication. Notification cooldown (currently six hours) is applied to previous notification times for the same station/rule. It suppresses reminders and the notification of a new episode inside the window, but never suppresses event creation: such an event starts with `notification_count: 0`. Ongoing high evidence can emit a reminder when the cooldown expires. These are persisted notification decisions only; no email or SMS is sent.

### `GET /api/model/metrics`

Lists model metric rows ordered by version. Values marked `is_synthetic: true` are illustrative and are not trained-model results.

Each item contains `version`, `algorithm`, `status`, `train_cutoff`, `feature_version`, `is_synthetic`, `metrics` (JSON), `station_metrics`, and `description`; the response is `{ "data": [...], "meta": {} }`.

`station_metrics` is an optional per-station error breakdown. When the stored `metrics` JSON has a `by_station` member, that value is returned unchanged in `station_metrics` (for example, `{ "ST001": { "mae": 3.1, "rmse": 4.0 } }`). When no `by_station` member is stored, `station_metrics` is `null`; the API does not infer or invent station-level measurements.

### `GET /api/pipeline/status`

Returns the latest 20 pipeline runs plus a summary and medallion layer counts.

`summary.status`, `records_processed`, and `freshness_minutes` describe the latest run by `started_at` (ID breaks ties), including failures or running work. `last_success_at` is the latest successful completion and is not used to hide newer failures.

```json
{
  "data": [
    {
      "id": 44,
      "stage": "forecast",
      "status": "success",
      "started_at": "2026-09-13T07:00:00Z",
      "finished_at": "2026-09-13T07:02:00Z",
      "records_processed": 30,
      "freshness_minutes": 2.0,
      "data_snapshot_id": "snapshot-20260913-0700",
      "error_message": null,
      "duration_ms": 120000
    }
  ],
  "summary": { "status": "success", "last_success_at": "2026-09-13T07:02:00Z", "records_processed": 30, "freshness_minutes": 2.0 },
  "layers": [
    { "name": "bronze", "description": "Raw synthetic observations", "record_count": 100, "status": "ok" },
    { "name": "silver", "description": "Validated quality readings", "record_count": 98, "status": "ok" },
    { "name": "gold", "description": "Forecasts and alert events", "record_count": 30, "status": "ok" },
    { "name": "forecast", "description": "Persisted PM2.5 predictions", "record_count": 30, "status": "ok" },
    { "name": "alert-engine", "description": "Rule-based event decisions", "record_count": 3, "status": "ok" }
  ],
  "meta": {}
}
```

## Frontend guidance

- Refresh dashboard forecasts, hotspots and open alerts at most every 60 seconds; refresh stations and metrics less often.
- Preserve `forecast_origin`, `generated_at`, `target_timestamp`, `model_version`, `data_snapshot_id`, `data_quality` and `status` in Vue state so freshness is inspectable.
- Render the three discrete horizons separately. Do not draw a continuous 24-hour line from the 1/6/24-hour points.
- Model explanation contributions describe prediction output only; they are not causal attribution or a health interpretation.

## Flood Detection API (v1)

The auxiliary flood module is isolated under `/api/v1/flood`. `GET /health` is public; all other endpoints require `Authorization: Bearer <FLOOD_API_TOKEN>`. Leave `FLOOD_API_TOKEN` empty until the private worker is configured.

- `POST /cameras` registers camera metadata only (`id`, `name`, `source_type`, optional coordinates). RTSP URLs and credentials are never stored in the database.
- `POST /datasets` registers a prepared manifest using its SHA-256 and an explicit source/license declaration.
- `POST /models` registers a trained artifact and validation/test metrics.
- `POST /predictions` accepts an idempotent prediction (`request_id`, camera/model IDs, UTC `captured_at`, image SHA-256, `probabilities.normal`, `probabilities.flooded`, and `latency_ms`). Probabilities must sum to `1`; the label is derived server-side. Retrying the same request ID returns the original row; reusing it for different evidence returns `409`.
- `GET /cameras`, `/datasets`, `/models`, and `/predictions` provide paginated records. A frame prediction is evidence, not an alert; temporal smoothing/event rules are intentionally a later stage.

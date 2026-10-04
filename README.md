# Airscope - Urban PM2.5 Observatory

Web prototype for the graduation project **"Tích hợp AI trong quản lý đô thị"**. It provides a Laravel 12 API, a Vue 3 dashboard, and a MySQL-compatible schema for station observations, forecasts, alert events, model metrics, and pipeline runs.

> The bundled records are explicitly synthetic demo data. They are not trained-model results, health guidance, or a city-wide pollution map.

## Chạy nhanh trên máy này

Mở PowerShell tại `D:\DoAnTotNghiep\web` (dependencies và `.env` đã được chuẩn bị):

```powershell
powershell -NoProfile -File scripts/local-db.ps1 start
php artisan migrate
php artisan urban:demo
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Mở `http://127.0.0.1:8000`. Nhấn Ctrl+C để dừng web. Dừng DB riêng bằng `powershell -NoProfile -File scripts/local-db.ps1 stop`.

Ứng dụng có tổng quan, bản đồ trạm, dự báo 1/6/24 giờ, chi tiết dữ liệu và giải thích, lịch sử cảnh báo có bộ lọc/phân trang, chỉ số mô hình và nhật ký pipeline. Các trang đều đọc API Laravel; mất kết nối có thông báo rõ ràng, không tự thay bằng số liệu giả trong frontend.

Phạm vi bàn giao là **web MVP**. Dữ liệu TP.HCM được tạo để demo, chưa nối OpenAQ/Open-Meteo, chưa huấn luyện XGBoost/Persistence hoặc tính SHAP thật. Bronze/Silver/Gold hiện chỉ là số liệu minh họa từ DB, chưa có kho Parquet hay pipeline Python. Ngưỡng `35` và cooldown `6` giờ là cấu hình demo, không phải P90 đã tính từ train hoặc chuẩn sức khỏe. Chưa có đăng nhập, phân quyền, email/SMS hoặc triển khai Internet.

Phần bổ sung Flood Detection nằm độc lập trong `ml/flood_detection`: baseline CPU-friendly, preprocessing/split Stage 2 và FastAPI inference worker. Laravel thêm schema/API `/api/v1/flood`; prediction được lưu idempotent nhưng chưa tự mở alert theo từng frame. Cần cung cấp dataset có license rõ ràng và đặt `FLOOD_API_TOKEN` trước khi dùng ngoài máy local.

## Local development

Requirements: PHP 8.2+, Composer, Node.js 20+, and MySQL/MariaDB.

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
# Set DB_DATABASE, DB_USERNAME, DB_PASSWORD and DB_HOST in .env
php artisan migrate
php artisan urban:demo
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. For hot reload, run `npm run dev` in a second terminal while `php artisan serve` stays running. The build creates the Vue bundle in `public/build`.

On this Windows workstation, a separate local MariaDB instance is available on port `3307` through `scripts/local-db.ps1`. It uses the MySQL protocol and the Laravel MySQL driver, and stores data only in `web/storage/local-mysql` (not XAMPP's existing data directory).

```powershell
powershell -NoProfile -File scripts/local-db.ps1 start
php artisan urban:demo
php artisan serve --host=127.0.0.1 --port=8000
```

The existing local `.env` (not committed) uses `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=urban_observatory`, `DB_USERNAME=urban`, `DB_PASSWORD=urban`. These are disposable local defaults, not production credentials. The helper requires XAMPP's MariaDB binaries at `C:\xampp\mysql\bin`; other machines can use their own MySQL server. Use `scripts/local-db.ps1 stop` to stop only that isolated database instance.

Demo observations, forecasts and events are additive and idempotent; the synthetic model-metric snapshot is refreshed in place so a new install also shows its optional station-error breakdown. Run `php artisan urban:demo` before a demo session to add a recent snapshot; repeat calls within the same hour do not duplicate it. Forecasts naturally become stale after the configured freshness window. Never reset or truncate an existing database just to refresh the dashboard.

## Docker / MySQL

1. Create `.env` from `.env.example` only if absent, and set `APP_KEY`, `DB_PASSWORD`, and `DB_ROOT_PASSWORD`. Keep the existing key when data is already in use. If PHP is available locally, `php artisan key:generate` creates the key; otherwise build the image and run `docker compose run --rm --no-deps app php artisan key:generate --show`, then put the result in `.env`.
2. Build and start the private MySQL network:

```powershell
docker compose up --build -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan urban:demo
```

The app is exposed on `http://127.0.0.1:8080`; MySQL is intentionally not published to the host.

Docker uses MySQL 8.4; local checks on this workstation use MariaDB 10.4 through Laravel's MySQL driver. Docker image execution has not been verified here because Docker CLI is unavailable. This unauthenticated prototype is bound to loopback; add access control, TLS, backups and secret management before any shared deployment.

## API

Read-only endpoints are available under `/api`: `/health`, `/dashboard`, `/stations`, `/stations/{id}`, `/forecast`, `/hotspots`, `/alerts`, `/model/metrics`, and `/pipeline/status`. The complete response contract is in [`docs/API.md`](docs/API.md).

The alert engine uses the report's h=6 policy: two consecutive valid forecasts above the internal threshold open an event; two consecutive valid forecasts at or below it close the event. Missing, invalid, stale, and out-of-order data do not create evidence. Forecast identity is immutable and idempotent.

## Checks

```powershell
php artisan test --compact
npm run test:frontend
npm run build
powershell -NoProfile -File scripts/local-db.ps1 start
powershell -NoProfile -File scripts/http-smoke.ps1
```

PHP tests use an isolated in-memory SQLite database, never the developer's MySQL data. HTTP smoke checks exercise the configured MySQL driver. No browser interaction/visual test suite is bundled.

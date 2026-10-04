# Báo cáo Backend / DB Foundation

## 1. Mục tiêu

Xây dựng nền tảng backend và cơ sở dữ liệu cho module giám sát ngập đường, có thể tiếp nhận camera, dataset, model và kết quả inference từ worker ML. Module được tách độc lập với các API PM2.5 hiện có.

## 2. Công nghệ

- Laravel 12 và PHP 8.2+
- MySQL/MariaDB cho môi trường chạy thật
- SQLite in-memory cho test tự động
- REST API JSON dưới prefix `/api/v1/flood`

## 3. Thiết kế cơ sở dữ liệu

Migration: `database/migrations/2026_09_27_000001_create_flood_tables.php`

| Bảng | Vai trò |
|---|---|
| `flood_cameras` | Danh mục camera, vị trí và trạng thái hoạt động |
| `flood_datasets` | Nguồn dữ liệu, license, manifest SHA-256 và thống kê split |
| `flood_models` | Artifact model, thuật toán, dataset sử dụng và metrics |
| `flood_predictions` | Kết quả inference theo camera, model, thời gian và ảnh |
| `flood_events` | Khung lưu event temporal; chưa tự mở event từ một frame đơn |

Các ràng buộc chính:

- Khóa ngoại giữa camera, dataset, model và prediction.
- `request_id` unique để retry inference không tạo bản ghi trùng.
- SHA-256 của manifest, model artifact và ảnh để truy xuất nguồn gốc.
- Timestamp prediction bắt buộc có timezone/offset và không được ở tương lai.
- Không lưu RTSP URL hoặc credential trong database.

## 4. API

API public:

- `GET /api/v1/flood/health`

API yêu cầu `Authorization: Bearer <FLOOD_API_TOKEN>`:

- `GET|POST /api/v1/flood/cameras`
- `GET|POST /api/v1/flood/datasets`
- `GET|POST /api/v1/flood/models`
- `GET|POST /api/v1/flood/predictions`

Prediction request yêu cầu `request_id`, camera/model ID, UTC `captured_at`, image SHA-256, xác suất `normal`/`flooded` và latency. Backend tự suy ra label và confidence; tổng xác suất phải bằng `1`.

Retry cùng `request_id` trả về bản ghi cũ. Dùng cùng ID cho evidence khác trả về `409 Conflict`.

## 5. Bảo mật và vận hành

- Middleware `FloodApiToken` fail-closed khi chưa cấu hình token.
- Validation whitelist label, hash, timestamp, ID và tọa độ.
- API list phân trang mặc định 20 bản ghi.
- `flood_events` được chuẩn bị cho temporal smoothing, cooldown và deduplication ở giai đoạn sau.

## 6. Kiểm thử

Đã bổ sung `tests/Feature/FloodApiContractTest.php`, bao phủ health endpoint, token protection, label derivation và idempotent prediction.

Kết quả toàn bộ Laravel test suite: **30 tests passed, 151 assertions**.

## 7. Cài đặt

```powershell
cd D:\DoAnTotNghiep\web
Copy-Item .env.example .env
# Đặt FLOOD_API_TOKEN trong .env
php artisan migrate
php artisan route:list | Select-String flood
```

## 8. Giới hạn hiện tại

- Chưa có authentication/user role đầy đủ; token hiện dành cho private worker.
- Chưa có queue/stream consumer hoặc RTSP credential vault.
- Một frame prediction chưa tạo alert; cần temporal event engine cho production.
- MariaDB local phải được bật trước khi chạy migration thật.

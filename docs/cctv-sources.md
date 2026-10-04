# CCTV công khai tại Việt Nam cho nghiên cứu

Catalog này phục vụ **nghiên cứu học tập về giao thông, ngập đường và thị giác máy tính**. Nó chỉ liệt kê các trang camera giao thông/thời tiết được công khai trên Internet; không phải cam kết rằng mọi feed đều đang online, liên tục hoặc được phép tải tự động.

> **Tình trạng catalog:** kiểm tra ngày `2026-10-04`.
> **Tệp máy đọc:** [`docs/data/opencctv-vietnam-traffic.csv`](data/opencctv-vietnam-traffic.csv) — `1.283` URL camera duy nhất từ danh mục OpenCCTV (`1.284` listing trên `81` trang phân trang; một listing bị lặp).

Cập nhật lại catalog (chỉ lấy **URL trang công khai**, không tải frame) với:

```powershell
powershell -NoProfile -File scripts/collect-opencctv-vietnam.ps1
```

Script có delay mặc định `500 ms/request`; giảm tốc độ hoặc dừng nếu website yêu cầu. Hãy đọc điều khoản và `robots.txt` trước khi xây collector ảnh/video riêng.

## Nguồn đã xác minh truy cập được

| Nguồn | Phạm vi nhìn thấy | Dùng tốt cho | Lưu ý quyền sử dụng |
| --- | --- | --- | --- |
| [Cổng thông tin giao thông TP.HCM](https://giaothong.hochiminhcity.gov.vn/) | Bản đồ, camera và video mode của hệ thống thành phố | Kiểm tra thủ công, đối chiếu tên/vị trí, nghiên cứu HCM | Nguồn chính thức; dùng giao diện và điều khoản của cổng. Không gọi endpoint nội bộ hàng loạt hoặc vượt xác thực. |
| [OpenCCTV — Vietnam traffic](https://opencctv.org/cameras/vietnam/category/traffic) | `1.284` trang camera; nhiều nhất ở TP.HCM, có Bình Dương và Hà Nội trong catalog | Tạo danh sách điểm, chọn camera để đánh giá thủ công | Aggregator, không phải cơ quan quản lý; mỗi camera có trang riêng và loại feed cần kiểm tra lại. |
| [ARGOS ATLAS — Vietnam](https://argosatlas.com/en/cameras/vietnam/) | Trang nguồn báo `654` camera công khai: `642` ảnh tĩnh và `12` video; số thay đổi theo ngày | Ảnh định kỳ, geolocation, so sánh theo hành lang | Aggregator; cần tài khoản miễn phí trên bản đồ theo mô tả nguồn; phần lớn là still frame, không phải video liên tục. |
| [ARGOS ATLAS — Ho Chi Minh City](https://argosatlas.com/en/cameras/vietnam/ho-chi-minh-city/) | Trang nguồn báo `618` camera TP.HCM, toàn bộ là ảnh tĩnh tại thời điểm kiểm tra | Theo dõi trạng thái đường và xây bộ dữ liệu ảnh theo thời gian | Kiểm tra timestamp mỗi ảnh; camera đứng hình không đồng nghĩa đường vắng. |
| [Cameragiaothong.net](https://www.cameragiaothong.net/cameras) | Bản đồ độc lập; hiển thị nhiều khu vực TP.HCM, Gia Lai, Tây Ninh, Bình Dương/Thuận An và các phường/xã | Duyệt nguồn, tìm camera theo tên đường/khu vực | Trang tự nêu không trực thuộc cơ quan nhà nước; dùng như directory, không xem là nguồn chính thức. |
| [WorldCam — Vietnam traffic](https://worldcam.eu/webcams/category/traffic/vietnam) | Hiện liệt kê trang giao thông Đà Nẵng và TP.HCM | Kiểm tra thủ công, tham khảo webcam | Directory bên thứ ba; số lượng nhỏ hơn các catalog trên. |
| [WorldCam — Đà Nẵng](https://worldcam.eu/webcams/asia/vietnam/15633-da-nang-traffic) | Một webcam giao thông Đà Nẵng | Kiểm tra thủ công/đối chiếu hình ảnh | Không coi đây là API hoặc dataset được cấp phép. |

## Quy mô catalog OpenCCTV

File CSV không chứa ảnh, video, RTSP URL hay thông tin đăng nhập. Mỗi dòng chỉ có:

```text
platform,name,page_url,catalog_url,access,feed_type
```

Một số đường dẫn mẫu:

- [11B Nguyễn Bỉnh Khiêm, TP.HCM](https://opencctv.org/cameras/vietnam/ho-chi-minh-city/11b-nguyen-binh-khiem-209690)
- [255 Hoàng Văn Thụ, TP.HCM](https://opencctv.org/cameras/vietnam/ho-chi-minh-city/255-hoang-van-thu-210152)
- [An Bình, Bình Dương](https://opencctv.org/cameras/vietnam/binh-duong-province/an-binh-ward/an-binh-ward-353925)
- [Danh mục trang 2](https://opencctv.org/cameras/vietnam/category/traffic?page=2)

OpenCCTV ghi loại feed theo từng trang. Một trang mẫu được gắn `IMAGE` và có tọa độ `10.7857, 106.7066`; cần đọc trang camera để xác minh hiện trạng thay vì suy đoán từ tên đường.

## Nguồn chỉ nên dùng để tham khảo

- [TrafficVision.Live — Vietnam](https://trafficvision.live/blog/vietnam-traffic-cameras): kết quả tìm kiếm quảng bá số lượng lớn, nhưng chưa được kiểm chứng độc lập trong catalog này.
- [Vietmap Live](https://vietmaplive.vietmap.vn/): sản phẩm thương mại/app; không mặc định có quyền tải dữ liệu nghiên cứu.
- Các ứng dụng “Camera Giao Thông VN” trên app store: phù hợp để xem thủ công; cần đọc điều khoản và không suy ra có API công khai.

## Quy trình nghiên cứu khuyến nghị

1. Chọn một nhóm nhỏ camera trong CSV, mở `page_url` và ghi lại `captured_at`, tên đường, tọa độ, loại feed và trạng thái online.
2. Đọc `Terms`, `Privacy`, `robots.txt` và điều kiện của **nguồn gốc feed**, không chỉ của trang tổng hợp.
3. Xin phép nếu cần tải định kỳ, lưu trữ lâu dài hoặc công bố lại frame. Ưu tiên ảnh độ phân giải thấp và tự động che biển số/khuôn mặt.
4. Khi tạo dataset, lưu `source_url`, timestamp UTC, SHA-256 ảnh và phiên/camera. Không lưu credential hoặc URL RTSP riêng tư.
5. Chia train/validation/test theo `camera` hoặc `session`, không chia ngẫu nhiên từng frame của cùng một video sang cả ba tập.
6. Ghi rõ dataset là **public-source research sample**, nguồn, thời điểm truy cập, tỷ lệ missing/offline và giấy phép trong báo cáo.

## Liên hệ với module trong repo

Module Flood Detection hiện nhận ảnh hoặc frame đã chuẩn bị; nó chưa có collector camera liên tục. Laravel lưu metadata camera nhưng cố ý **không lưu RTSP URL/credential**. Xem thêm:

- [`docs/flood-detection-baseline-report.md`](flood-detection-baseline-report.md)
- [`docs/backend-db-foundation-report.md`](backend-db-foundation-report.md)
- [`ml/flood_detection/dataset.py`](../ml/flood_detection/dataset.py)
- [`docs/API.md`](API.md) — contract `/api/v1/flood`

Không truy cập camera riêng tư, camera bị lộ mật khẩu, endpoint quản trị hoặc feed cần vượt đăng nhập. Catalog này là danh sách điểm bắt đầu cho nghiên cứu có trách nhiệm, không phải giấy phép khai thác dữ liệu.

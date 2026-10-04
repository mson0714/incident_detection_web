# Báo cáo Flood Detection Baseline

## 1. Mục tiêu

Xây dựng baseline nhẹ cho bài toán phân loại ảnh đường `normal` và `flooded`, phục vụ model phụ trong hệ thống phân tích video đô thị. Baseline chỉ phát hiện trạng thái ngập theo ảnh, không ước lượng độ sâu nước.

## 2. Pipeline

```text
Ảnh/video frame
    -> kiểm tra file và EXIF
    -> RGB + resize/pad
    -> RGB/HSV statistics + edge texture
    -> HistGradientBoostingClassifier
    -> label, confidence, probabilities
```

Các module chính:

- `ml/flood_detection/dataset.py`: validation, preprocessing, grouped split và report.
- `ml/flood_detection/features.py`: trích xuất feature deterministic.
- `ml/flood_detection/baseline.py`: train và đánh giá model.
- `ml/flood_detection/inference.py`: wrapper inference cho ảnh.
- `ml/flood_detection/service.py`: FastAPI worker.

## 3. Feature và model

Feature gồm mean, standard deviation và quantile của RGB; thống kê HSV-like; gradient edge; blue-channel ratio và dark-pixel ratio.

Model sử dụng `HistGradientBoostingClassifier` với `max_iter=160`, `learning_rate=0.08`, `max_leaf_nodes=15` và random seed cố định. Artifact được lưu bằng `joblib`, kèm model version và feature version.

## 4. Chuẩn bị dataset Stage 2

Dataset có thể dùng class folders hoặc CSV. Với video frames, CSV bắt buộc có:

```text
image,label,group
frames/cam01_001.jpg,flooded,cam01_session01
frames/cam01_002.jpg,flooded,cam01_session01
```

`group` phải đại diện cho video/camera/session gốc. Các frame cùng group không được tách sang train, validation và test khác nhau.

Pipeline sinh `train.csv`, `val.csv`, `test.csv`, `manifest.csv`, `dataset_report.json` và ảnh đã chuẩn hóa trong `images/`. Pipeline cũng kiểm tra ảnh hỏng, duplicate decoded-pixel, class balance, split overlap và ghi SHA-256.

## 5. Cách chạy

```powershell
cd D:\DoAnTotNghiep\web

python -m ml.flood_detection.dataset `
  --input data/flood/raw `
  --output data/flood/processed `
  --source FloodNet `
  --license "LICENSE-THỰC-TẾ"

python -m ml.flood_detection.baseline `
  --dataset data/flood/processed `
  --model ml/models/flood-baseline.joblib

uvicorn ml.flood_detection.service:app --port 8090
```

Inference API:

- `GET http://127.0.0.1:8090/health`
- `POST http://127.0.0.1:8090/predict` với multipart field `file`

Response mẫu:

```json
{
  "label": "flooded",
  "confidence": 0.91,
  "probabilities": {"normal": 0.09, "flooded": 0.91},
  "model_version": "flood-baseline-hgb-v1"
}
```

## 6. Kiểm thử hiện tại

- Python modules đã compile thành công.
- Smoke test đã chạy đủ luồng preprocessing -> grouped split -> feature extraction -> train -> evaluation.
- Baseline chưa có benchmark chính thức vì workspace chưa chứa dataset flood thật.

## 7. Tiêu chí đánh giá Stage 2

- Macro F1 và per-class precision/recall.
- Confusion matrix.
- Chỉ dùng test set một lần sau khi khóa model.
- So sánh với majority-class và color-statistics baseline.
- Kiểm tra domain shift bằng camera/session không xuất hiện trong train.
- Đo latency/frame và throughput trên CPU.

## 8. Giới hạn và bước tiếp theo

- Feature thủ công là baseline tham chiếu, không phải model cuối cùng.
- Cần bổ sung FloodNet hoặc dataset được cấp phép, ghi rõ license/source.
- Cần xử lý near-duplicate và kiểm tra chất lượng nhãn thủ công.
- Sau baseline có thể thử MobileNet/EfficientNet nhẹ hoặc segmentation nếu mục tiêu cần vùng ngập.
- Temporal smoothing và event engine nên chạy sau inference, không nhúng vào label của từng frame.

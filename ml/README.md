# Flood Detection baseline

The baseline is intentionally lightweight: deterministic RGB/HSV statistics and texture features feed a `HistGradientBoostingClassifier`. It is suitable for CPU inference and is a reference point for Stage 2 experiments; it does not estimate water depth.

## Stage 2 data preparation

Put images under class folders, for example:

```text
raw/flooded/*.jpg
raw/normal/*.jpg
```

Then run from `web/`:

```powershell
python -m ml.flood_detection.dataset --input data/flood/raw --output data/flood/processed
python -m ml.flood_detection.baseline --dataset data/flood/processed --model ml/models/flood-baseline.joblib
uvicorn ml.flood_detection.service:app --port 8090
```

For frame sequences, pass a labels CSV containing `image,label,group`; `group` should identify a video or camera session so frames from one source never cross train/validation/test splits. `dataset_report.json` records invalid files, class balance, image size, seed and split counts.

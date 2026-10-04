"""Optional FastAPI wrapper used by a camera worker or local demo."""

from pathlib import Path
import os
import tempfile

from fastapi import FastAPI, File, HTTPException, UploadFile

from .inference import FloodDetector

MODEL_PATH = Path(os.getenv("FLOOD_MODEL_PATH", "ml/models/flood-baseline.joblib"))
app = FastAPI(title="Flood Detection Inference", version="1.0.0")
_detector = None


def detector() -> FloodDetector:
    global _detector
    if _detector is None:
        if not MODEL_PATH.exists():
            raise HTTPException(status_code=503, detail=f"Model artifact not found: {MODEL_PATH}")
        _detector = FloodDetector(MODEL_PATH)
    return _detector


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "model_ready": MODEL_PATH.exists(), "model_path": str(MODEL_PATH)}


@app.post("/predict")
async def predict(file: UploadFile = File(...)) -> dict:
    suffix = Path(file.filename or "frame.jpg").suffix or ".jpg"
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as temporary:
        temporary.write(await file.read())
        path = Path(temporary.name)
    try:
        return detector().predict(path)
    finally:
        path.unlink(missing_ok=True)

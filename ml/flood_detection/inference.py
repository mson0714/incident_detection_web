"""Reusable inference wrapper for images and video frames."""

from __future__ import annotations

from pathlib import Path

import joblib

from .features import extract_features


class FloodDetector:
    def __init__(self, model_path: str | Path):
        artifact = joblib.load(model_path)
        self.model = artifact["model"]
        self.labels = artifact.get("labels", list(self.model.classes_))
        self.model_version = artifact.get("model_version", "flood-baseline-hgb-v1")

    def predict(self, image: str | Path) -> dict:
        probabilities = self.model.predict_proba([extract_features(image)])[0]
        index = int(probabilities.argmax())
        return {
            "label": str(self.model.classes_[index]),
            "confidence": round(float(probabilities[index]), 6),
            "probabilities": {str(label): round(float(probability), 6) for label, probability in zip(self.model.classes_, probabilities)},
            "model_version": self.model_version,
        }

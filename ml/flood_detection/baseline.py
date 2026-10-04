"""Train/evaluate the CPU-friendly flooded-road classification baseline."""

from __future__ import annotations

import argparse
import csv
import json
from pathlib import Path

import joblib
import numpy as np
from sklearn.ensemble import HistGradientBoostingClassifier
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix, f1_score

from .features import extract_features


def _read_split(root: Path, csv_path: Path) -> tuple[np.ndarray, np.ndarray]:
    features, labels = [], []
    with csv_path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            features.append(extract_features(root / row["image"]))
            labels.append(row["label"])
    return np.asarray(features, dtype=np.float32), np.asarray(labels)


def train_model(dataset_dir: str | Path, model_path: str | Path, seed: int = 42) -> dict:
    dataset_dir, model_path = Path(dataset_dir), Path(model_path)
    x_train, y_train = _read_split(dataset_dir, dataset_dir / "train.csv")
    x_val, y_val = _read_split(dataset_dir, dataset_dir / "val.csv")
    x_test, y_test = _read_split(dataset_dir, dataset_dir / "test.csv")
    if len(np.unique(y_train)) < 2:
        raise ValueError("Training split must contain at least two classes")
    model = HistGradientBoostingClassifier(max_iter=160, learning_rate=0.08, max_leaf_nodes=15, random_state=seed)
    model.fit(x_train, y_train)
    labels = sorted(np.unique(np.concatenate([y_train, y_val, y_test])).tolist())

    def metrics(x: np.ndarray, y: np.ndarray) -> dict:
        predictions = model.predict(x)
        return {
            "count": int(len(y)), "accuracy": float(accuracy_score(y, predictions)),
            "macro_f1": float(f1_score(y, predictions, average="macro", zero_division=0)),
            "classification_report": classification_report(y, predictions, labels=labels, output_dict=True, zero_division=0),
            "confusion_matrix": confusion_matrix(y, predictions, labels=labels).tolist(),
        }

    artifact = {"model": model, "labels": labels, "model_version": "flood-baseline-hgb-v1", "feature_version": "rgb-hsv-statistics-v1", "input_size": 64}
    model_path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(artifact, model_path)
    report = {"model_version": "flood-baseline-hgb-v1", "feature_version": artifact["feature_version"], "labels": labels, "train": metrics(x_train, y_train), "val": metrics(x_val, y_val), "test": metrics(x_test, y_test), "artifact": str(model_path)}
    (model_path.parent / "metrics.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
    return report


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dataset", required=True, type=Path)
    parser.add_argument("--model", required=True, type=Path)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    print(json.dumps(train_model(args.dataset, args.model, args.seed), indent=2))


if __name__ == "__main__":
    main()

"""Small, CPU-friendly image features for the first flood baseline."""

from pathlib import Path

import numpy as np
from PIL import Image


def _load_rgb(image: str | Path | Image.Image, size: int = 64) -> np.ndarray:
    image_obj = image if isinstance(image, Image.Image) else Image.open(image)
    return np.asarray(image_obj.convert("RGB").resize((size, size)), dtype=np.float32) / 255.0


def extract_features(image: str | Path | Image.Image, size: int = 64) -> np.ndarray:
    """Return deterministic appearance/texture features for one image."""
    rgb = _load_rgb(image, size)
    gray = rgb.mean(axis=2)
    hsv_like = np.stack([rgb.max(axis=2), rgb.min(axis=2), rgb.max(axis=2) - rgb.min(axis=2)], axis=2)
    gx = np.diff(gray, axis=1)
    gy = np.diff(gray, axis=0)
    edge = np.concatenate([np.abs(gx).ravel(), np.abs(gy).ravel()])
    blue_ratio = rgb[:, :, 2] / (rgb.sum(axis=2) + 1e-6)
    dark_ratio = (gray < 0.25).mean()
    channels = np.concatenate([rgb.reshape(-1, 3), hsv_like.reshape(-1, 3)], axis=1)
    return np.asarray(
        np.concatenate([
            channels.mean(axis=0),
            channels.std(axis=0),
            np.quantile(channels, [0.1, 0.5, 0.9], axis=0).reshape(-1),
            [edge.mean(), edge.std(), float((edge > 0.12).mean()), blue_ratio.mean(), blue_ratio.std(), dark_ratio],
        ]),
        dtype=np.float32,
    )

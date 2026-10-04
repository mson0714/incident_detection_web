"""Validate images, preprocess and split whole source groups without leakage."""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import re
from collections import Counter
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps

IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp"}
FIELDS = ["image", "label", "group", "source_image", "pixel_sha256", "processed_sha256"]


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def read_rows(root: Path, labels_csv: Path | None, independent_images: bool) -> list[dict]:
    if labels_csv:
        with labels_csv.open(newline="", encoding="utf-8-sig") as handle:
            reader = csv.DictReader(handle)
            if not {"image", "label", "group"}.issubset(reader.fieldnames or []):
                raise ValueError("CSV requires image,label,group columns; use original video/camera/session as group")
            rows = [{key: (row[key] or '').strip() for key in ("image", "label", "group")} for row in reader]
    else:
        if not independent_images:
            raise ValueError("Provide --labels-csv, or explicitly acknowledge --independent-images for unrelated stills")
        rows = [{"image": p.relative_to(root).as_posix(), "label": p.relative_to(root).parts[0],
                 "group": p.relative_to(root).as_posix()} for p in sorted(root.rglob("*"))
                if p.is_file() and p.suffix.lower() in IMAGE_EXTENSIONS and len(p.relative_to(root).parts) > 1]
    if not rows:
        raise ValueError("No samples found")
    for row in rows:
        if not row['image'] or not row['group'] or not re.fullmatch(r"[a-zA-Z0-9_-]+", row['label']):
            raise ValueError("Every row needs an image, group and a safe label (letters, digits, underscore, hyphen)")
        path = (root / row['image']).resolve()
        if not path.is_relative_to(root):
            raise ValueError(f"Image is outside the input directory: {row['image']}")
        row['image'] = path.relative_to(root).as_posix()
    return sorted(rows, key=lambda row: (row['group'], row['label'], row['image']))


def split_groups(rows: list[dict], seed: int, ratios: tuple[float, float, float]) -> dict[str, list[dict]]:
    if len(ratios) != 3 or any(not np.isfinite(r) or r <= 0 for r in ratios) or not np.isclose(sum(ratios), 1):
        raise ValueError("Three positive split ratios summing to one are required")
    groups = sorted({row['group'] for row in rows})
    labels = sorted({row['label'] for row in rows})
    if len(groups) < 3 or len(labels) < 2:
        raise ValueError("Need at least three independent groups and two classes")
    matrix = np.zeros((len(groups), len(labels)), dtype=int)
    group_index, label_index = {g: i for i, g in enumerate(groups)}, {label: i for i, label in enumerate(labels)}
    for row in rows:
        matrix[group_index[row['group']], label_index[row['label']]] += 1
    if np.any((matrix > 0).sum(axis=0) < 3):
        raise ValueError("Each class must occur in at least three independent groups")
    n_val, n_test = max(1, round(len(groups) * ratios[1])), max(1, round(len(groups) * ratios[2]))
    if n_val + n_test >= len(groups):
        n_val, n_test = 1, 1
    rng, best, best_score = np.random.default_rng(seed), None, float('inf')
    # Search using labels only, never model results. Ratios are approximate for whole groups.
    for _ in range(512):
        order = rng.permutation(len(groups))
        indices = [order[n_val + n_test:], order[:n_val], order[n_val:n_val + n_test]]
        counts = np.stack([matrix[index].sum(axis=0) for index in indices])
        if np.any(counts == 0):
            continue
        score = float(np.abs(counts / matrix.sum(axis=0) - np.asarray(ratios)[:, None]).sum())
        if score < best_score:
            best, best_score = indices, score
    if best is None:
        raise ValueError("Cannot find a class-complete grouped split; add groups or adjust the split protocol")
    assignment = {groups[i]: name for name, indices in zip(('train', 'val', 'test'), best) for i in indices}
    return {name: [row for row in rows if assignment[row['group']] == name] for name in ('train', 'val', 'test')}


def prepare_dataset(input_dir: str | Path, output_dir: str | Path, labels_csv: str | Path | None = None,
                    seed: int = 42, image_size: int = 224, ratios: tuple[float, float, float] = (0.7, 0.15, 0.15),
                    independent_images: bool = False, source: str = 'user-supplied',
                    license_name: str = 'UNVERIFIED', is_synthetic: bool = False) -> dict:
    root, output = Path(input_dir).resolve(), Path(output_dir).resolve()
    if not root.is_dir() or not 32 <= image_size <= 2048:
        raise ValueError("Input must be a directory and image size must be between 32 and 2048")
    if output.exists() or output.is_relative_to(root) or root.is_relative_to(output):
        raise ValueError("Output must be a new directory outside the input tree; existing datasets are immutable")
    valid, invalid, duplicates, seen = [], [], [], {}
    for row in read_rows(root, Path(labels_csv) if labels_csv else None, independent_images):
        try:
            with Image.open(root / row['image']) as image:
                rgb = ImageOps.exif_transpose(image).convert('RGB')
                digest = hashlib.sha256(str(rgb.size).encode() + rgb.tobytes()).hexdigest()
                item = {**row, 'pixel_sha256': digest, 'width': rgb.width, 'height': rgb.height}
        except (OSError, ValueError, Image.DecompressionBombError) as exc:
            invalid.append({**row, 'error': str(exc)})
            continue
        if digest in seen:
            previous = seen[digest]
            if previous['label'] != row['label'] or previous['group'] != row['group']:
                raise ValueError(f"Duplicate image has conflicting label/group: {row['image']}; correct grouping before splitting")
            duplicates.append(row['image'])
            continue
        seen[digest] = item
        valid.append(item)
    if not valid:
        raise ValueError("No valid images found")
    splits = split_groups(valid, seed, ratios)
    report = {
        'schema_version': 1, 'seed': seed, 'image_size': image_size, 'requested_ratios': ratios,
        'source': source, 'license': license_name, 'is_synthetic': is_synthetic,
        'total': len(valid), 'invalid': invalid, 'duplicates_removed': duplicates,
        'labels': sorted({row['label'] for row in valid}), 'class_counts': dict(Counter(row['label'] for row in valid)),
        'original_dimensions': {'min_width': min(row['width'] for row in valid), 'max_width': max(row['width'] for row in valid),
                                'min_height': min(row['height'] for row in valid), 'max_height': max(row['height'] for row in valid)},
        'preprocessing': 'EXIF transpose -> RGB -> aspect-preserving letterbox (black) -> PNG; no augmentation',
        'leakage_unit': 'group', 'group_overlap': False,
        'warnings': ['Exact decoded-pixel duplicates checked; near-duplicates require manual review.',
                     'Group IDs must represent original videos/cameras/flights, not individual extracted frames.',
                     'License/source declarations are supplied by the operator, not independently verified.'],
        'splits': {},
    }
    output.mkdir(parents=True)
    manifest = []
    for split_name, items in splits.items():
        prepared = []
        for row in items:
            path = output / 'images' / f"{row['pixel_sha256']}.png"
            path.parent.mkdir(exist_ok=True)
            with Image.open(root / row['image']) as image:
                ImageOps.pad(ImageOps.exif_transpose(image).convert('RGB'), (image_size, image_size),
                             method=Image.Resampling.LANCZOS, color='black').save(path)
            prepared.append({'image': path.relative_to(output).as_posix(), 'label': row['label'], 'group': row['group'],
                             'source_image': row['image'], 'pixel_sha256': row['pixel_sha256'], 'processed_sha256': sha256(path)})
        with (output / f'{split_name}.csv').open('w', newline='', encoding='utf-8') as handle:
            writer = csv.DictWriter(handle, fieldnames=FIELDS)
            writer.writeheader()
            writer.writerows(prepared)
        manifest.extend({**row, 'split': split_name} for row in prepared)
        report['splits'][split_name] = {'count': len(items), 'groups': len({row['group'] for row in items}),
                                       'class_counts': dict(Counter(row['label'] for row in items))}
    with (output / 'manifest.csv').open('w', newline='', encoding='utf-8') as handle:
        writer = csv.DictWriter(handle, fieldnames=[*FIELDS, 'split'])
        writer.writeheader()
        writer.writerows(manifest)
    report['manifest_sha256'] = sha256(output / 'manifest.csv')
    report['split_sha256'] = {name: sha256(output / f'{name}.csv') for name in splits}
    (output / 'dataset_report.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    return report


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--input', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--labels-csv', type=Path)
    parser.add_argument('--independent-images', action='store_true')
    parser.add_argument('--seed', type=int, default=42)
    parser.add_argument('--image-size', type=int, default=224)
    parser.add_argument('--source', required=True)
    parser.add_argument('--license', required=True)
    parser.add_argument('--synthetic', action='store_true')
    args = parser.parse_args()
    print(json.dumps(prepare_dataset(args.input, args.output, args.labels_csv, args.seed, args.image_size,
                                    independent_images=args.independent_images, source=args.source,
                                    license_name=args.license, is_synthetic=args.synthetic), indent=2))


if __name__ == '__main__':
    main()

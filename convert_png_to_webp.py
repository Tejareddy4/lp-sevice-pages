from __future__ import annotations

import argparse
import sys
from pathlib import Path


def resolve_images_dir(base_dir: Path, provided_dir: str | None) -> Path:
    if provided_dir:
        return (base_dir / provided_dir).resolve()

    lower = (base_dir / "images").resolve()
    upper = (base_dir / "Images").resolve()

    if lower.exists():
        return lower
    return upper


def convert_pngs(images_dir: Path, quality: int) -> tuple[int, int]:
    try:
        from PIL import Image
    except ImportError as exc:
        raise RuntimeError(
            "Pillow is required. Install it with: pip install Pillow"
        ) from exc

    if not images_dir.exists() or not images_dir.is_dir():
        raise FileNotFoundError(f"Images folder not found: {images_dir}")

    converted = 0
    skipped = 0

    for png_file in images_dir.rglob("*.png"):
        webp_file = png_file.with_suffix(".webp")
        try:
            with Image.open(png_file) as img:
                if img.mode in ("P", "LA"):
                    img = img.convert("RGBA")
                elif img.mode not in ("RGB", "RGBA"):
                    img = img.convert("RGB")

                img.save(webp_file, "WEBP", quality=quality, method=6)
            converted += 1
        except Exception as err:
            skipped += 1
            print(f"Skipped: {png_file} ({err})")

    return converted, skipped


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Convert PNG files under images folder to WebP (overwrite existing .webp)."
    )
    parser.add_argument(
        "--dir",
        default=None,
        help="Images directory relative to this script (default: images or Images)",
    )
    parser.add_argument(
        "--quality",
        type=int,
        default=85,
        help="WebP quality (0-100). Default: 85",
    )
    args = parser.parse_args()

    if not (0 <= args.quality <= 100):
        print("Error: --quality must be between 0 and 100")
        return 2

    base_dir = Path(__file__).resolve().parent
    images_dir = resolve_images_dir(base_dir, args.dir)

    try:
        converted, skipped = convert_pngs(images_dir, args.quality)
    except Exception as err:
        print(f"Error: {err}")
        return 1

    print(f"Images folder: {images_dir}")
    print(f"Converted: {converted}")
    print(f"Skipped: {skipped}")
    print("Done. Existing .webp files were overwritten.")
    return 0


if __name__ == "__main__":
    sys.exit(main())

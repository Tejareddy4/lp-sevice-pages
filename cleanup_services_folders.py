from __future__ import annotations

import csv
import re
import shutil
from pathlib import Path


BASE_DIR = Path(__file__).resolve().parent
CSV_PATH = BASE_DIR / "csv" / "Service-pages.csv"
SERVICES_ROOT = BASE_DIR / "services"


def normalize_header(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", (value or "").strip().lower())


def slugify(value: str) -> str:
    text = (value or "").strip().lower()
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return re.sub(r"-+", "-", text).strip("-")


def resolve_csv_path() -> Path:
    if CSV_PATH.exists():
        return CSV_PATH

    csv_dir = CSV_PATH.parent
    candidates = sorted(csv_dir.glob("*.csv")) if csv_dir.exists() else []
    if candidates:
        return candidates[0]

    return CSV_PATH


def read_destination_folders(csv_path: Path) -> set[str]:
    rows: list[dict[str, str]] = []
    try:
        with csv_path.open("r", encoding="utf-8-sig", newline="") as f:
            rows = list(csv.DictReader(f))
    except UnicodeDecodeError:
        with csv_path.open("r", encoding="cp1252", newline="") as f:
            rows = list(csv.DictReader(f))

    if not rows:
        return set()

    fieldnames = rows[0].keys()
    header_map = {normalize_header(h): h for h in fieldnames}
    destination_key = header_map.get("destination") or header_map.get("country")
    if not destination_key:
        raise ValueError("CSV must include Destination or Country column.")

    folders = {
        slugify(str(row.get(destination_key, "")))
        for row in rows
        if str(row.get(destination_key, "")).strip()
    }
    return {f for f in folders if f}


def main() -> None:
    csv_path = resolve_csv_path()
    if not csv_path.exists():
        raise FileNotFoundError(f"CSV file not found: {CSV_PATH}")

    if not SERVICES_ROOT.exists():
        raise FileNotFoundError(f"Services folder not found: {SERVICES_ROOT}")

    keep_folders = read_destination_folders(csv_path)
    existing_dirs = sorted([d for d in SERVICES_ROOT.iterdir() if d.is_dir()])
    to_remove = [d for d in existing_dirs if d.name not in keep_folders]

    for folder in to_remove:
        shutil.rmtree(folder)

    print(f"CSV used: {csv_path}")
    print(f"Keep folders: {len(keep_folders)}")
    print(f"Removed folders: {len(to_remove)}")
    print(f"Remaining folders: {len([d for d in SERVICES_ROOT.iterdir() if d.is_dir()])}")


if __name__ == "__main__":
    main()

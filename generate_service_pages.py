from __future__ import annotations

import csv
import json
import re
from pathlib import Path
from typing import Dict, Iterable, Optional


BASE_DIR = Path(__file__).resolve().parent
CSV_PATH = BASE_DIR / "csv" / "Service-pages.csv"
OUTPUT_ROOT = BASE_DIR / "services"


def resolve_csv_path() -> Path:
    if CSV_PATH.exists():
        return CSV_PATH

    csv_dir = CSV_PATH.parent
    if not csv_dir.exists():
        return CSV_PATH

    candidates = sorted(csv_dir.glob("*.csv"))
    if candidates:
        return candidates[0]

    return CSV_PATH


def normalize_header(value: object) -> str:
    text = str(value or "").strip().lower()
    return re.sub(r"[^a-z0-9]+", "", text)


def slugify(value: str) -> str:
    text = (value or "").strip().lower()
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return re.sub(r"-+", "-", text).strip("-")


def build_header_index(headers: Iterable[object]) -> Dict[str, int]:
    index: Dict[str, int] = {}
    for i, header in enumerate(headers):
        key = normalize_header(header)
        if key:
            index[key] = i
    return index


def find_col(header_index: Dict[str, int], aliases: Iterable[str]) -> Optional[int]:
    for alias in aliases:
        normalized = normalize_header(alias)
        if normalized in header_index:
            return header_index[normalized]
    return None


def cell_text(row: tuple[object, ...], col_idx: Optional[int]) -> str:
    if col_idx is None or col_idx >= len(row):
        return ""
    value = row[col_idx]
    return "" if value is None else str(value)


def nowdoc(var_name: str, token: str, content: str) -> str:
    body = str(content or "").replace("\r\n", "\n").replace("\r", "\n")
    return f"${var_name} = <<<'{token}'\n{body}\n{token};"


def build_default_review_schema(origin: str, destination: str) -> str:
    origin_slug = slugify(origin)
    destination_slug = slugify(destination)
    page_url = f"https://shipglobal.in/lp/{origin_slug}-to-{destination_slug}-courier"

    schema = {
        "@context": "https://schema.org",
        "@type": "Service",
        "name": f"International Courier Service from {origin} to {destination}",
        "provider": {
            "@type": "Organization",
            "name": "ShipGlobal",
            "url": "https://shipglobal.in",
        },
        "serviceType": "International Courier Service",
        "areaServed": [
            {"@type": "Place", "name": origin},
            {"@type": "Country", "name": destination},
        ],
        "url": page_url,
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "189",
        },
    }

    return (
        '<script type="application/ld+json">\n'
        + json.dumps(schema, ensure_ascii=False)
        + "\n</script>"
    )


def make_php_page(origin: str, destination: str, mapped: Dict[str, str]) -> str:
    lines = [
        "<?php",
        "",
        f'$Origin = "{origin}"; //city name',
        f'$Destination = "{destination}"; //country name',
        nowdoc("Excerpt", "EXCERPT", mapped["Excerpt"]),
        nowdoc("Updatedhtml", "UPDATEDHTML", mapped["Updatedhtml"]),
        nowdoc("Relposts", "RELPOSTS", mapped["Relposts"]),
        nowdoc("FAQ", "FAQ", mapped["FAQ"]),
        "",
        "//scripts",
        nowdoc("FAQscript", "FAQSCRIPT", mapped["FAQscript"]),
        nowdoc("Review_Schema", "REVIEWSCHEMA", mapped["Review_Schema"]),
        "",
        'include __DIR__ . "/../../template.php";',
        "",
        "?>",
        "",
    ]
    return "\n".join(lines)


def main() -> None:
    csv_path = resolve_csv_path()
    if not csv_path.exists():
        raise FileNotFoundError(f"CSV file not found: {CSV_PATH}")

    # Prefer UTF-8 with BOM support; fallback keeps generation resilient
    # for CSV files exported with legacy encodings.
    rows: list[list[str]] = []
    try:
        with csv_path.open("r", encoding="utf-8-sig", newline="") as f:
            reader = csv.reader(f)
            rows = [list(r) for r in reader]
    except UnicodeDecodeError:
        with csv_path.open("r", encoding="cp1252", newline="") as f:
            reader = csv.reader(f)
            rows = [list(r) for r in reader]

    if not rows:
        raise ValueError("CSV file has no rows.")

    headers = rows[0]
    if not headers:
        raise ValueError("CSV file has no header row.")

    header_index = build_header_index(headers)

    # Explicit mapping requested:
    # $Origin = City, $Destination = Destination
    # Current workbook uses "Country" for destination in some sheets.
    city_col = find_col(header_index, ["City"])
    destination_col = find_col(header_index, ["Destination", "Country"])

    # Exact mapping requested by the user.
    excerpt_col = find_col(header_index, ["Excerpt"])
    updated_html_col = find_col(header_index, ["Updated Html content"])
    related_posts_col = find_col(header_index, ["Related Posts"])
    coded_faq_col = find_col(header_index, ["Coded FAQ"])
    faq_schema_col = find_col(header_index, ["FAQ Schema"])
    review_schema_col = find_col(header_index, ["Review_Schema"])

    missing = []
    required = {
        "City": city_col is not None,
        "Destination/Country": destination_col is not None,
        "Excerpt": excerpt_col is not None,
        "Updated Html content": updated_html_col is not None,
        "Related Posts": related_posts_col is not None,
        "Coded FAQ": coded_faq_col is not None,
        "FAQ Schema": faq_schema_col is not None,
        "Review_Schema": review_schema_col is not None,
    }
    for name, ok in required.items():
        if not ok:
            missing.append(name)
    if missing:
        raise ValueError(f"Missing required columns in Sheet1: {', '.join(missing)}")

    OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)
    created = 0

    for row in rows[1:]:
        city = cell_text(row, city_col).strip()
        destination = cell_text(row, destination_col).strip()
        if not city or not destination:
            continue

        city_slug = slugify(city)
        destination_slug = slugify(destination)
        if not city_slug or not destination_slug:
            continue

        page_name = f"{city_slug}-to-{destination_slug}-courier.php"
        destination_dir = OUTPUT_ROOT / destination_slug
        destination_dir.mkdir(parents=True, exist_ok=True)

        mapped_values = {
            "Excerpt": cell_text(row, excerpt_col),
            "Updatedhtml": cell_text(row, updated_html_col),
            "Relposts": cell_text(row, related_posts_col),
            "FAQ": cell_text(row, coded_faq_col),
            "FAQscript": cell_text(row, faq_schema_col),
            "Review_Schema": cell_text(row, review_schema_col).strip()
            or build_default_review_schema(city, destination),
        }

        php_content = make_php_page(city, destination, mapped_values)
        (destination_dir / page_name).write_text(php_content, encoding="utf-8")
        created += 1

    print(f"Created/updated {created} page file(s) under: {OUTPUT_ROOT}")


if __name__ == "__main__":
    main()

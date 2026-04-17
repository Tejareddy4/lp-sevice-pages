from __future__ import annotations

import csv
import html
import json
import re
from pathlib import Path
from typing import Dict, Iterable, Optional


SCRIPT_DIR = Path(__file__).resolve().parent
ROOT_DIR = SCRIPT_DIR.parent
CSV_PATH = ROOT_DIR / "data" / "csv" / "Service-pages.csv"
OUTPUT_ROOT = ROOT_DIR / "services"
TEMPLATE_PATH = ROOT_DIR / "template.html"


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


def add_read_more_shortcode(html_content: str) -> str:
    trimmed_html = (html_content or "").strip()
    if not trimmed_html:
        return html_content or ""
    if "[read more]" in trimmed_html or trimmed_html.endswith("[/read]"):
        return html_content or ""

    def _inject(match: re.Match[str]) -> str:
        inner_text = match.group(2).strip()
        if not inner_text:
            return match.group(0)

        words = re.split(r"\s+", inner_text)
        insert_index = max(len(words) - 5, 1)
        before = " ".join(words[:insert_index])
        after = " ".join(words[insert_index:])
        return f"{match.group(1)}{before} <!--...-->[read more] {after}{match.group(3)}"

    updated = re.sub(r"(<[^>]+>)([\s\S]*?)(</[^>]+>)", _inject, trimmed_html, count=1)
    return updated.rstrip() + "[/read]"


def find_featured_image(origin: str, destination: str) -> str:
    destination_slug = slugify(destination)
    origin_slug = slugify(origin)
    page_slug = f"{origin_slug}-to-{destination_slug}"
    image_root = ROOT_DIR / "Images"

    if not image_root.is_dir():
        return ""

    for destination_dir in image_root.iterdir():
        if not destination_dir.is_dir():
            continue
        if slugify(destination_dir.name) != destination_slug:
            continue

        for extension in ("webp", "png", "jpg", "jpeg"):
            file_name = f"{page_slug}.{extension}"
            if not (destination_dir / file_name).is_file():
                continue
            return f"/Services/Images/{destination_dir.name}/{file_name}"

    return ""


def build_featured_image_meta(featured_image_url: str, featured_image_alt: str) -> str:
    if not featured_image_url.strip():
        return ""

    escaped_url = html.escape(featured_image_url, quote=True)
    escaped_alt = html.escape(featured_image_alt, quote=True)
    return "\n".join(
        [
            f'<meta property="og:image" content="{escaped_url}">',
            f'<meta property="og:image:alt" content="{escaped_alt}">',
            '<meta name="twitter:card" content="summary_large_image">',
            f'<meta name="twitter:image" content="{escaped_url}">',
            f'<meta name="twitter:image:alt" content="{escaped_alt}">',
        ]
    )


def build_default_faq_schema(origin: str, destination: str) -> str:
    payload = {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": f"How to send courier from {origin} to {destination}?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": (
                        f"You can send courier from {origin} to {destination} using "
                        "ShipGlobal with affordable pricing and fast international delivery."
                    ),
                },
            }
        ],
    }
    return '<script type="application/ld+json">\n' + json.dumps(payload, ensure_ascii=False) + "\n</script>"


def build_absolute_image_url(featured_image: str) -> str:
    if not featured_image.strip():
        return ""
    if re.match(r"^https?://", featured_image, flags=re.IGNORECASE):
        return featured_image
    return "https://shipglobal.in" + ("" if featured_image.startswith("/") else "/") + featured_image


def render_page(template: str, origin: str, destination: str, mapped: Dict[str, str]) -> str:
    origin_slug = slugify(origin)
    destination_slug = slugify(destination)

    updated_html = add_read_more_shortcode(mapped["Updatedhtml"])
    featured_image = find_featured_image(origin, destination)
    if featured_image.lower().endswith(".png"):
        featured_image = featured_image[: -len(".png")] + ".webp"

    featured_image_alt = f"{origin} to {destination} courier featured image"
    featured_image_seo_url = build_absolute_image_url(featured_image)
    featured_image_meta = build_featured_image_meta(featured_image_seo_url, featured_image_alt)

    review_schema = mapped["Review_Schema"].strip() or build_default_review_schema(origin, destination)
    faq_schema = mapped["FAQscript"].strip() or build_default_faq_schema(origin, destination)

    replacements = {
        "{{TITLE}}": html.escape(f"{origin} to {destination} Courier Shipment"),
        "{{META_DESCRIPTION}}": html.escape(
            f"Ship internationally from {origin} to {destination} with transparent pricing, fast delivery, and customs support.",
            quote=True,
        ),
        "{{ORIGIN_SLUG}}": origin_slug,
        "{{DESTINATION_SLUG}}": destination_slug,
        "{{FEATURED_IMAGE_META}}": featured_image_meta,
        "{{REVIEW_SCHEMA_BLOCK}}": review_schema,
        "{{ORIGIN}}": html.escape(origin),
        "{{DESTINATION}}": html.escape(destination),
        "{{EXCERPT}}": mapped["Excerpt"],
        "{{UPDATED_HTML}}": updated_html,
        "{{RELATED_POSTS}}": mapped["Relposts"],
        "{{FAQ_HTML}}": mapped["FAQ"],
        "{{FAQ_SCHEMA_BLOCK}}": faq_schema,
    }

    rendered = template
    for token, value in replacements.items():
        rendered = rendered.replace(token, value)
    return rendered


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


def main() -> None:
    csv_path = resolve_csv_path()
    if not csv_path.exists():
        raise FileNotFoundError(f"CSV file not found: {CSV_PATH}")
    if not TEMPLATE_PATH.exists():
        raise FileNotFoundError(f"HTML template not found: {TEMPLATE_PATH}")

    template_content = TEMPLATE_PATH.read_text(encoding="utf-8")

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

        page_name = f"{city_slug}-to-{destination_slug}-courier.html"
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

        html_content = render_page(template_content, city, destination, mapped_values)
        (destination_dir / page_name).write_text(html_content, encoding="utf-8")

        legacy_php_path = destination_dir / f"{city_slug}-to-{destination_slug}-courier.php"
        if legacy_php_path.exists():
            legacy_php_path.unlink()

        created += 1

    print(f"Created/updated {created} static HTML page file(s) under: {OUTPUT_ROOT}")


if __name__ == "__main__":
    main()

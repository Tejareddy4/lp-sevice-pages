from __future__ import annotations

from datetime import date
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
ROOT_DIR = SCRIPT_DIR.parent
SERVICES_ROOT = ROOT_DIR / "services"
SITEMAP_PATH = ROOT_DIR / "sitemap.xml"
BASE_URL = "https://shipglobal.in/lp"


def collect_urls() -> list[str]:
    pages = sorted(SERVICES_ROOT.glob("*/*-courier.html"))
    unique_slugs = sorted({page.stem for page in pages})
    return [f"{BASE_URL}/{slug}.html" for slug in unique_slugs]


def build_sitemap(urls: list[str]) -> str:
    today = date.today().isoformat()
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ]

    for url in urls:
        lines.extend(
            [
                "  <url>",
                f"    <loc>{url}</loc>",
                f"    <lastmod>{today}</lastmod>",
                "  </url>",
            ]
        )

    lines.append("</urlset>")
    return "\n".join(lines) + "\n"


def main() -> None:
    if not SERVICES_ROOT.exists():
        raise FileNotFoundError(f"Services folder not found: {SERVICES_ROOT}")

    urls = collect_urls()
    if not urls:
        raise ValueError("No service pages found under services/*/*-courier.html")

    sitemap_content = build_sitemap(urls)
    SITEMAP_PATH.write_text(sitemap_content, encoding="utf-8")
    print(f"Generated sitemap with {len(urls)} URLs: {SITEMAP_PATH}")


if __name__ == "__main__":
    main()

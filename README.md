# Service Pages Generator (CSV to Static HTML)

This project generates courier landing pages as static HTML files from a CSV file and stores them in country-based subfolders.

## What It Does

- Reads `data/csv/Service-pages.csv` (or the first available CSV file in the `data/csv/` folder).
- Maps spreadsheet columns to page content blocks.
- Creates files in this structure:

```text
services/<destination-slug>/<city-slug>-to-<destination-slug>-courier.html
```

- Uses `template.html` as the shared design/layout template for all generated pages.
- Uses `.htaccess` rewrite rules so pages can be accessed with root-style URLs.

## Project Structure

```text
lp/
  .htaccess
  template.html
  scripts/
    generate_service_pages.py
    generate_sitemap.py
    cleanup_services_folders.py
    convert_png_to_webp.py
  data/
    csv/
      Service-pages.csv
  services/
    <destination>/
      <city>-to-<destination>-courier.html
```

## Required Python Dependency

No external Python package is required for CSV-based generation.

## Generate Pages

From the `lp` folder:

```bash
python scripts/generate_service_pages.py
```

Expected output:

```text
Created/updated <N> static HTML page file(s) under: ...\services
```

## Generate Sitemap

From the `lp` folder:

```bash
python scripts/generate_sitemap.py
```

Expected output:

```text
Generated sitemap with <N> URLs: ...\sitemap.xml
```

## Cleanup Services Folders

Use this to remove folders under `services/` that are not present as destination countries in your active CSV:

```bash
python scripts/cleanup_services_folders.py
```

Expected output:

```text
CSV used: ...\data\csv\<file>.csv
Keep folders: <N>
Removed folders: <N>
Remaining folders: <N>
```

## CSV Column Mapping

The generator uses these columns:

- `City`
- `Destination` (fallback to `Country` if `Destination` does not exist)
- `Excerpt`
- `Updated Html content`
- `Related Posts`
- `Coded FAQ`
- `FAQ Schema`
- `Review_Schema`

## URL Format

Public URL format:

```text
/lp/<city>-to-<destination>-courier.html
```

Example:

```text
/ lp / agra-to-austria-courier.html
```

(Without spaces: `/lp/agra-to-austria-courier.html`)

Internal file path for that example:

```text
services/austria/agra-to-austria-courier.html
```

## Rewrite Rules

`.htaccess` maps the public URL to the generated file under `services/<destination>/...`.

If links return 404, verify:

- Apache `mod_rewrite` is enabled.
- `AllowOverride All` is enabled for the site directory.
- `.htaccess` exists in the `lp` folder.

## Important Template Note

Generated files are fully rendered static HTML.

`template.html` uses placeholders such as `{{ORIGIN}}`, `{{DESTINATION}}`, `{{UPDATED_HTML}}`, and schema/meta placeholders.

Do not remove placeholder tokens from `template.html` unless you also update `scripts/generate_service_pages.py`.

## Regeneration Workflow

Whenever CSV data changes:

1. Update `data/csv/Service-pages.csv` (or your active CSV in `data/csv/`).
2. Run `python scripts/generate_service_pages.py`.
3. Run `python scripts/cleanup_services_folders.py`.
4. Run `python scripts/generate_sitemap.py`.
5. Upload updated `services/` files and `sitemap.xml` to server.

## Laravel Deployment

See full guide: `docs/LARAVEL_UPLOAD.md`

For a manual file manager workflow, see: `docs/FILEMANAGER_UPLOAD.md`

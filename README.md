# Service Pages Generator (CSV to PHP)

This project generates courier landing pages from a CSV file and stores them in country-based subfolders.

## What It Does

- Reads `csv/Service-pages.csv` (or the first available CSV file in the `csv/` folder).
- Maps spreadsheet columns to PHP variables.
- Creates files in this structure:

```text
services/<destination-slug>/<city-slug>-to-<destination-slug>-courier.php
```

- Uses `template.php` as the shared design/layout template for all generated pages.
- Uses `.htaccess` rewrite rules so pages can be accessed with root-style URLs.

## Project Structure

```text
lp/
  .htaccess
  template.php
  generate_service_pages.py
  csv/
    Service-pages.csv
  services/
    <destination>/
      <city>-to-<destination>-courier.php
```

## Required Python Dependency

No external Python package is required for CSV-based generation.

## Generate Pages

From the `lp` folder:

```bash
python generate_service_pages.py
```

Expected output:

```text
Created/updated <N> page file(s) under: ...\services
```

## Generate Sitemap

From the `lp` folder:

```bash
python generate_sitemap.py
```

Expected output:

```text
Generated sitemap with <N> URLs: ...\sitemap.xml
```

## Cleanup Services Folders

Use this to remove folders under `services/` that are not present as destination countries in your active CSV:

```bash
python cleanup_services_folders.py
```

Expected output:

```text
CSV used: ...\csv\<file>.csv
Keep folders: <N>
Removed folders: <N>
Remaining folders: <N>
```

## CSV Column Mapping

The generator uses these columns:

- `$Origin` = `City`
- `$Destination` = `Destination` (fallback to `Country` if `Destination` does not exist)
- `$Excerpt` = `Excerpt`
- `$Updatedhtml` = `Updated Html content`
- `$Relposts` = `Related Posts`
- `$FAQ` = `Coded FAQ`
- `$FAQscript` = `FAQ Schema`
- `$Review_Schema` = `Review_Schema`

## URL Format

Public URL format:

```text
/lp/<city>-to-<destination>-courier.php
```

Example:

```text
/ lp / agra-to-austria-courier.php
```

(Without spaces: `/lp/agra-to-austria-courier.php`)

Internal file path for that example:

```text
services/austria/agra-to-austria-courier.php
```

## Rewrite Rules

`.htaccess` maps the public URL to the generated file under `services/<destination>/...`.

If links return 404, verify:

- Apache `mod_rewrite` is enabled.
- `AllowOverride All` is enabled for the site directory.
- `.htaccess` exists in the `lp` folder.

## Important Template Note

Generated files define:

- `$Origin`
- `$Destination`

If `template.php` uses lowercase variables (`$origin`, `$destination`) only, title/meta values can become empty.

Use either:

- uppercase variables in `template.php`, or
- a small normalization block at the top of `template.php`.

## Regeneration Workflow

Whenever CSV data changes:

1. Update `csv/Service-pages.csv` (or your active CSV in `csv/`).
2. Run `python generate_service_pages.py`.
3. Run `python cleanup_services_folders.py`.
4. Run `python generate_sitemap.py`.
5. Upload updated `services/` files and `sitemap.xml` to server.

## Laravel Deployment

See full guide: `docs/LARAVEL_UPLOAD.md`

For a manual file manager workflow, see: `docs/FILEMANAGER_UPLOAD.md`

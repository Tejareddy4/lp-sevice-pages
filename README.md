# Service Pages Generator (Excel to PHP)

This project generates courier landing pages from an Excel file and stores them in city-based subfolders.

## What It Does

- Reads `csv/Service-pages.xlsx` (Sheet1).
- Maps spreadsheet columns to PHP variables.
- Creates files in this structure:

```text
service/<city-slug>/<city-slug>-to-<destination-slug>-courier.php
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
    Service-pages.xlsx
  service/
    <city>/
      <city>-to-<destination>-courier.php
```

## Required Python Dependency

Install once:

```bash
python -m pip install openpyxl
```

## Generate Pages

From the `lp` folder:

```bash
python generate_service_pages.py
```

Expected output:

```text
Created/updated <N> page file(s) under: ...\service
```

## Excel Column Mapping (Sheet1)

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
service/agra/agra-to-austria-courier.php
```

## Rewrite Rules

`.htaccess` maps the public URL to the generated file under `service/<city>/...`.

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

Whenever Excel data changes:

1. Update `csv/Service-pages.xlsx`.
2. Run `python generate_service_pages.py`.
3. Upload updated `service/` files to server.

## Laravel Deployment

See full guide: `docs/LARAVEL_UPLOAD.md`

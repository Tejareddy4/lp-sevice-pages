# File Manager Upload Guide for LP Service Pages

This guide explains how to upload generated LP service pages using a hosting file manager or control-panel file manager.

## Goal

Upload only the static files required to serve the LP pages.

## What You Should Upload

Upload these files/folders:

- `template.html`
- generated service page `.html` files inside `services/<destination>/`
- `.htaccess` (if your server uses Apache rewrites)

Example generated page:

```text
services/austria/agra-to-austria-courier.html
```

## What You Should Not Upload

Do not upload these unless you also want generation scripts in production:

- `csv/`
- `data/csv/`
- `scripts/generate_service_pages.py`
- `scripts/generate_sitemap.py`
- `scripts/cleanup_services_folders.py`
- `scripts/convert_png_to_webp.py`
- legacy `.php` files

## Step by Step

### 1) Generate pages locally

Run this locally:

```bash
python scripts/generate_service_pages.py
```

This creates or updates static `.html` pages in `services/`.

### 2) Open your hosting file manager

Open your provider file manager.

Common examples:

- cPanel File Manager
- Plesk File Manager
- DirectAdmin File Manager
- Hostinger File Manager

### 3) Open the LP directory on server

Go to the deployed LP folder.

Typical path:

```text
public_html/lp/
```

Laravel setup usually:

```text
public/lp/
```

### 4) Upload shared template and routing file

Upload:

- `template.html`
- `.htaccess`

### 5) Upload generated service pages

Upload generated `.html` files while keeping destination folder structure intact.

Example map:

```text
local:  services/austria/agra-to-austria-courier.html
server: public_html/lp/services/austria/agra-to-austria-courier.html
```

### 6) Upload only changed HTML on content updates

For regular updates, upload only:

- changed `.html` pages
- `template.html` only if layout/design changed

### 7) Validate naming and folder structure

Folder names should match destination slugs:

- `services/austria/`
- `services/canada/`
- `services/us/`

File name format:

```text
<city>-to-<destination>-courier.html
```

### 8) Test in browser

Open a deployed page and verify rendering:

```text
/lp/agra-to-austria-courier.html
```

## Quick Checklist

- `template.html` uploaded
- `.htaccess` uploaded (Apache only)
- required `.html` pages uploaded
- pages open without 404
- title/meta/content are visible

## Recommended Workflow

1. Update source CSV locally.
2. Run `python scripts/generate_service_pages.py`.
3. Run `python scripts/generate_sitemap.py`.
4. Upload updated static files.
5. Verify pages in browser.

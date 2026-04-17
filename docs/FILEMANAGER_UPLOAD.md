# File Manager Upload Guide for LP Service Pages

This guide explains how to upload the generated LP service pages using a hosting file manager or control panel file manager.

## Goal

Upload only the PHP files that are needed for the LP service pages, without sending the CSV or generator scripts to production.

## What You Should Upload

Upload these PHP files only:

- `template.php`
- The generated service page `.php` files inside `services/<destination>/`

Example generated page:

```text
services/austria/agra-to-austria-courier.php
```

## What You Should Not Upload

Do not upload these unless you are rebuilding the pages on the server:

- `csv/`
- `generate_service_pages.py`
- `generate_sitemap.py`
- `cleanup_services_folders.py`
- `convert_png_to_webp.py`
- `sample-service-page.php`
- any build or source files that are not required to serve the pages

## Step by Step

### 1) Generate the pages locally

Run the generator on your local machine:

```bash
python generate_service_pages.py
```

This creates or updates the PHP pages under `services/`.

### 2) Open your file manager

Log in to your hosting control panel and open the file manager.

Typical examples:

- cPanel File Manager
- Plesk File Manager
- DirectAdmin File Manager
- Hostinger or similar hosting panel file manager

### 3) Go to the LP folder

Navigate to the folder where the LP pages are hosted.

Common location:

```text
public_html/lp/
```

If this project is inside Laravel, the location is usually:

```text
public/lp/
```

### 4) Upload the shared template

Upload `template.php` into the `lp` folder first.

If `template.php` already exists, replace it only when you want to update the page layout or shared content.

### 5) Upload the generated service pages

Upload the generated `.php` files from the `services/` folder into the same folder structure on the server.

Keep the destination folders intact.

Example upload map:

```text
local:  services/austria/agra-to-austria-courier.php
server: public_html/lp/services/austria/agra-to-austria-courier.php
```

### 6) Upload only the required PHP files

For a normal content update, you only need to upload:

- `template.php`
- the changed generated `.php` service pages

Do not upload CSV files, Python scripts, or other project files unless you are also updating the generator workflow.

### 7) Check file paths and names

Make sure the destination folder name matches the country slug used by the page.

Examples:

- `services/austria/`
- `services/canada/`
- `services/us/`

The file name should stay in this format:

```text
<city>-to-<destination>-courier.php
```

### 8) Test the page in browser

Open one uploaded page in the browser and confirm it loads correctly.

Example:

```text
/lp/agra-to-austria-courier.php
```

## Quick Upload Checklist

- `template.php` is uploaded
- Required generated `.php` pages are uploaded
- Folder names match the destination slugs
- The page opens without a 404 error
- The page title and content render correctly

## Important Note

The generated pages rely on `template.php`.

If you upload only the page file and forget the shared template, the page will not render correctly.

## Recommended Workflow

1. Update the source CSV locally.
2. Run the generator locally.
3. Upload only the updated PHP files.
4. Verify the pages in the browser.

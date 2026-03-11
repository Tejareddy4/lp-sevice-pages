# Laravel Upload and Deployment Guide

This guide explains how to upload and run the generated `lp` pages inside a Laravel application.

## Goal

Serve generated pages like:

```text
https://yourdomain.com/lp/agra-to-austria-courier.php
```

where the real file is:

```text
public/lp/service/agra/agra-to-austria-courier.php
```

## 1) Where to Place Files in Laravel

Copy this entire `lp` folder to Laravel `public` directory:

```text
<laravel-root>/public/lp/
```

Minimum required files/folders inside `public/lp/`:

- `.htaccess`
- `template.php`
- `service/` (generated city folders and pages)
- `csv/` (optional in production)
- `generate_service_pages.py` (optional in production)

## 2) Generate Pages Before Upload

Recommended: generate on local machine, then upload only output.

```bash
python -m pip install openpyxl
python generate_service_pages.py
```

Then upload at least:

- `template.php`
- `.htaccess`
- `service/`

## 3) Apache Requirements

Ensure:

- `mod_rewrite` enabled
- VirtualHost directory allows `.htaccess` overrides (`AllowOverride All`)

Typical Apache check:

```apache
<Directory /var/www/your-laravel/public>
    AllowOverride All
    Require all granted
</Directory>
```

Restart Apache after config changes.

## 4) Laravel `.htaccess` Interaction

Laravel `public/.htaccess` usually sends unknown routes to `index.php`, but existing files are served directly.

Because `lp/.htaccess` rewrites only inside `/lp`, generated URLs continue to work when `lp` is under `public/`.

## 5) Access URLs

- Public URL:
  - `/lp/<city>-to-<destination>-courier.php`
- Example:
  - `/lp/hyderabad-to-us-courier.php`

## 6) Template Consistency Requirement

All generated pages include `template.php`.

Generated files currently define:

- `$Origin`
- `$Destination`

If template logic uses lowercase (`$origin`, `$destination`), align variable names in template to avoid empty SEO/title fields.

## 7) Permissions (Linux Server)

Use readable permissions for web server user:

```bash
find public/lp -type d -exec chmod 755 {} \;
find public/lp -type f -exec chmod 644 {} \;
```

## 8) Nginx Note (If Not Using Apache)

`.htaccess` is ignored by Nginx. Add an equivalent location rule in Nginx config.

Example idea (adapt as needed):

```nginx
location ~ ^/lp/([a-zA-Z0-9-]+)-to-([a-zA-Z0-9-]+)-courier\.php$ {
    rewrite ^/lp/([a-zA-Z0-9-]+)-to-([a-zA-Z0-9-]+)-courier\.php$ /lp/service/$1/$1-to-$2-courier.php last;
}
```

## 9) Quick Verification Checklist

- `public/lp/.htaccess` exists
- `public/lp/template.php` exists
- `public/lp/service/<city>/<city>-to-<destination>-courier.php` exists
- Open one URL in browser and confirm page renders
- Confirm title/meta values are populated

## 10) Update Process

When sheet content changes:

1. Regenerate pages locally.
2. Upload updated `service/` files.
3. Upload `template.php` if design changed.
4. Purge cache/CDN if enabled.

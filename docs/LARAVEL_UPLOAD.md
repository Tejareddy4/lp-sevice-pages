# Laravel Upload and Deployment Guide

This guide explains how to upload and run the generated `lp` pages inside a Laravel application.

## Goal

Serve generated pages like:

```text
https://yourdomain.com/lp/agra-to-austria-courier.html
```

where the real file is:

```text
public/lp/services/austria/agra-to-austria-courier.html
```

## 1) Where to Place Files in Laravel

Copy this entire `lp` folder to Laravel `public` directory:

```text
<laravel-root>/public/lp/
```

Minimum required files/folders inside `public/lp/`:

- `.htaccess`
- `template.html`
- `services/` (generated destination folders and pages)
- `data/csv/` (optional in production)
- `scripts/` (optional in production)

## 2) Generate Pages Before Upload

Recommended: generate on local machine, then upload only output.

```bash
python -m pip install openpyxl
python scripts/generate_service_pages.py
```

Then upload at least:

- `template.html`
- `.htaccess`
- `services/`

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
  - `/lp/<city>-to-<destination>-courier.html`
- Example:
  - `/lp/hyderabad-to-us-courier.html`

## 6) Template Consistency Requirement

All generated pages are pre-rendered from `template.html` during generation.

If you change placeholders in `template.html`, keep `scripts/generate_service_pages.py` in sync.

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
location ~ ^/lp/services/([a-zA-Z0-9-]+)-to-([a-zA-Z0-9-]+)/?$ {
  rewrite ^/lp/services/([a-zA-Z0-9-]+)-to-([a-zA-Z0-9-]+)/?$ /lp/services/$2/$1-to-$2-courier.html last;
}
```

## 9) Quick Verification Checklist

- `public/lp/.htaccess` exists
- `public/lp/template.html` exists
- `public/lp/services/<destination>/<city>-to-<destination>-courier.html` exists
- Open one URL in browser and confirm page renders
- Confirm title/meta values are populated

## 10) Update Process

When sheet content changes:

1. Regenerate pages locally.
2. Upload updated `services/` files.
3. Upload `template.html` if design changed.
4. Purge cache/CDN if enabled.

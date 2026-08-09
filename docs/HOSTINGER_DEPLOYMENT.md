# Hostinger Deployment Guide

This guide addresses why Hostinger was directing/redirecting your domain to GitHub instead of serving the website, and explains how to set up automated deployments.

---

## 1. Why Hostinger Was Directing to GitHub (And How It's Fixed)

When deploying Laravel on Hostinger shared hosting:

1. **Root Web Directory Mismatch**: Hostinger's web server serves files from the `public_html` root folder by default. Laravel requires requests to be handled by `public/index.php`. Previously, the root `.htaccess` blocked access (`Require all denied`), causing Hostinger to return HTTP 403 or fall back to Hostinger's holding/parking page with links to GitHub.
2. **Fixed in Repository**: The root `.htaccess` has been updated to automatically route all web requests directly to `public/` while strictly keeping `.env`, `storage`, `vendor`, and source files secure.

---

## 2. Option A: Automated GitHub Actions Deployment (Recommended)

Whenever you `git push` to your GitHub repository (`main` or `master` branch), GitHub Actions will automatically compile your Vite assets, install production PHP packages, and upload the built site directly to Hostinger.

### Step 1: Get FTP Credentials from Hostinger
1. Log in to **Hostinger hPanel**.
2. Go to **Websites** -> Select your domain -> **Files** -> **FTP Accounts**.
3. Copy your **FTP Host (Server)**, **FTP Username**, and **Password**.

### Step 2: Add Secrets to GitHub Repository
1. Open your repository on GitHub: `https://github.com/carataoaljun/e-clearance`.
2. Go to **Settings** -> **Secrets and variables** -> **Actions**.
3. Click **New repository secret** and add the following three secrets:
   - `HOSTINGER_FTP_SERVER` (e.g. `ftp.yourdomain.com` or IP address)
   - `HOSTINGER_FTP_USERNAME` (e.g. `u123456789`)
   - `HOSTINGER_FTP_PASSWORD` (your FTP password)

### Step 3: Trigger Deployment
- Push your changes to GitHub:
  ```bash
  git add .
  git commit -m "Configure Hostinger deployment"
  git push origin main
  ```
- Check the **Actions** tab on GitHub to monitor progress.

---

## 3. Option B: Hostinger hPanel Git Deployment Tool

If you prefer using Hostinger's built-in Git deployment tool:

1. Open **Hostinger hPanel** -> **Websites** -> **Git**.
2. Create a new repository connection:
   - **Repository**: `https://github.com/carataoaljun/e-clearance.git`
   - **Branch**: `main` or `master`
   - **Directory**: `public_html`
3. Copy the **Auto Deployment Webhook URL** provided by Hostinger.
4. Open GitHub -> Repository **Settings** -> **Webhooks** -> **Add webhook**.
5. Paste the URL into **Payload URL** and set Content type to `application/json`.
6. Whenever you push to GitHub, Hostinger will automatically fetch the latest code.

---

## 4. Environment (.env) Setup on Hostinger

Create a `.env` file in your Hostinger `public_html` root folder (or copy `.env.production.example`):

```env
APP_NAME="e-Clearance System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_eclearance
DB_USERNAME=u123456789_user
DB_PASSWORD=your_secure_password
```

Run migrations and generate the application key via Hostinger SSH terminal:
```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Remove diagnostic and setup scripts

The only PHP file allowed inside `public_html/public` is Laravel's
`index.php`. Delete `public_html/public/info.php`, `phpinfo.php`, database
connection tests, account-creation helpers, and other one-off setup scripts.
They must not remain on the production server, even when their current query
fails. The supplied `.htaccess` files return 404 for directly requested PHP
scripts, and `.user.ini` disables browser error display as defense in depth.

After deploying, verify that `/info.php`, `/phpinfo.php`, and
`/public/info.php` return 404. PHP may cache `.user.ini` changes for several
minutes, but the `.htaccess` denial should take effect immediately.

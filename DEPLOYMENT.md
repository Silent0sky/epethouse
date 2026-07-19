# Deploying Pet House

This is a Core PHP application — no build step, no Node, no Composer required.
It needs: **PHP 8.1+ with PDO/MySQL extension**, and **MySQL 8 or MariaDB 10.4+**.

## 1. Local testing (XAMPP / MAMP / Laragon)

1. Copy this whole `pet-house/` folder into your server's web root
   (e.g. `htdocs/php-pethouse/`).
2. Start Apache + MySQL.
3. Open `config/config.php` and confirm `DB_USER` / `DB_PASS` match your
   local MySQL (XAMPP default: user `root`, empty password).
4. Visit `http://localhost/php-pethouse/setup.php` in your browser **once**.
   This creates the database, imports the schema, and seeds demo accounts.
5. **Copy the admin password shown on that page — it's generated randomly
   and only shown once.** The customer/groomer/delivery demo accounts use
   `password123` (see the page for the full list).
6. Log in at `http://localhost/php-pethouse/login.php`.

## 2. Going live on a real server / hosting provider

1. Upload the folder to your host (via FTP, git, or your host's file
   manager). Point your domain's document root at this folder.
2. Create a MySQL database and a dedicated MySQL user (don't use `root`
   in production) through your host's control panel.
3. Edit `config/config.php`:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` → your real database
     credentials.
   - `APP_URL` → your real domain, e.g. `https://petshop.example.com`
     (no trailing slash). This also switches the app into "production
     mode" — hides PHP errors from visitors and enables secure cookies.
4. Make sure your site is served over **HTTPS** (most hosts provide free
   SSL via Let's Encrypt / AutoSSL) — login sessions rely on this for the
   `secure` cookie flag to take effect.
5. Visit `https://yourdomain.com/setup.php` once. Save the generated
   admin password immediately, then log in and change it from
   **Profile → Change Password**.
6. **Delete or rename `setup.php`** once you've confirmed the site works.
   It's guarded against accidental re-runs, but there's no reason to leave
   an installer reachable on a live store.
7. Change the passwords on (or delete) the demo customer/groomer/delivery
   accounts before real customers start using the site — they ship with
   the shared password `password123` for easy testing only.
8. Set correct file permissions on `assets/uploads/` (needs to be
   writable by the web server user) for pet photos, profile photos, and
   delivery proof-of-delivery images to upload correctly.

## 3. Day-to-day admin

- Admin dashboard: `/admin/dashboard.php` — manage products, bookings,
  orders, coupons, users, blog posts, FAQs, adoption listings, and store
  settings.
- Reset a user's password anytime from **Admin → Users → Reset Password**.
- Back up your database regularly (`mysqldump`) — there is no built-in
  automated backup.

## What's included

- `admin/`, `customer/`, `groomer/`, `delivery/` — role-specific dashboards
- `ajax/` — 4 JSON endpoints (cart, wishlist, notifications)
- `includes/` — shared auth, DB helpers, header/footer/sidebar partials
- `database/database.sql` — full schema + reference data (categories,
  services, FAQs, etc. — no fake customer orders)
- `assets/` — CSS, JS, uploads folder
- `README.md` — full feature list and architecture notes

See `README.md` for the complete feature breakdown.

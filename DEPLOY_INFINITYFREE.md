# Deploying the School Management System to InfinityFree

This project is plain PHP + HTML + CSS + MySQL/MariaDB. No Composer, Node, or
build step is required. It works on PHP 8.1+.

---

## 1. Files and folders to upload

Upload the contents of this project into InfinityFree's **`htdocs/`** directory
(not the account root). Use the online File Manager or FTP.

Upload:

```
.htaccess
index.php
login.php
logout.php
hr.php
registrar.php
hod.php
lecturer.php
student.php
db.php
functions.php
config.php          <- edit with InfinityFree credentials first (see step 3)
config.example.php  <- optional template; safe to omit on the server
assets/style.css
uploads/.htaccess
uploads/<existing images>   (optional; keep a.png etc. for seeded avatars)
database/student_management.sql   (only needed to import; can delete afterwards)
```

Do **not** upload local junk such as `.git/`, temporary files, editor backups,
or any `__mk*.php` helper scripts.

> `.htaccess` is a hidden file. Make sure your FTP client shows hidden files, or
> recreate it from this project if it is missing after upload.

---

## 2. Create the database and import the dump

1. Log in to the InfinityFree Control Panel for your account.
2. Open **MySQL Databases** and create a database, e.g. `school`.
   InfinityFree prefixes names, giving you something like `if0_12345678_school`.
3. Note these four values from the panel:
   - **MySQL Host**: `sqlXXX.infinityfree.com`
   - **Database name**: `if0_XXXXXXXX_school`
   - **Username**: `if0_XXXXXXXX`
   - **Password**: (the password you set)
4. Open **phpMyAdmin** for that database.
5. Select the database in the left sidebar.
6. Go to the **Import** tab, choose `database/student_management.sql`, keep
   character set **utf-8**, and click **Go**.
7. Confirm all four tables appear: `users`, `modules`, `module_assignments`,
   `marks`.

The dump is phpMyAdmin-safe: it contains **no** `CREATE DATABASE`, **no** `USE`,
and **no** `DEFINER` clauses, and uses `utf8mb4`.

---

## 3. Configure the server database connection

Edit `config.php` on the server (do not rely on the local values):

```php
define("DB_HOST", "sqlXXX.infinityfree.com");
define("DB_USER", "if0_XXXXXXXX");
define("DB_PASS", "your_mysql_password");
define("DB_NAME", "if0_XXXXXXXX_school");
define("DB_PORT", 3306);
```

Notes:
- Use the **MySQL host** from the panel, not `localhost`.
- Keep `config.php` blocked from web access (the included `.htaccess` does this).
- In the Control Panel, set the PHP version to **8.1 or newer**.
- Enable the free **SSL/HTTPS** certificate for the domain if available.

---

## 4. Test credentials and post-upload checks

Seed accounts (all use password **`1234`** — change these immediately):

| Role | Email |
|------|-------|
| HR | `hr@gmail.com` |
| Registrar | `registrar@gmail.com` |
| HOD | `hod@gmail.com` |

The seeded lecturer (`niyo@example.com`) and students keep whatever passwords
were stored in the database. Create new staff/students from the dashboards if
you do not know them.

### Post-upload checklist

1. Visit `https://your-site.infinityfreeapp.com/` — it should redirect to
   `login.php` (no directory listing, no 403).
2. Log in as `hr@gmail.com` / `1234` — you should land on `hr.php` with the
   "School Management System" banner.
3. Add a lecturer, then log in as Registrar and add a student.
4. Log in as HOD and assign the module to a lecturer.
5. Log in as that lecturer and add a mark; log in as the student and confirm the
   average/pass status shows.
6. Confirm these return **403 Forbidden**:
   - `https://your-site.infinityfreeapp.com/database/student_management.sql`
   - `https://your-site.infinityfreeapp.com/config.php`
   - `https://your-site.infinityfreeapp.com/config.example.php`
7. Confirm an uploaded image still loads from `uploads/`.
8. Click **Logout** (it is a POST button) and confirm you return to login.

---

## Troubleshooting

- **HTTP 500 right after upload** — usually `.htaccess`. Temporarily rename it to
  `.htaccess.bak` to confirm; then re-enable the blocks one at a time. The rules
  here use only `Options -Indexes`, `<FilesMatch>`/`Require`, and `mod_rewrite`
  guarded by `<IfModule>`, which InfinityFree supports.
- **"Database connection failed"** — recheck `DB_HOST`, `DB_USER`, `DB_PASS`,
  `DB_NAME` in `config.php`; the host must be `sqlXXX.infinityfree.com`.
- **Blank page** — set the PHP version to 8.1+ in the panel and check the
  InfinityFree error log.
- **`chk_marks_range` import error on old MySQL** — if the target server does
  not support `CHECK` constraints, remove that single line from the dump before
  importing (the app still validates marks 0–100 in PHP).

## Security reminders

- Change the `1234` passwords for HR, Registrar, and HOD immediately after the
  first login.
- After importing, you may delete `database/student_management.sql` from the
  server (it is also blocked by `.htaccess`, but removing it is safer).
- Keep `config.php` out of version control and never share real credentials.
- Use HTTPS; sessions automatically set the `Secure` cookie flag when HTTPS is
  detected.

# Install & setup

For someone deploying **Pickleball Open Play** on shared hosting (cPanel, Bluehost, SiteGround, etc.) or testing locally with Docker.

**Requirements**

- PHP 8.0+ with the `mysqli` extension
- MySQL 5.7+ or MariaDB 10.3+ (InnoDB)
- No Composer, Node, or SSH required on the host

---

## A. Shared hosting (production)

### 1. Create the database

In your hosting control panel (often **MySQL Databases** or **phpMyAdmin**):

1. Create a database, e.g. `pickleball` (host may prefix it: `user_pickleball`).
2. Create a database user with a strong password.
3. Grant that user **All privileges** on the database.
4. Note: **host**, **database name**, **username**, **password**. Host is usually `localhost`.

### 2. Import the schema

1. Open **phpMyAdmin** → select the database.
2. **Import** → choose [`sql/schema.sql`](sql/schema.sql) → Go.
3. Confirm tables exist: `players`, `sessions`, `session_players`, `slots`, `games`, `slot_sitouts`, `settings`.

### 3. Upload the app files

Upload the project folder contents to your web root or a subfolder, for example:

- Document root: `public_html/`
- Or subfolder: `public_html/pickleball/`

You can use FTP/SFTP or the host’s File Manager. Include everything except local-only junk (`.git` is optional on the server).

### 4. Create `config.php`

On the server (or before upload):

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
'db' => [
    'host' => 'localhost',      // from hosting panel
    'port' => 3306,             // almost always 3306 on shared hosting
    'name' => 'user_pickleball',
    'user' => 'user_dbuser',
    'pass' => 'your-strong-password',
    'charset' => 'utf8mb4',
],
'base_path' => null,            // see step 5
// Friends’ QR / share link — MUST be your real public URL (not localhost)
'public_base_url' => 'https://yourdomain.com',
'default_admin_password' => 'changeme',
```

### 5. Domain for QR codes (important)

`public_base_url` is what gets encoded in the **share QR**. Phones must open a real hostname.

| Deploy | Example `public_base_url` |
|--------|---------------------------|
| Site at domain root | `https://yourdomain.com` |
| App in subfolder | `https://yourdomain.com/pickleball` |
| Local Docker only | leave `''` (falls back to `http://localhost:8080`) |

If this is wrong or left as localhost, friends scanning the QR will get a link that does not work on their phones.

### 6. Subfolder / broken links (CSS & admin)

If the app is at `https://example.com/pickleball/` and CSS or links 404:

```php
'base_path' => '/pickleball',
'public_base_url' => 'https://example.com/pickleball',
```

If the site is at the domain root, leave `'base_path' => null` and set `public_base_url` to `https://yourdomain.com`.

### 7. First login

1. Open `https://your-domain/login.php` (or `/pickleball/login.php`).
2. Password: value of `default_admin_password` in config (default `changeme`), unless you already changed it in Admin.
3. **Dashboard → Change admin password** immediately.
4. **Players** → add names.
5. **New open play** → courts, hours, select players, optional toggles (results / timer).
6. **Generate schedule** → confirm if partner-repeat warning appears.
7. On the session page, use the **Share with friends** QR (or copy link) → then **Open play screen** → **Start open play**.

### 8. Quick checklist

| Step | Done? |
|------|--------|
| DB created + user granted | |
| `schema.sql` imported | |
| `config.php` created from example | |
| DB credentials correct | |
| **`public_base_url` set to live domain** | |
| Files uploaded | |
| `base_path` set if in subfolder | |
| Login works | |
| Admin password changed | |
| QR opens play page on a phone | |

### Common problems

| Symptom | Fix |
|---------|-----|
| Blank page / 500 | Check PHP version ≥ 8; enable `mysqli`; check error log |
| Database connection error | Wrong host/name/user/pass/port in `config.php` |
| CSS missing / wrong links | Set `base_path` to the subfolder path |
| Can’t log in | Use `default_admin_password`; or clear `admin_password_hash` in `settings` table and retry |
| Permission denied writing nothing | App does not need a writable upload dir for normal use |

---

## B. Local Docker (development)

Useful when PHP is not installed on the Mac.

### MySQL on port 3307 (if 3306 is busy)

```bash
docker rm -f pb-mysql 2>/dev/null
docker run --name pb-mysql \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
  -e MYSQL_DATABASE=pickleball \
  -p 3307:3306 \
  -d mysql:8

# Wait ~15s, then:
docker exec -i pb-mysql mysql -u root pickleball < sql/schema.sql
```

### Config for Docker PHP

```bash
cp config.example.php config.php
```

Set in `config.php`:

```php
'host' => 'host.docker.internal',
'port' => 3307,
'name' => 'pickleball',
'user' => 'root',
'pass' => '',
```

### Run PHP

```bash
./docker-php.sh
# → http://localhost:8080/login.php
```

Or:

```bash
docker run --rm -it --name pb-php -p 8080:8080 \
  -v "$(pwd)":/app -w /app php:8.2-cli \
  bash -c "docker-php-ext-install mysqli && php -S 0.0.0.0:8080"
```

---

## C. Day-of usage (short)

1. Admin adds/selects players and creates the open play.
2. Generate schedule (ack partner warning if shown).
3. On setup, show the **QR** (or copy link) so friends can open the play page (view only).
4. Admin opens play screen → **Start open play** → Round 1 is live for everyone.
5. Admin taps **Next round** when ready; friend phones poll and follow (reload) automatically.
6. Only admin can enter scores / control the timer; friends are view-only.

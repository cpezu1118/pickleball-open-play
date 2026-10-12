# Pickleball Open Play

A simple **PHP + MySQL** app for running pickleball open play: reusable players, auto court rotations with fair sit-outs and no-repeat partners, and a phone-friendly **round-by-round play screen** (Swish-style). Designed for **shared hosting** — no Composer, no Node build.

## Features

- **Admin setup** — add/reuse players; create an open play (courts, hours, player list)
- **Scheduler** — 15-minute rounds, fair sit-outs, avoid repeat partners; warn + confirm if repeats are forced
- **Play mode** — host starts and advances rounds; friends open a public view-only link and follow live progress
- **Toggles per open play** — track W–L + points (optional); 15-minute timer + sound (optional, host only)
- **Public link** — share `play.php?session={id}` (no login; view only)

## Docs

| Doc | Purpose |
|-----|---------|
| **[INSTALL.md](INSTALL.md)** | Step-by-step install & setup (shared hosting + local Docker) |
| **[ARCHITECTURE.md](ARCHITECTURE.md)** | How the app is structured (folders, DB, flows) |
| **[config.example.php](config.example.php)** | Copy to `config.php` and fill in DB credentials |
| **[sql/schema.sql](sql/schema.sql)** | Database schema for phpMyAdmin import |

## Quick start (shared hosting)

1. Create a MySQL database + user in your host panel.
2. Import `sql/schema.sql`.
3. Upload this project to `public_html` (or a subfolder).
4. Copy `config.example.php` → `config.php` and set DB host / name / user / pass / port.
5. Set **`public_base_url`** to your live domain (e.g. `https://yourdomain.com`) — this powers the share **QR code**.
6. If the app is in a subfolder, set `base_path` and include that path in `public_base_url`.
7. Open `/login.php` — default password `changeme` — then change it under Admin → Dashboard.
8. Add players → New open play → Generate → share the **QR** → **Start open play**.

Full detail: **[INSTALL.md](INSTALL.md)**.

## Architecture (short)

```text
Admin (setup) ──generate──► MySQL (players, slots, games)
                                ▲
Play UI (play.php) ──host Start/Next/scores──┘
   └─ Friends poll play_state.php and follow live round
```

- **Pages** — thin PHP scripts + HTML forms  
- **includes/** — DB, auth, `Scheduler`, session board helpers  
- **assets/** — CSS + timer/carousel JS  
- **No framework** — `require_once` only  

See **[ARCHITECTURE.md](ARCHITECTURE.md)** for the data model and security notes.

## Local development (Docker)

If PHP is not installed on your machine:

```bash
# MySQL (example: host port 3307)
docker run --name pb-mysql \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
  -e MYSQL_DATABASE=pickleball \
  -p 3307:3306 -d mysql:8
docker exec -i pb-mysql mysql -u root pickleball < sql/schema.sql

cp config.example.php config.php
# set host=host.docker.internal, port=3307, user=root, pass=

./docker-php.sh
# http://localhost:8080/login.php
```

## Default credentials

- Admin password (first bootstrap): `changeme` (from `default_admin_password` in `config.php`)
- Change it immediately after first login

## License / use

Built for a friends open-play group. Use and modify freely for your club.

# Architecture

Plain PHP 8 + MySQL application. No Composer, no framework, no build step. Suitable for shared hosting.

## High-level flow

```text
┌─────────────┐     setup / generate      ┌──────────────────┐
│ Admin pages │ ─────────────────────────► │ MySQL            │
│ (session)   │                            │ players, slots,  │
└──────┬──────┘                            │ games, …         │
       │ Start open play                   └────────▲─────────┘
       ▼                                            │
┌─────────────┐     read board / save scores        │
│ play.php    │ ────────────────────────────────────┘
│ (carousel)  │
└─────────────┘
       ▲
       │ shared link (each device browses rounds locally)
  Friends’ phones
```

## Directory layout

```text
pickleball-open-play/
├── config.example.php    # Template; copy to config.php
├── config.php            # Local/secrets (gitignored)
├── index.php             # Session list; redirects ?session= to play
├── play.php              # Day-of round UI (Swish-style)
├── login.php / logout.php
├── admin/
│   ├── index.php         # Dashboard, password change
│   ├── players.php       # Player CRUD
│   ├── session_new.php   # Create open play + feature toggles
│   ├── session.php       # Generate / reset / Start open play
│   └── results.php       # Bulk score entry (admin)
├── includes/
│   ├── db.php            # mysqli singleton + settings helpers
│   ├── auth.php          # Session login / require_admin
│   ├── helpers.php       # CSRF, urls, flash, formatting
│   ├── Scheduler.php     # Round robin: sit-outs + partnerships
│   ├── session_repo.php  # Load/persist schedule, standings
│   ├── score_save.php    # Shared score update
│   ├── header.php / footer.php
├── assets/
│   ├── app.css           # Admin + play responsive styles
│   └── app.js            # Timer buzzer + round carousel / swipe
├── sql/schema.sql
├── INSTALL.md
└── ARCHITECTURE.md
```

## Layers

| Layer | Responsibility |
|-------|----------------|
| Pages (`*.php`, `admin/*`) | HTTP, forms, CSRF, redirects, HTML |
| `includes/*` | Auth, DB, scheduling, domain helpers |
| MySQL | Persistent players, sessions, slots, games, scores |
| Browser JS | Timer + local round navigation only (no SPA framework) |

## Data model

- **players** — reusable roster (`name`, `active`)
- **sessions** — one open-play day: courts, hours, `track_results`, `timer_enabled`, status
- **session_players** — who is in that day
- **slots** — rounds (15-minute index + offset)
- **games** — per court: `p1/p2` vs `p3/p4`, optional scores / winner
- **slot_sitouts** — who sits that round
- **settings** — e.g. `admin_password_hash`

Scheduling rules (see `Scheduler.php`):

1. Slot count ≈ `hours × 60 / 15`
2. Capacity = `courts × 4`; extras sit out (prefer fewest games so far)
3. Avoid repeating partner pairs across the session when possible
4. If repeats are unavoidable → admin warning → confirm → persist

## Play mode vs admin

| Concern | Admin (logged in) | Friends (public URL) |
|---------|-------------------|----------------------|
| Add players / create session | Yes (admin pages) | No |
| Generate / reset schedule | Yes | No |
| Start open play / Next round | Yes on `play.php` | No |
| View courts / sit-outs | Yes | Yes (view only) |
| Enter scores | Yes if tracking on | No (see scores if entered) |
| Standings | Yes | Yes when tracking on |
| Timer | Host controls | Hidden |

**Live round** is stored on the server (`slots.status = active`). Admin Start / Next / Previous update it. Public viewers poll `play_state.php` about every 4s and reload when the revision (round or scores) changes. Friends may browse other rounds locally; the live badge tracks the host.

**Share / QR:** `public_base_url` in `config.php` builds the canonical play URL for QR codes (`includes/share_card.php`). Always set this to the live domain on hosting — never leave localhost for court day.

## Security notes

- Admin password: `password_hash` / `password_verify` in `settings`
- CSRF tokens on state-changing POSTs
- Prepared statements (`mysqli`) for queries
- `.htaccess` denies HTTP access to `config.php` on Apache
- Public users can view schedule/standings; score writes require admin session

## Feature flags (per session)

- `track_results` — off by default (schedule-only first drop)
- `timer_enabled` — client-side 15:00 countdown + Web Audio beep

## Hosting assumptions

- Document root (or subfolder) points at this project
- `base_path` in config corrects URLs when not at domain root
- No writable `uploads/` directory required for core features

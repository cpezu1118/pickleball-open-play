# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Primary user: a single club/group admin (the project owner) who runs recurring open-play pickleball nights for a known, recurring pool of players at an indoor gym/rec center. Secondary: those players and their friends, who follow the live session as spectators/participants via a shared QR-code link on their phones, plus a shared TV/projector screen at the venue showing the same live view.

## Product Purpose
Runs round-robin open-play pickleball sessions: an admin sets up the player pool and a session (courts, duration, optional score tracking, optional 15-minute round timer), the scheduler assigns rotating courts/partners across rounds while minimizing repeated partnerships and keeping sit-outs fair, and the host drives a live, round-by-round carousel that updates for everyone watching. Success means a session runs itself once started, so the host can focus on hosting instead of managing a spreadsheet, and friends/spectators can follow along live without asking what's happening.

## Positioning
Distinct from a generic scheduling spreadsheet or tournament bracket tool: built specifically around casual, recurring open-play nights (not tournaments/leagues) — round-robin fairness (partner-repeat minimization, sit-out balancing) combined with a live, shareable, QR-accessible spectator view is the mechanism a generic scheduler doesn't offer.

## Operating Context
- Indoor gym/rec center sessions — controlled lighting; a venue-mounted or propped-up TV/projector likely shows the live carousel for the room, while players/friends follow along on their own phones via the QR-shared link.
- Host runs the session live: starts play, advances rounds, enters scores per court.
- Admin (same person, separately, ahead of time) sets up players and sessions.
- Optional 15-minute round timer with an audible buzzer signals round changes.

## Capabilities and Constraints
- Plain PHP 8 + MySQL (mysqli), no framework or build tooling — existing architecture, not to be changed by this redesign.
- Shared layout partials (`includes/header.php`, `includes/footer.php`) drive 7 of 9 page templates; `play.php` is a standalone full-page shell (host/spectator live carousel) outside that shared layout.
- Live sync is poll-based (4s interval, full page reload on change) — not a websocket/real-time push architecture.
- Single admin password (not a multi-account/role system) gates the admin pages.

## Brand Commitments
Renamed during the corkboard redesign: "Pin & Paddle" is the app's name going forward (was the placeholder "Pickleball Open Play").

## Evidence on Hand
No existing logo, brand assets, testimonials, or photography on hand. Nothing to preserve; nothing to fabricate.

## Product Principles
- Built for one recurring group's own open-play nights, not a multi-tenant product for other clubs.
- The live, round-by-round view (on both phone and shared screen) is the product's centerpiece, not an afterthought.
- Fairness (partner rotation, sit-out balance) is a functional guarantee the UI should make visible/legible, not hide.
- Setup (admin) and live hosting are two distinct jobs for the same person at different times — the UI should reflect that split rather than blur it.

## Accessibility & Inclusion
No product-specific requirement established yet.

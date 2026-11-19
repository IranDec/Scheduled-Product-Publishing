# Changelog - Scheduled Product Publishing

All notable changes to this module are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), versions follow
[Semantic Versioning](https://semver.org/).

---

## [1.2.0] - 2026-11-19

### Added
- **Blog articles** of the ST Blog module (`stblog`, Panda / Transformer themes) can be scheduled too.
  New panel *Plan blog articles* on the list page: every article with its state (online / offline), date and
  next planned change; tick articles and plan them
  - **as a series**: one article every day / every 2 or 3 days / every week at a fixed time, from a start date,
    in the order of the *Order* column (a new series continues the day after the last planned article), or
  - **all at the same time**.
  Offline articles that are not planned yet are ticked already; *show as the newest article* sets the article date
  to the publish day, so the blog lists it first.
- Publishing sets the article online in `st_blog` and `st_blog_shop` (the blog reads both), fires
  `actionObjectStBlogClassUpdateAfter` for the blog blocks and clears the template and full-page caches once per run.
- The schedule list shows blog rows with a *Blog* badge, finds them by article title and has a type filter
  (*Products and blog / Products / Blog articles*); run now, change time, cancel and the e-mail report work for
  articles too.

### Changed
- Column `type` (`product` / `blog`) in the schedule table; the upgrade adds it and keeps every existing schedule
  as a product schedule. Product queries only read product rows.

---

## [1.1.0] - 2026-11-16

### Added
- **Cron schedule builder** on the settings page: each shop chooses how often the cron job runs
  (every minute, 5 / 10 / 15 / 30 minutes, hourly at a chosen minute, once a day at a fixed time,
  or a custom cron expression) and how it calls the module (curl, wget or the PHP command line).
  The crontab line to copy updates live, with a note on how late products can change with that
  choice. "Save my cron schedule" stores the choice; the module cannot edit the server's crontab.
- The cron status ("Cron job active") now follows the chosen interval - an hourly or daily cron
  no longer shows "no cron job detected" between two runs - and names the schedule.
- **Confirmation popup** in the module's style replaces the browser's confirm() dialogs (create a
  new cron URL, run now, cancel, delete, bulk actions, test e-mail); destructive actions are shown
  in red. Keyboard: Enter / Esc, focus stays in the popup.
- **New report e-mail design** (en / de): summary tiles (executed / done / failed), coloured action
  and status pills, a button to the schedule list, and a footer with the module name, the module
  version (filled in automatically) and a link to adschi.com.
- **"Send a test report e-mail"** button on the settings page (example data, marked [TEST]).
- The command-line crontab line uses the PHP binary of the shop's PHP version when it can be found.
- Banner (`docs/banner.png`, 1280 x 640, and `@2x`; source `docs/banner.html`, version via `?v=`) shown at the top of the README.
- Result messages ("Published", "Already online", "Product not found", ...) are shown in the
  back-office language and the report language instead of always English (stored text unchanged).

### Upgrade
- `upgrade/upgrade-1.1.0.php` adds the new settings with defaults (every minute, curl); existing
  settings and schedules are kept.

---

## [1.0.0] - 2026-11-16

First release.

### Added
- **Per product**: "Scheduled publishing" panel in the product editor (Modules tab) - publish or
  unpublish at a date and time, optional opposite action later ("online from Monday 08:00 until
  Sunday 23:59"), optional "show as new product" from the publish time, quick picks
  (+1 h, tomorrow 08:00, Monday 08:00), upcoming list with Run now / Cancel, history.
- **List page** (Catalog > Scheduled publishing): every schedule with status tabs (waiting, done,
  failed, cancelled, all), search by product name / reference / ID, action filter, pagination,
  change time, run now, cancel, delete, bulk cancel / delete, and a bulk "Add schedule" form with
  a product picker (search) and a field for pasted product IDs (up to 500 products at once).
- **Runner**: cron URL with a secret token, command-line script (`cron_cli.php`), and a fallback
  that runs due schedules after a shop or back-office page was delivered (at most once a minute).
  One run at a time (MySQL named lock); rows are claimed before execution; interrupted runs are
  retried (3 tries).
- Products are changed through `Product::update()` in the target shop's context (hooks, caches and
  search index behave like a manual save), with a status-only fallback for products whose data
  does not validate.
- **Multistore**: schedule for every shop of the product or for chosen shops; lists follow the
  shop context; employees only see and change schedules of shops they have access to.
- **Settings**: run on visits, e-mail report after each run (en / de templates), history clean-up
  (days), changes per run, cron URL / crontab lines with copy buttons, "create a new cron URL".
- Status overview: waiting / next change / last run / failed, and a warning when no cron job is
  detected.
- Translations: English (source) and German; all texts can be translated in
  International > Translations > Installed modules translations.
- Security: every change is a POST with a per-employee token signed with the shop's secret key
  (PrestaShop 9's randomized admin tokens cannot be compared; a form key protects the settings page),
  permission checks (this page and Catalog > Products, view / edit), shop access checks, strict
  date validation, escaped output everywhere, cron token compared in constant time, `index.php`
  guards in every folder.

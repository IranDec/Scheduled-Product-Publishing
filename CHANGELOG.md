# Changelog - Scheduled Product Publishing

All notable changes to this module are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), versions follow
[Semantic Versioning](https://semver.org/).

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

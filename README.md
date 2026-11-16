# Scheduled Product Publishing

**PrestaShop module - publish or unpublish products automatically at the date and time you choose.**

Plan a product launch for Monday 08:00, take a seasonal product offline on Sunday night, or put
a whole collection online at once - per product, in bulk, and per shop.

| | |
|---|---|
| Module name | `productscheduler` |
| Version | 1.0.0 (see [CHANGELOG.md](CHANGELOG.md)) |
| PrestaShop | 1.7.x, 8.x, 9.x |
| PHP | 5.6 - 8.4 (whatever your PrestaShop version supports) |
| Tested on | PrestaShop 1.7.8.11 (PHP 7.4) and 9.0.3 (PHP 8.4), single shop and multistore |
| Multistore | yes |
| Languages | English, German (more via PrestaShop's translation screen) |
| Author | Mohammad Babaei - [adschi.com](https://adschi.com) |
| License | MIT |

---

## Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Set up the cron job](#set-up-the-cron-job)
4. [Usage](#usage)
5. [Multistore](#multistore)
6. [Settings](#settings)
7. [Translations](#translations)
8. [Permissions](#permissions)
9. [Security](#security)
10. [How it works](#how-it-works)
11. [Troubleshooting](#troubleshooting)
12. [Uninstall](#uninstall)
13. [Files](#files)

---

## Features

- **On the product page** (product editor > *Modules* tab > *Scheduled Product Publishing*):
  - publish (set online) or unpublish (set offline) at a date and time;
  - optional opposite action later, e.g. *online from 1 Dec 08:00, offline again on 24 Dec 23:59*;
  - optional *show as a new product from the publish time* (sets the product's creation date, so it
    appears under "New products");
  - quick picks: *+1 h*, *tomorrow 08:00*, *Monday 08:00*;
  - live state of the product (online / offline, per shop), the upcoming schedules with
    *Run now* and *Cancel*, and the history with the result of every run.
- **List page** (*Catalog > Scheduled publishing*):
  - every schedule with tabs *Waiting / Done / Failed / Cancelled / All*;
  - search by product name, reference or ID, filter by action, 20-300 per page;
  - change the time, run now, cancel, delete history entries, bulk cancel / delete;
  - **bulk scheduling**: search and pick products, or paste a list of product IDs (up to 500 at once).
- **Reliable runner**: cron URL, command-line script, and a fallback that works without any cron
  job. One run at a time, interrupted runs are retried.
- **E-mail report** after each run (optional).
- **Status overview** everywhere: how many schedules are waiting, the next change, the last run,
  failures, and a warning when no cron job is detected.

---

## Installation

1. Get a ZIP whose top folder is `productscheduler/`. With GitHub's *Code > Download ZIP*, rename
   the extracted folder `Scheduled-Product-Publishing-main` to `productscheduler` and zip it again
   (PrestaShop needs the folder name to match the module name).
2. Back office > *Modules > Module Manager* > *Upload a module* > choose the ZIP.
   (Or copy the `productscheduler` folder into `/modules/` and click *Install*.)
3. Click *Configure* and set up the cron job (next section).

On install the module:

- creates the table `PREFIX_productscheduler`,
- adds the menu entry *Catalog > Scheduled publishing*,
- registers the hooks `displayAdminProductsExtra`, `actionAdminControllerSetMedia`,
  `actionFrontControllerSetMedia` and `actionProductDelete`,
- creates a random cron token.

Nothing changes in the shop's front office.

---

## Set up the cron job

Without a cron job the module still works: due schedules run after the next page of the shop or the
back office was delivered. On a quiet shop that can be minutes later than planned. **For exact
times, add a cron job that runs every minute.** The module settings page shows ready-to-copy lines.

**Option A - URL** (works everywhere, also with hosting panels and online cron services):

```cron
* * * * * curl -fsS "https://your-shop.com/module/productscheduler/cron?token=YOUR_TOKEN" >/dev/null 2>&1
```

Without friendly URLs the URL looks like
`https://your-shop.com/index.php?fc=module&module=productscheduler&controller=cron&token=YOUR_TOKEN`.
It answers with a short JSON summary, e.g. `{"ok":true,"processed":2,"done":2,"failed":0,...}`.

**Option B - command line** (no web request, no token; run it as the user that owns the shop files):

```cron
* * * * * php /path/to/shop/modules/productscheduler/cron_cli.php >/dev/null 2>&1
```

Running the cron more often than once a minute is not useful; running it less often (e.g. every
5 minutes) is fine - schedules then run up to 5 minutes late.

---

## Usage

### One product

1. Open the product in *Catalog > Products*.
2. Go to the *Modules* tab (PrestaShop 1.7: *Modules* tab at the top of the product form; 8 / 9:
   *Modules* tab) and choose *Scheduled Product Publishing*.
3. Choose **Publish** or **Unpublish**, a date and time, optionally the second date and the shops,
   and click **Add schedule**.

The panel saves immediately - you do **not** need to save the product. Its fields are not part of
the product form, so saving the product never changes your schedules.

### Many products

*Catalog > Scheduled publishing* > **Add schedule**: search products by name / reference / ID and
click them to add them (they appear as chips), and / or paste product IDs separated by commas,
spaces or new lines. Then choose the action, date and shops.

### Times and time zone

All dates are in the shop's time zone (*International > Localization > Configuration > Time zone*),
like every date in PrestaShop. The quick-pick buttons use the shop's clock, not the computer's.
A date must be from the current minute on and at most 5 years ahead.

### What "Publish" and "Unpublish" do

They set the product's *Enabled / Online* switch, exactly like the switch in the product editor.
The product is saved the normal way (`Product::update()`), so other modules (cache, search,
feeds, ...) see a normal product save, and the search index is refreshed when a product goes
online. If a product is already in the target state the run is marked *Done - nothing to change*.

---

## Multistore

- **Every shop of the product** (default): the schedule applies to all shops the product is
  assigned to *when it runs* - also shops it was added to after you planned it.
- **Chosen shops**: untick *All shops of this product* and choose the shops. One schedule is
  created per shop, and each shop's online state is changed separately.
- The lists follow the shop context of the back office: in a shop or group context you see the
  schedules of those shops plus the "all shops" schedules; in *All shops* you see everything.
- Employees who do not have access to all shops can only see and change schedules of their own
  shops; "all shops" becomes "my shops" for them.
- The settings and the cron job are global (one runner serves every shop). Any shop's domain can
  be used for the cron URL.

Without multistore none of this is visible.

---

## Settings

| Setting | Default | Meaning |
|---|---|---|
| Also run on visits | Yes | Fallback runner after shop / back-office pages (at most once a minute). Harmless together with a cron job. |
| E-mail after each run | No | Report of published / unpublished products and failures. |
| Report e-mail | (empty) | Recipient; empty = shop e-mail address. |
| Keep history | 180 days | Done, failed and cancelled entries older than this are deleted (daily). 0 = keep forever. |
| Changes per run | 100 | Upper limit per run; the rest follows in the next run. |

The settings page also shows the cron URL and crontab lines (with *Copy* buttons) and a
**Create a new cron URL** button for when the URL was leaked.

---

## Translations

The module uses PrestaShop's classic module translation system, which works the same way in
1.7, 8 and 9:

- English is the source language; German is included (`translations/de.php`).
- Every text (back office, messages, e-mail subject) can be translated or changed in
  *International > Translations > Installed modules translations > Scheduled Product Publishing*.
- E-mail templates are in `mails/en/` and `mails/de/`; for other languages copy `mails/en/` to
  `mails/<iso>/` (or edit them in *International > Translations > E-mail translations*). When the
  shop's default language has no template, the English one is used.

---

## Permissions

- **Super admins** can do everything.
- Other profiles need **both**:
  - access to *Catalog > Scheduled publishing* (*Advanced Parameters > Team > Permissions*) and
  - the same right on *Catalog > Products*:
    *view* to see schedules, *edit* to add, change, cancel, run or delete them.
- PrestaShop gives the new menu entry to the super admin profile (and, in some versions, to the
  installing employee's profile). Grant it to other profiles as needed.

---

## Security

- Every change is a POST request carrying a per-employee CSRF token signed with the shop's secret
  key (`_COOKIE_KEY_`); it changes when the employee's password changes. The settings forms also
  carry a daily form key, on top of PrestaShop's own token in the URL.
- Permission check (this page *and* Catalog > Products, view / edit) and shop-access check on
  every action, also for the product tab's requests.
- All input is validated (strict date format and range, known actions only, integer IDs, limits
  on products per bulk action and on history days); all SQL values are cast or escaped.
- Everything is escaped on output; the JavaScript inserts server data as text only.
- The cron URL needs a 40-character random token, compared in constant time; a wrong token gets
  HTTP 403 and does nothing. The token can be regenerated at any time. The CLI script refuses to
  run from the web.
- Error details go to the PrestaShop log (*Advanced Parameters > Logs*), not to the browser.
- Every folder contains an `index.php` guard against directory listing.

---

## How it works

- Each schedule is one row in `PREFIX_productscheduler`: product, shop (0 = all shops of the
  product), action, time, status (`pending`, `running`, `done`, `failed`, `cancelled`), result
  message, the employee who created it.
- The runner takes a MySQL named lock (`GET_LOCK`), so two runs never overlap, and then claims each
  due row (`pending` -> `running`) before executing it. A row left in `running` by a run that
  died is retried after 15 minutes (3 tries), then marked failed.
- For each target shop the product is loaded in that shop's context and saved with
  `Product::update()`. If PrestaShop refuses to save it (old invalid product data), only the
  status columns are written and `actionProductUpdate` is called; the result says so.
- The earliest waiting time is kept in the configuration (`PRODUCTSCHEDULER_NEXT_DUE`), so the
  visit fallback costs no database query until something is actually due. When it is, the work is
  done after the page was sent to the visitor (`fastcgi_finish_request`).
- When a product is deleted, its schedules are deleted too.

---

## Troubleshooting

| Problem | Check |
|---|---|
| Schedules run late | No cron job: set one up (see above). The status box shows when the cron last called. |
| Status "Failed" | The result column tells why (product deleted, product no longer in that shop, ...). Details of unexpected errors: *Advanced Parameters > Logs*, search for `productscheduler`. |
| Cron URL answers 403 | Wrong / old token - copy the URL again from the settings page. |
| Cron URL answers 503 | The module is disabled. |
| Front office still shows the old state | A full-page cache module may serve old pages; clear it or let it expire. The module saves the product normally, so most cache modules refresh it on their own. |
| No *Scheduled Product Publishing* in the product's Modules tab | The module must be enabled and hooked to `displayAdminProductsExtra` (*Design > Positions*). |
| "You do not have permission" | See [Permissions](#permissions). |

---

## Uninstall

Uninstalling removes the table (all schedules and history), the settings, the cron token and the
menu entry. Products keep their current online / offline state.

---

## Files

```
productscheduler/
├── productscheduler.php                  module class: install, hooks, settings page, shared view data
├── cron_cli.php                          command-line runner
├── config.xml
├── logo.png
├── classes/
│   ├── ProductSchedulerRepository.php    database access
│   ├── ProductSchedulerService.php       validation, shops, creating schedules
│   ├── ProductSchedulerRunner.php        executes due schedules, lock, report e-mail
│   └── ProductSchedulerError.php         user-facing error
├── controllers/
│   ├── admin/AdminProductSchedulerController.php   list page + JSON endpoint of the product tab
│   └── front/cron.php                    cron URL
├── views/
│   ├── templates/admin/                  product_tab, manage, configure, _footer
│   ├── css/admin.css
│   └── js/admin.js
├── mails/{en,de}/productscheduler_report.{html,txt}
├── translations/de.php
├── upgrade/
├── CHANGELOG.md
├── LICENSE
└── README.md
```

---

Developed by **Mohammad Babaei** - [adschi.com](https://adschi.com)

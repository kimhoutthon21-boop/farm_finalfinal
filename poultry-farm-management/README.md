# Poultry Farm Management System

A complete farm management website for a commercial chicken farm (4 houses by default, any number supported).
Built with plain **PHP, MySQL, HTML, CSS and vanilla JavaScript**. No Node, npm, Composer or build step.
Chart.js is included locally in `assets/js/vendor/`, and icons are inline SVG, so it works without internet.

## What it does

- **Two roles:** Admin (your parents) and Farm worker. Permissions are enforced on the server for every page and action.
- **Dashboard:** living chickens, active houses, feed today (bags), deaths today, mortality rate, active cycles. Charts and cards follow a date filter (Today / 7 days / 30 days / This cycle / Custom). Alerts for missing reports and high mortality.
- **Houses & cycles:** House Number, status (Active / Empty / Cleaning / Maintenance), **Add New Cycle**, and close with confirmation. Old cycles are never overwritten or deleted.
- **Daily report:** fast form with a review screen, then confirm. Feed in bags, deaths with a cause, tasks, notes.
- **Exact population:** living = placed + arrivals − removals − deaths. It is *calculated* from saved records (database view `v_cycle_stats`), never stored as a number that can drift. Each report counts once.
- **Corrections:** admin can correct, void or restore a report. Reason required; before/after values, who and when are in the audit log.
- **Feed, Mortality, Finance** pages, formatted Excel workbook exports, audit log, worker accounts, settings (currency, bag weight, thresholds, causes of death, task list).

## Install on Windows with XAMPP

1. Install XAMPP. In the XAMPP Control Panel, **start Apache and MySQL**.
2. Copy the folder `poultry-farm-management` into `C:\xampp\htdocs\`.
3. Open **http://localhost/poultry-farm-management/** in your browser. The setup wizard opens by itself.
4. **Step 1:** MySQL details. In XAMPP the defaults normally work (user `root`, empty password). Press *Create database & continue*. This creates the database, all tables, the four houses and the default settings, and writes `config/config.php`.
5. **Step 2:** choose the administrator name, username and **your own password**. There is no default password anywhere. After this the setup page locks itself.
6. Log in. Go to **Workers** to add worker accounts and choose which houses each can use. Go to **Flock Cycles → Add New Cycle** to start the first cycle.

### Manual alternative (phpMyAdmin)
Import `database/schema.sql`, then `database/seed.sql`. Copy `config/config.example.php` to `config/config.php`, edit the database details, then open the site: the wizard will ask only for the administrator.

### Requirements
PHP 8.0 or newer (XAMPP 8.x), MySQL 5.7+ or MariaDB 10.3+ (XAMPP's MariaDB is fine). PDO MySQL is on by default in XAMPP.

## Everyday use

- **Workers:** log in, tap **Daily Report**, pick the house, enter feed bags and deaths, tick the work done, **Review report**, then **Confirm and save**.
- **Admins:** the dashboard shows the whole farm. Open a house or cycle for its history. **Report History** lets you search, filter, export and correct reports.
- Switch between English and Khmer with the language control in the top bar (or on the login/setup screen). Your choice is saved in this browser for one year.
- The first time a person logs in with a password set by an admin, they must choose their own.

## Rules built in (so you know what to expect)

- One daily report per house per cycle per date. A second attempt is refused (the form also warns early).
- Submitting the form twice, refreshing, or tapping twice cannot count deaths twice (one-time form token, database unique key, transaction).
- Deaths cannot exceed living chickens. Negative numbers are rejected. Reports can only go to the house's **active** cycle.
- Workers may back-date a report by up to 2 days (changeable in Settings). Admins may add older ones.
- A house can have only one active cycle (also enforced by the database). Closing a cycle sets the house to *Cleaning*; every finished cycle remains *Closed*.
- Exceptional arrivals or removals are done by an admin on the cycle page, with a reason, and are audited.
- Mortality % = cumulative deaths ÷ chickens placed × 100. Day-1 is the placement date.
- Feed stock is shown only after you record an opening stock count; it is labelled an estimate.
- Finance shows entries only and warns that net result is not profit when entries are missing or unallocated.
- Login is limited to 5 failed tries per username per 15 minutes. Passwords are hashed with `password_hash()`.

## Folder guide

```
index.php, login.php, logout.php, setup.php, dashboard.php, profile.php
houses/ cycles/ reports/ feed/ mortality/ finance/ workers/ settings/   pages
actions/    form handlers (POST only, CSRF-checked)
api/        search suggestions and duplicate-report check
includes/   database, auth, permissions, csrf, population rules, layout
assets/     css, js (Chart.js included), images
database/   schema.sql, seed.sql
config/     config.example.php  (config.php is created on setup and is git-ignored)
```

## Security notes

PDO prepared statements everywhere, escaped output, CSRF tokens on every POST, session ID regenerated at login, HttpOnly + SameSite cookies, server-side role checks, no public registration, `includes/`, `config/` and `database/` blocked by `.htaccess`.
For a real server on the internet: use HTTPS, give MySQL a non-empty password and a dedicated user (not `root`), and keep `debug` false in `config/config.php`.
Backups: export the database from phpMyAdmin regularly.

## Known limitations

- Feed stock is an estimate and depends on you entering purchases.
- Workers have no finance access. There is no per-worker finance permission.
- A report's house cannot be changed by correction (void it and enter a new one). Its date can be.
- English and Khmer interfaces, selectable from the language control. The selection is saved in the browser for one year.
- Finance categories are fixed to the seeded list; edit them in the database if you need more.

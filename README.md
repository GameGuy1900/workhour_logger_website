# Work Hour Logger

A small PHP + MySQL website for logging daily work hours with a description.
Tracks a weekly target of 12 hours; any shortfall in a week is added on top
of the following week's target.

## How the weekly carryover works

- Weeks run Monday–Sunday.
- Week 1's required hours = 12.
- If a week's logged hours are less than required, the shortfall is added
  to the following week's required hours (`12 + shortfall`).
- If a week meets or exceeds its required hours, the next week resets to
  the base 12-hour target (surplus hours are not banked forward).
- A week with zero entries still counts as a full shortfall and carries
  forward, compounding if you skip multiple weeks in a row.
- Any hours logged in a week beyond that week's required hours count as
  **surplus hours**, paid at a configurable rate (€10/hour by default).
  Surplus hours are not carried forward to reduce future targets — the
  carryover rule only ever adds hours for shortfalls, never subtracts
  them for surplus.

The calculation lives in `includes/weeks.php` (`compute_weekly_summaries`)
and is covered by `test/weeks_test.php`. The page shows surplus hours and
pay for the current week, per past week, and as a running total.

## Settings page

Visit `settings.php` (linked from the top of the main page) to edit, without
touching any code:

- **Weekly target hours** — the base hours required per week
- **Surplus pay rate** — euros paid per surplus hour
- **Savings goal** — an optional name and price (e.g. "New headphones, €150").
  Your accumulated surplus pay counts toward this price; the main page shows
  a progress bar and marks it "Paid off!" once your total surplus pay meets
  or exceeds it. Set the price to 0 to hide the goal.

**Changing the weekly target or surplus rate only affects the current week
onward — it never recalculates already-completed weeks.** Each change is
recorded with the date it took effect (`settings_history` table), and every
past week is judged against whatever target/rate was actually active for it
at the time. The Settings page shows a small change history table so you can
see what was in effect when. The savings goal isn't week-based, so it's just
a single current value.

## Requirements

Any DirectAdmin/cPanel-style shared hosting plan with PHP 7.4+ and a MySQL
database — this is the standard setup on Etheron Hosting's web hosting
plans, no Node.js Selector or special modules needed.

## Deploying to Etheron Hosting (DirectAdmin)

1. **Create a MySQL database.** In DirectAdmin, go to *MySQL Management* →
   *Create new Database*. Note the database name, username, and password
   DirectAdmin generates (they'll be prefixed with your account username).
2. **Import the schema.** Open *phpMyAdmin* for that database (link is on
   the MySQL Management page) → *Import* → upload `schema.sql` from this
   repo, or paste its contents into the SQL tab and run it.
3. **Upload the files.** Use DirectAdmin's *File Manager* (or an FTP/SFTP
   client with the credentials from *FTP Management*) to upload every file
   in this repo into `public_html/` (or a subfolder if you want it at
   `yourdomain.com/hours/` instead of the domain root).
4. **Configure credentials.** Copy `config.sample.php` to `config.php`
   (via File Manager: duplicate, then rename) and fill in `DB_NAME`,
   `DB_USER`, `DB_PASS` from step 1. `config.php` is git-ignored so your
   real credentials never end up in version control.
5. **Visit the site.** Open your domain in a browser — you should see the
   logger UI. Add a test entry to confirm the database connection works.
   Visit `settings.php` to set your weekly target hours, surplus rate, and
   optional savings goal (defaults are 12 hours/week and €10/hour).

### Upgrading an existing deployment

**If you deployed before the settings page existed at all:** re-run
`schema.sql` (safe to run again — it only creates tables if missing) and
upload the new/changed files. Any `WEEKLY_TARGET_HOURS` or
`SURPLUS_RATE_EUR` lines left over in your `config.php` are simply unused
now; remove them or leave them, either is fine.

**If you already have the settings page from before this history feature
existed** (a `settings` table with `weekly_target_hours`/`surplus_rate_eur`
columns on it), migrate your existing values into the new history table
before uploading the new files, so you don't lose them — run this once in
phpMyAdmin's SQL tab:

```sql
CREATE TABLE IF NOT EXISTS settings_history (
    effective_from DATE NOT NULL,
    weekly_target_hours DECIMAL(5,2) NOT NULL,
    surplus_rate_eur DECIMAL(6,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings_history (effective_from, weekly_target_hours, surplus_rate_eur)
SELECT '1970-01-01', weekly_target_hours, surplus_rate_eur FROM settings WHERE id = 1;

ALTER TABLE settings DROP COLUMN weekly_target_hours, DROP COLUMN surplus_rate_eur;
```

Then upload the new files. Your weekly target and surplus rate carry over
unchanged, now editable going forward without touching past weeks.

## Dark mode

The page follows your system's light/dark preference automatically. A
"Dark mode" / "Light mode" button in the top-right corner lets you override
that manually; your choice is remembered in the browser (`localStorage`) and
applies across both pages.

### Optional: password-protect the page

This app has no login of its own. Since anyone with the URL could add or
delete entries, consider using DirectAdmin's *Password Protected
Directories* feature on the folder you uploaded to, which adds an HTTP
Basic Auth prompt in front of the whole site with no code changes needed.

## Local development / testing

Requires PHP with `pdo_sqlite` (for local testing only — production uses
MySQL via `pdo_mysql`).

```bash
php test/weeks_test.php   # unit tests for the carryover logic
php -l index.php          # syntax check any file
```

To run the full app locally without a MySQL server, temporarily point
`includes/db.php` at a SQLite file instead of MySQL, then run:

```bash
php -S 127.0.0.1:8000
```

## File structure

```
index.php            Main page: log form, current week progress, history, entries
settings.php          Edit weekly target hours, surplus rate, savings goal
add_entry.php          Handles the "log a day" form submission
delete_entry.php        Handles deleting an entry
includes/db.php          PDO/MySQL connection
includes/weeks.php       Pure weekly carryover calculation logic, effective-dated settings lookup
includes/settings.php    Reads/writes the goal row and settings history
style.css                  Styling, including light/dark theme variables
theme.js                   Dark mode toggle button behavior
schema.sql                MySQL table definitions (entries, settings, settings_history)
config.sample.php         Template for config.php (create your own, see above)
style.css                  Styling
test/weeks_test.php        Unit tests for includes/weeks.php
```

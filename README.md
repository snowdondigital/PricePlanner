# PricePlan

A dependency-free PHP 8 and MariaDB/MySQL product pricing planner.

## Deployment

1. Upload this complete directory to the Apache web root.
2. Create an empty MariaDB/MySQL database and import `sql/pricing_planner.sql` using your hosting control panel.
3. Edit `config/config.php` with the database host, name, user and password. Set the site name and timezone there if required.
4. Point the domain at this directory and browse to it. Apache must allow `.htaccess` overrides and PHP must have the PDO MySQL extension.
5. Sign in with username `admin` and password `ChangeMeNow!2026`, then immediately reset that password on the Users page.

No Composer, command-line, build, scheduled task, Node.js or environment file is required.

## Updating an existing install

If you already imported an earlier version of the database, run the applicable dated `sql/upgrade_*.sql` files once in date order through phpMyAdmin or your hosting control panel. New installs only need `sql/pricing_planner.sql`.

Fresh installations include a small set of entirely fictional sample products so the pricing views can be evaluated immediately. These records can be edited, archived or replaced through CSV import.

Admins can install future versions from **Updates** in the app. The server needs outbound HTTPS access, write access to the application directory and PHP's Zip extension. `config/config.php` is always preserved.

To publish an update, increment `VERSION` and the version in `update.json`, list any new SQL files in `update.json` in execution order, commit, then push a matching tag such as `v1.1.0`. The GitHub workflow creates the release and its `priceplan-update.zip` package automatically. Back up the application and database before installing a release that contains migrations.

## CSV mapping

The CSV importer recognizes common pricing spreadsheet headers. Export a worksheet as CSV, open Import, and upload it. Derived columns are deliberately ignored.

Stored inputs are product group, SKU, product name/code, unit and labour costs, target margin, optional preferred override, MSRP, competitor price, retail price excluding VAT, trade discount/price, minimum margin, and wholesale status.

Product groups can be managed on the Settings page and may have their own preferred target margin. Group margins are used as defaults for new products and CSV imports; existing product margins are not overwritten.

Calculated on every request:

- Total Cost = Unit Cost + Labour Cost
- Preferred Sell Price = Total Cost / (1 - Target Margin)
- Retail Price incl. VAT = Retail Price excl. VAT × (1 + VAT Rate)
- Suggested Trade Price = Retail Price excl. VAT × (1 - Trade Discount)
- Actual Trade Discount = 1 - (Actual Trade Price / Retail Price excl. VAT)
- Retail Margin = (Retail Price excl. VAT - Total Cost) / Retail Price excl. VAT
- Trade Margin = (Actual Trade Price - Total Cost) / Actual Trade Price
- Minimum Price = Total Cost / (1 - Minimum Margin)

Blank inputs propagate to blank calculated results, matching the workbook. Target and minimum margins at or above 100%, and division by zero, also produce blank results.

## Security and roles

Passwords use PHP's `password_hash()` and `password_verify()`. State-changing forms use CSRF tokens, queries use native PDO prepared statements, sessions use HTTP-only SameSite cookies, and output is escaped.

- Admin: all actions, settings, users, archive products
- Manager: view/edit products and import/export
- Editor: view/edit products
- Viewer: read-only

Product changes are recorded field-by-field in the audit log. “Archive” is a reversible soft-delete at database level and is restricted to admins.

## Backups and limits

Back up the database through the hosting panel. CSV export includes all active products and all derived values. CSV import creates records; it does not update matching SKUs. The import size is bounded by the host's PHP upload and request limits.

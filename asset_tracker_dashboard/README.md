# Asset Tracker Dashboard

Legacy PHP/MySQL operational dashboard for tents, transportation, RFQs, and motorpool records.

## Security Foundation

- `page_bootstrap.php` protects interactive pages.
- `app_security.php` provides secure sessions, role authorization, and CSRF validation.
- `api_helpers.php` provides consistent JSON responses and prepared-statement helpers.
- `validators.php` provides allowlist and typed input validation.
- `assets/app-security.js` attaches CSRF tokens to forms, `fetch`, and jQuery AJAX requests.

Authorized roles are `ASSET`, `Admin`, and `master_admin`; unrelated authenticated roles are denied access to this module.

## Verification

```powershell
php tests/run.php
Get-ChildItem -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }
```

## Database

Review and apply `database_optimizations.sql` in staging before production. Existing indexes should be checked first because MySQL rejects duplicate index names.

## Deployment Notes

- Keep `.env` outside source control.
- Serve the application over HTTPS.
- Disable `display_errors` in production and review PHP error logs instead.
- Back up the database before applying schema or index changes.

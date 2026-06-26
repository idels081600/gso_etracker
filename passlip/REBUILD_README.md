# E-Pass Slip Rebuild

This folder now contains a modernized app shell beside the legacy role folders.

## Entry Point

Open the rebuilt app at:

```text
/passlip/public/index.php
```

The app uses the existing PHP session values:

- `$_SESSION['username']`
- `$_SESSION['role']`
- `$_SESSION['pay_name']` when available

If no session exists, the rebuilt app shows a sign-in required page that links back to the existing login.

## Structure

- `app/bootstrap.php` loads environment config, sessions, CSRF, helpers, and autoloading.
- `app/Core/Database.php` centralizes prepared `mysqli` queries and transactions.
- `app/Repositories` contains database access for requests, users, and audit logs.
- `app/Services` contains workflow logic for request creation, batch approval, and scanning.
- `app/Controllers` routes server-rendered pages and JSON API actions.
- `app/Views` contains shared role-aware pages and layout.
- `public/assets` contains the shared operational UI CSS and JavaScript.
- `database/rebuild_schema.sql` adds audit logs, export history, and system settings tables.

## Migration

Run `database/rebuild_schema.sql` once against the existing database before using audit-dependent workflows in production.

The rebuild preserves the current source tables:

- `request`
- `logindb`

## Current Coverage

Implemented first:

- Role-aware app shell
- Approver dashboard
- Batch approval and decline flow
- Employee status home
- Employee request creation
- Scanner API and UI
- Tracking view
- Export center links
- Super-admin user list
- Audit log
- CSRF and route guards
- Prepared SQL access layer

Legacy pages remain available while each module is migrated.

# Production readiness tests

Run the complete Version 1 check from PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_production_readiness.ps1
```

The runner clones the current Version 1 database into a temporary `logisys_test_*` database, generates a random per-run password used only inside that clone, starts an isolated PHP server, runs API and browser workflows, writes evidence to `outputs/`, stops the server, and drops the temporary database. It also writes `inventory-ledger-mismatches.csv` when current balances differ from the latest ledger entry.

The suite checks authentication, CSRF protection, IB creation and activation, active-item additions, over-delivery, ADMIN-only inventory posting, monitoring-only office delivery, quantity edits, duplicate submission protection, managed-IB Stock In blocking, reversal behavior, ledger reconciliation, and responsive IB Monitoring layouts.

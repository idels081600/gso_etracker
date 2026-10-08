# LogiSys Version 1 Production Readiness

Generated: 2026-10-08 10:18:02 +08:00

**Decision:** NOT READY

- Passed: 51
- Failed: 1
- Blocking failures: 1
- Test database: isolated clone (removed after the run)

## Results

| Area | Check | Result | Severity | Detail |
|---|---|---|---|---|
| Static | All Version 1 PHP files pass syntax validation | PASS | blocker |  |
| Static | All Version 1 JavaScript files pass syntax validation | PASS | blocker |  |
| Deployment | Internal artifacts are denied by Apache configuration | PASS | blocker | .htaccess protects tmp, outputs, tests, SQL, CSV, logs, and environment files |
| Security | Every write endpoint enforces authentication | PASS | blocker |  |
| Security | Every write endpoint validates CSRF | PASS | blocker |  |
| Data | No negative inventory balances | PASS | blocker | count=0 |
| Data | Inventory stock numbers are unique in current data | PASS | required | duplicate_numbers=0 |
| Data | IB records have no orphan lines | PASS | blocker | orphans=0 |
| Data | IB delivered totals reconcile to posted and reversed deliveries | PASS | blocker | lines=4172, history=4172 |
| Data | Inventory balances match latest ledger entries | FAIL | blocker | mismatches=63; evidence=inventory-ledger-mismatches.csv |
| Authorization | Anonymous IB page access redirects to login | PASS | blocker | status=302 |
| Authorization | Anonymous IB mutations are rejected | PASS | blocker | status=401 |
| Authorization | Non-ADMIN_SAP account is rejected | PASS | blocker | status=403 |
| Authentication | ADMIN_SAP can sign in | PASS | blocker | status=302 |
| UI | IB Monitoring renders for ADMIN_SAP | PASS | required | status=200 |
| UI | IB page exposes valid application data | PASS | blocker | valid JSON |
| Fixtures | Required offices and three catalog items exist | PASS | required | ADMIN, CSWDO, catalog |
| Security | IB mutations reject invalid CSRF | PASS | blocker | status=403 |
| Security | Legacy write endpoint rejects anonymous request | PASS | blocker | status=401 |
| Security | Legacy write endpoint rejects missing CSRF | PASS | blocker | status=403 |
| IB workflow | Create multi-office Draft IB | PASS | blocker | IB TEST-READY-20261008101719 was saved as Draft. |
| IB workflow | Duplicate IB numbers are rejected | PASS | blocker | status=409 |
| IB workflow | Activate Draft IB | PASS | blocker | IB TEST-READY-20261008101719 is now Active. |
| IB workflow | Append an item while IB is Active | PASS | required | 1 item added to ADMIN in IB TEST-READY-20261008101719. |
| Delivery | ADMIN delivery may exceed planned quantity and adds stock | PASS | blocker | status=200, balance=5→13 |
| Delivery | Repeated delivery token cannot post twice | PASS | blocker | status=409 |
| Delivery | Non-ADMIN delivery is monitoring-only | PASS | blocker | status=200, stock_change=0 |
| IB workflow | Edit planned quantity with audit reason | PASS | required | Planned quantity for  Aluminum Tape was updated from 5 to 10. |
| Delivery | Ordinary Stock In rejects managed IB number | PASS | blocker | status=409 |
| Reversal | Whole delivery reversal restores ADMIN stock and preserves original | PASS | blocker | status=200, balance=5 |
| Reversal | A delivery cannot be reversed twice | PASS | blocker | status=409 |
| Browser | ADMIN_SAP login reaches dashboard | PASS | required | http://127.0.0.1:8128/Logi_Sys_Dashboard.php |
| Browser | Logi_inventory.php renders with a CSRF token | PASS | required | status=200, token=true |
| Browser | Logi_transactions.php renders with a CSRF token | PASS | required | status=200, token=true |
| Browser | Logi_app_req.php renders with a CSRF token | PASS | required | status=200, token=true |
| Browser | Logi_manage_office.php renders with a CSRF token | PASS | required | status=200, token=true |
| Browser | Logi_scanner.php renders with a CSRF token | PASS | required | status=200, token=true |
| Browser | Shared browser transport attaches CSRF to legacy AJAX | PASS | required | status=200 |
| Browser | 1440px renders IB Monitoring | PASS | required | records=2 |
| Browser | 1440px defaults IB and office details to minimized | PASS | required | ib_open=0, office_open=0 |
| Browser | 1440px has no page-level horizontal overflow | PASS | required | overflow=0px |
| Browser | 1024px renders IB Monitoring | PASS | required | records=2 |
| Browser | 1024px defaults IB and office details to minimized | PASS | required | ib_open=0, office_open=0 |
| Browser | 1024px has no page-level horizontal overflow | PASS | required | overflow=0px |
| Browser | 390px renders IB Monitoring | PASS | required | records=2 |
| Browser | 390px defaults IB and office details to minimized | PASS | required | ib_open=0, office_open=0 |
| Browser | 390px has no page-level horizontal overflow | PASS | required | overflow=0px |
| Browser | No uncaught browser errors | PASS | required |  |
| Browser | No browser console errors | PASS | required |  |
| Browser | Logi_my_req.php renders for an office account | PASS | required | status=200 |
| Browser | Logi_req.php renders for an office account | PASS | required | status=200 |
| Browser | Requester pages have no uncaught browser errors | PASS | required |  |

## Evidence

- `production-readiness-results.json` - API, workflow, data, and security results
- `browser-smoke-results.json` - responsive browser results
- `inventory-ledger-mismatches.csv` - item-level balance discrepancies
- `ib-monitoring-1440.png`, `ib-monitoring-1024.png`, `ib-monitoring-390.png` - rendered screenshots

TRACKING PRODUCTION UPLOAD PACKAGE

Upload this entire tent_tracking folder into the production
asset_tracker_dashboard folder. Preserve the folder name and included assets
subfolder.

Production location:
- asset_tracker_dashboard/tent_tracking/tracking.php

Open the page at:
- /asset_tracker_dashboard/tent_tracking/tracking.php

The parent asset_tracker_dashboard folder must keep the existing shared PHP
files, styles, images, print pages, export page, and AJAX endpoints used by the
rest of the dashboard.

This folder is the authoritative tent tracking implementation. Do not upload
duplicate tracking.php, tracking.js, update_data.php, update_status_duration.php,
or tracking-redesign assets into the parent asset_tracker_dashboard folder.

Files:
- tracking.php
- tracking.js
- update_data.php
- update_status_duration.php
- tent_status_auto_update_service.php
- run_tent_status_auto_update.php
- setup_tent_status_auto_update_task.ps1
- assets/app-security.js
- assets/app-ui.css
- assets/tracking-redesign.css
- assets/tracking-redesign.js
- assets/white-theme.css

After uploading, run setup_tent_status_auto_update_task.ps1 once as an
administrator on the production Windows server to schedule due-today status
updates every five minutes.

Overdue definition:
- Status is For Retrieval
- Retrieval date is before today

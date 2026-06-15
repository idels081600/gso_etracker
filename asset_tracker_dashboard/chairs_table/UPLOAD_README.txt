CHAIRS & TABLE TRACKING - PRODUCTION UPLOAD PACKAGE

Upload this entire chairs_table folder into the production
asset_tracker_dashboard folder. Preserve the folder name and included assets
subfolder.

Production location:
- asset_tracker_dashboard/chairs_table/tracking.php

Open the page at:
- /asset_tracker_dashboard/chairs_table/tracking.php

=== BEFORE FIRST USE ===
Run setup_tables.sql in your database (phpMyAdmin or MySQL CLI) to create the
required tables:
  - equipment_types (chair colors & table shapes with inventory)
  - deployments (main deployment records)
  - deployment_items (items within each deployment)

=== SEED DATA ===
The SQL file pre-populates equipment_types with:
  Chairs: White (100), Blue (60), Red (40), Black (50)
  Tables: Round (20), Rectangle (25), Square (15)
Adjust inventory quantities as needed after setup.

=== FILES ===
- tracking.php           Main tracking page (UI + form handling)
- tracking.js            JavaScript: auto-update, search, filter, pagination
- update_data.php        AJAX endpoint for all CRUD operations
- update_status_duration.php  Auto-update statuses based on retrieval dates
- equipment_helpers.php  Shared PHP functions for equipment/deployment queries
- setup_tables.sql       Database table creation script
- assets/tracking-redesign.css  UI styles

=== AUTO-UPDATE ===
For auto status transitions (Deployed -> For Retrieval), set up a scheduled
task to hit update_status_duration.php every 5 minutes. The frontend also
polls it every 60 seconds.

=== SIDEBAR ===
Add this link to the sidebar in dashboard_asset_tracker.php:
  <li><a href="chairs_table/tracking.php"><i class="fas fa-chair"></i> Chairs & Table</a></li>

=== KEY DIFFERENCES FROM TENT TRACKING ===
- No individual item numbering (no 1-300 grid)
- Chairs tracked by color, Tables tracked by shape
- Quantities tracked in deployment_items table
- Inventory available_qty updated automatically on deploy/retrieve
# Chairs & Table Tracking System - Plan Overview

## Objective
Build a tracking system for chairs & tables (venue equipment). Unlike tents (which have individual item IDs 1-300), chairs and tables are tracked by **quantity and type** only with no individual item numbering.

## Key Difference from Tent Tracking
| Feature | Tent Tracking | Chairs & Table Tracking |
|---------|--------------|------------------------|
| Individual items | Yes (1-300 numbered tents) | No |
| Tracking method | Per-item status grid | Quantity + type/subtype |
| Item types | Generic "tent" | Chair (by color) · Table (by shape) |

## Equipment Types (Subtypes)

### Chairs (tracked by color)
- White Monoblock Chair
- Blue Monoblock Chair  
- Red Monoblock Chair
- Black Monoblock Chair
- (Add more colors as needed)

### Tables (tracked by shape)
- Round Table
- Rectangle Table
- Square Table
- (Add more shapes as needed)

## Core Tables (Database)
- `equipment_types` - Lookup table (id, category ENUM('Chair','Table'), subtype_name)
- `deployments` - Deployment records (id, requestor, contact, location, address, purpose, status, date, retrieval_date, created_at)
- `deployment_items` - Items within a deployment (id, deployment_id FK, equipment_type_id FK, quantity)

## Workflow
1. User submits a deployment request with:
   - Chair type + quantity (e.g., White Chairs x 10)
   - Table type + quantity (e.g., Round Tables x 3)
   - Location, purpose, date, duration
2. System sets status to "Pending"
3. Status changes: Pending → Deployed → For Retrieval → Retrieved
4. System auto-checks retrieval dates and updates statuses accordingly

## UI Key Features
- **Dashboard Stats**: Total chairs deployed · Total tables deployed · Pending · For Retrieval · Overdue
- **Deploy Modal**: Separate sections for chairs (with color dropdown) and tables (with shape dropdown)
- **Records Table**: Shows "10 White Chairs · 3 Round Tables" format
- **No visual grid**: Subtype selection via dropdown menus instead of numbered boxes
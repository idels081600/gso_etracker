-- Review in staging before applying to production.
-- These indexes support the dashboard, status workflows, and motorpool lookups.

CREATE INDEX idx_tent_status_status ON tent_status (Status);
CREATE INDEX idx_tent_status_request_status ON tent (status, id);
CREATE INDEX idx_tent_date_status ON tent (date, status);
CREATE INDEX idx_vehicle_status ON Vehicle (Status);
CREATE INDEX idx_vehicle_plate_no ON Vehicle (Plate_no);
CREATE INDEX idx_vehicle_records_plate_no ON vehicle_records (plate_no);
CREATE INDEX idx_transportation_plate_status_id ON Transportation (Plate_no, Status, id);
CREATE INDEX idx_transportation_date ON Transportation (Date);
CREATE INDEX idx_motorpool_repair_status_date ON motorpool_repair (status, repair_date);
CREATE INDEX idx_motorpool_repair_plate_no ON motorpool_repair (plate_no);
CREATE INDEX idx_rfq_status_date ON RFQ (Status, date);


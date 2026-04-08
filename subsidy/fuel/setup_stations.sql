-- SQL Setup for Gasoline Stations
-- Run this in the 'subsidy' database

-- Gas stations table
CREATE TABLE IF NOT EXISTS gas_stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_name VARCHAR(100) NOT NULL,
    station_code VARCHAR(20) NOT NULL,
    address VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User-station mapping (links username from logindb to station)
CREATE TABLE IF NOT EXISTS user_stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    station_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES gas_stations(id)
);

-- Add station_id to voucher_claims
ALTER TABLE voucher_claims ADD COLUMN station_id INT NULL;
ALTER TABLE voucher_claims ADD CONSTRAINT fk_claim_station FOREIGN KEY (station_id) REFERENCES gas_stations(id);

-- Insert the 3 gasoline stations
INSERT INTO gas_stations (station_name, station_code) VALUES 
('CALTEX GASOLINE STATION 1', 'CALTEX-1'),
('CALTEX GASOLINE STATION 2', 'CALTEX-2'),
('CALTEX GASOLINE STATION 3', 'CALTEX-3');
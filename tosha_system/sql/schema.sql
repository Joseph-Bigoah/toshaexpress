-- TOSHA EXPRESS Database Schema
CREATE DATABASE IF NOT EXISTS tosha_express;
USE tosha_express;

-- Admin table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'clerk') DEFAULT 'clerk',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Buses table
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_name VARCHAR(100) NOT NULL,
    plate_no VARCHAR(20) UNIQUE NOT NULL,
    color VARCHAR(50) NOT NULL,
    capacity INT DEFAULT 60,
    driver_name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Routes table
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_point VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    fare DECIMAL(10,2) NOT NULL,
    distance_km INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seats table
CREATE TABLE seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    seat_no VARCHAR(10) NOT NULL,
    status ENUM('available', 'booked') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bus_seat (bus_id, seat_no)
);

-- Tickets table
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) UNIQUE NOT NULL,
    passenger_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    from_route VARCHAR(100) NOT NULL,
    to_route VARCHAR(100) NOT NULL,
    bus_id INT NOT NULL,
    seat_no VARCHAR(10) NOT NULL,
    fare DECIMAL(10,2) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    travel_date DATE NOT NULL,
    travel_time TIME NOT NULL,
    status ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
);

-- Parcels table
CREATE TABLE parcels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parcel_no VARCHAR(20) UNIQUE NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20) NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20) NOT NULL,
    from_route VARCHAR(100) NOT NULL,
    to_route VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'in_transit', 'delivered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table (for additional user management)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'clerk') DEFAULT 'clerk',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin
INSERT INTO admins (username, password, role) VALUES ('admin', '0192023a7bbd73250516f069df18b500', 'admin');

-- Insert sample routes
INSERT INTO routes (start_point, destination, fare, distance_km) VALUES
('Nairobi', 'Nakuru', 1500.00, 150),
('Nakuru', 'Eldoret', 1200.00, 200),
('Eldoret', 'Kitale', 800.00, 100),
('Kitale', 'Lodwar', 2000.00, 300),
('Lodwar', 'Kakuma', 500.00, 80);

-- Insert sample buses
INSERT INTO buses (bus_name, plate_no, color, capacity, driver_name) VALUES
('TOSHA EXPRESS 001', 'KCA 001A', 'Yellow', 60, 'John Mwangi'),
('TOSHA EXPRESS 002', 'KCA 002B', 'Red', 60, 'Peter Kimani'),
('TOSHA EXPRESS 003', 'KCA 003C', 'Blue', 60, 'David Ochieng');

-- Generate seats for each bus (60 seats per bus)
DELIMITER //
CREATE PROCEDURE GenerateSeats()
BEGIN
    DECLARE bus_count INT DEFAULT 0;
    DECLARE i INT DEFAULT 1;
    DECLARE j INT DEFAULT 1;
    DECLARE seat_num VARCHAR(10);
    
    SELECT COUNT(*) INTO bus_count FROM buses;
    
    WHILE i <= bus_count DO
        SET j = 1;
        WHILE j <= 60 DO
            SET seat_num = CONCAT('S', LPAD(j, 2, '0'));
            INSERT INTO seats (bus_id, seat_no) VALUES (i, seat_num);
            SET j = j + 1;
        END WHILE;
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL GenerateSeats();
DROP PROCEDURE GenerateSeats;

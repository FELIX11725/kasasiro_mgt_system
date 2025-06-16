-- Roles Table
CREATE TABLE Roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(255) NOT NULL UNIQUE
);

-- Users Table
CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Should be hashed
    role_id INT NOT NULL,
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone_number VARCHAR(20) UNIQUE,
    address TEXT,
    FOREIGN KEY (role_id) REFERENCES Roles(role_id)
);

-- Customers Table
CREATE TABLE Customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    customer_identifier VARCHAR(255) NOT NULL UNIQUE, -- e.g., CUST-00001
    address_latitude DECIMAL(10, 8),
    address_longitude DECIMAL(11, 8),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Drivers Table
CREATE TABLE Drivers (
    driver_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    license_number VARCHAR(255) NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Vehicles Table
CREATE TABLE Vehicles (
    vehicle_id INT PRIMARY KEY AUTO_INCREMENT,
    registration_number VARCHAR(255) NOT NULL UNIQUE,
    model VARCHAR(255),
    capacity DECIMAL(10, 2), -- e.g., in tons or cubic meters
    status VARCHAR(50) DEFAULT 'available' -- e.g., available, in_maintenance, assigned
);

-- Staff Table
CREATE TABLE Staff (
    staff_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    job_title VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- WasteTypes Table
CREATE TABLE WasteTypes (
    waste_type_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    disposal_guidelines TEXT
);

-- CollectionSchedules Table
CREATE TABLE CollectionSchedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    driver_id INT, -- Can be NULL if not yet assigned
    vehicle_id INT, -- Can be NULL if not yet assigned
    collection_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'scheduled', -- e.g., scheduled, completed, cancelled, missed
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id),
    FOREIGN KEY (driver_id) REFERENCES Drivers(driver_id),
    FOREIGN KEY (vehicle_id) REFERENCES Vehicles(vehicle_id)
);

-- CollectedWaste Table
CREATE TABLE CollectedWaste (
    collected_waste_id INT PRIMARY KEY AUTO_INCREMENT,
    schedule_id INT NOT NULL,
    waste_type_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL, -- e.g., in kg or items
    collection_time TIME,
    FOREIGN KEY (schedule_id) REFERENCES CollectionSchedules(schedule_id),
    FOREIGN KEY (waste_type_id) REFERENCES WasteTypes(waste_type_id)
);

-- CustomerQueries Table
CREATE TABLE CustomerQueries (
    query_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'open', -- e.g., open, in_progress, resolved, closed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    response TEXT NULL,
    responded_at TIMESTAMP NULL,
    response_by_user_id INT NULL, -- User who responded (admin/staff)
    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id),
    FOREIGN KEY (response_by_user_id) REFERENCES Users(user_id)
);

-- Notifications Table
CREATE TABLE Notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(255) NULL, -- Optional URL for the notification to link to
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Billings Table
CREATE TABLE Billings (
    billing_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    billing_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- e.g., pending, paid, overdue, cancelled, partially_paid
    description TEXT NULL, -- Description for the invoice, e.g., "Monthly Waste Collection Fee for July 2024"
    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id)
);

-- Payments Table
CREATE TABLE Payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    billing_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL, -- e.g., credit_card, bank_transfer, cash
    transaction_id VARCHAR(255) UNIQUE, -- Can be specific to this payment record
    FOREIGN KEY (billing_id) REFERENCES Billings(billing_id)
);

-- Sample Data for Roles (optional, for initial setup)
INSERT INTO Roles (role_name) VALUES ('admin'), ('customer'), ('driver'), ('staff');

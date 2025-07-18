-- Database structure for Dog Grooming Calendar
-- Execute this file to create the database and tables

CREATE DATABASE IF NOT EXISTS dog_grooming_salon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dog_grooming_salon;

-- Table for storing employees
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('administrator', 'groomer') DEFAULT 'groomer',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for storing clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone)
);

-- Table for storing dogs
CREATE TABLE dogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    breed VARCHAR(100),
    age INT,
    weight DECIMAL(5,2),
    special_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Table for storing services
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT DEFAULT 60,
    price DECIMAL(10,2),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for storing appointments
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    dog_id INT NOT NULL,
    employee_id INT NOT NULL,
    service_id INT,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    service_description TEXT,
    price DECIMAL(10,2),
    notes TEXT,
    added_by_employee_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (added_by_employee_id) REFERENCES employees(id),
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_employee_date (employee_id, appointment_date),
    INDEX idx_client_date (client_id, appointment_date)
);

-- Insert default employees
INSERT INTO employees (name, role) VALUES 
('Administrator', 'administrator'),
('Wiola', 'groomer'),
('Kamila', 'groomer'),
('Beata', 'groomer'),
('Dawid', 'groomer');

-- Insert default services
INSERT INTO services (name, description, duration_minutes, price) VALUES 
('Kąpiel + strzyżenie', 'Podstawowa kąpiel z myciem i strzyżeniem', 90, 80.00),
('Trymowanie', 'Profesjonalne trymowanie sierści', 60, 60.00),
('Pielęgnacja pazurów', 'Obcinanie i pielęgnacja pazurów', 30, 25.00),
('Kąpiel lecznicza', 'Kąpiel z użyciem specjalistycznych szamponów', 45, 50.00),
('Strzyżenie kreative', 'Artystyczne strzyżenie według wzoru', 120, 120.00),
('Pełna pielęgnacja', 'Kompleksowa pielęgnacja: kąpiel, strzyżenie, pazury, uszy', 150, 150.00);

-- Sample data (optional - for testing)
-- Insert sample clients
INSERT INTO clients (name, phone, email) VALUES 
('Jan Kowalski', '123456789', 'jan.kowalski@email.com'),
('Anna Nowak', '987654321', 'anna.nowak@email.com'),
('Piotr Wiśniewski', '555666777', 'piotr.wisniewski@email.com');

-- Insert sample dogs
INSERT INTO dogs (client_id, name, breed, age, weight) VALUES 
(1, 'Burek', 'Mieszaniec', 3, 25.50),
(1, 'Azor', 'Owczarek niemiecki', 5, 32.00),
(2, 'Bella', 'Pudel', 2, 8.50),
(3, 'Rex', 'Labrador', 4, 28.75);

-- Insert sample appointments (for testing client history)
INSERT INTO appointments (client_id, dog_id, employee_id, service_id, appointment_date, start_time, end_time, service_description, price, added_by_employee_id, status) VALUES 
(1, 1, 2, 1, '2024-01-15', '09:00:00', '10:30:00', 'Kąpiel + strzyżenie', 80.00, 1, 'completed'),
(1, 1, 2, 3, '2024-02-20', '14:00:00', '14:30:00', 'Pielęgnacja pazurów', 25.00, 1, 'completed'),
(2, 3, 3, 2, '2024-01-10', '11:00:00', '12:00:00', 'Trymowanie', 60.00, 1, 'completed'),
(1, 2, 4, 6, '2024-03-05', '10:00:00', '12:30:00', 'Pełna pielęgnacja', 150.00, 1, 'completed');
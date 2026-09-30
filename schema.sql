CREATE DATABASE IF NOT EXISTS greencampus
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE greencampus;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS report_status_history;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    contact_placeholder VARCHAR(255) DEFAULT 'Official contact details to be added by college',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_type ENUM('Student','Staff') NOT NULL,
    name VARCHAR(120) NOT NULL,
    department VARCHAR(120) NOT NULL,
    contact VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_name (name)
) ENGINE=InnoDB;

CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_code VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    location_id INT NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
    status ENUM('Pending','In Progress','Resolved') NOT NULL DEFAULT 'Pending',
    assigned_department_id INT DEFAULT NULL,
    resolution_remarks TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_reports_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_reports_location FOREIGN KEY (location_id) REFERENCES locations(id),
    CONSTRAINT fk_reports_department FOREIGN KEY (assigned_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_reports_status (status),
    INDEX idx_reports_category (category_id),
    INDEX idx_reports_location (location_id),
    INDEX idx_reports_priority (priority),
    INDEX idx_reports_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE report_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    old_status VARCHAR(30) DEFAULT NULL,
    new_status VARCHAR(30) NOT NULL,
    remarks TEXT DEFAULT NULL,
    changed_by INT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_admin FOREIGN KEY (changed_by) REFERENCES admin_users(id),
    INDEX idx_history_report (report_id)
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
('Garbage / Waste'),
('Cleanliness'),
('Water Leakage / Water Wastage'),
('Electricity Wastage'),
('Damaged Infrastructure'),
('Unnecessary / Dumped Material'),
('Garden / Green Area'),
('Other Environmental Issue');

INSERT INTO locations (name) VALUES
('Main Gate'),
('Administration Building'),
('CSE Department'),
('Mechanical Department'),
('Electrical Department'),
('Library'),
('Canteen'),
('Hostel'),
('Parking Area'),
('Garden'),
('Playground'),
('Other');

INSERT INTO departments (name) VALUES
('Campus Welfare / Administration'),
('Maintenance'),
('Sanitation / Housekeeping'),
('Electrical Maintenance'),
('Plumbing / Water Maintenance');

-- Demo admin: admin / Admin@123
INSERT INTO admin_users (username, password_hash, full_name)
VALUES ('admin', '$2y$12$lGDfhR626s4P2tkCCggb4O9ECau3yoaYF47zGtmf3n6UdDreVTEp.', 'Campus Administrator');

-- Demo users
INSERT INTO users (reporter_type, name, department, contact) VALUES
('Student','Aarav Patil','CSE','demo.student1@college.example'),
('Staff','Priya Deshmukh','Administration','demo.staff1@college.example'),
('Student','Rohan Shinde','Mechanical','demo.student2@college.example'),
('Student','Sneha More','CSE','demo.student3@college.example'),
('Staff','Vikram Jadhav','Electrical','demo.staff2@college.example');

-- Demo reports. Images are intentionally NULL; live submissions can upload images.
INSERT INTO reports
(report_code,user_id,category_id,location_id,description,image_path,priority,status,assigned_department_id,resolution_remarks,created_at,updated_at)
VALUES
('GC-0001',1,1,8,'Overflowing waste bin near the hostel entrance needs collection.',NULL,'High','Pending',3,NULL,NOW() - INTERVAL 1 DAY,NOW() - INTERVAL 1 DAY),
('GC-0002',2,3,6,'Water tap near the library wash area appears to be leaking continuously.',NULL,'High','In Progress',5,'Plumbing team has been informed for inspection.',NOW() - INTERVAL 3 DAY,NOW() - INTERVAL 1 DAY),
('GC-0003',3,4,3,'Several classroom lights were left switched on after working hours.',NULL,'Medium','Resolved',4,'Lights checked and reminder signage added.',NOW() - INTERVAL 7 DAY,NOW() - INTERVAL 4 DAY),
('GC-0004',4,2,7,'Canteen-side area needs additional cleaning after lunch period.',NULL,'Medium','Pending',3,NULL,NOW() - INTERVAL 5 DAY,NOW() - INTERVAL 5 DAY),
('GC-0005',5,7,10,'Dry leaves and plastic pieces have accumulated in the garden corner.',NULL,'Low','Resolved',3,'Area cleaned and waste segregated.',NOW() - INTERVAL 12 DAY,NOW() - INTERVAL 9 DAY),
('GC-0006',1,5,9,'A damaged section of the parking-area paving may become a trip hazard.',NULL,'High','In Progress',2,'Maintenance inspection scheduled.',NOW() - INTERVAL 2 DAY,NOW() - INTERVAL 1 DAY),
('GC-0007',3,6,2,'Unused material is stacked beside the administration building.',NULL,'Medium','Pending',2,NULL,NOW() - INTERVAL 10 DAY,NOW() - INTERVAL 10 DAY),
('GC-0008',4,8,11,'Additional dustbin points may help keep the playground area clean.',NULL,'Low','Resolved',1,'New bin location reviewed by welfare team.',NOW() - INTERVAL 15 DAY,NOW() - INTERVAL 11 DAY);

-- Generate initial status history using admin id 1.
INSERT INTO report_status_history (report_id, old_status, new_status, remarks, changed_by, changed_at)
SELECT id, NULL, status, resolution_remarks, 1, updated_at
FROM reports;



CREATE DATABASE IF NOT EXISTS electroserve_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE electroserve_db;




-- ============================================================
-- TAXES
-- ============================================================
CREATE TABLE IF NOT EXISTS taxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rate DECIMAL(5,2) NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO taxes (name, rate, status) VALUES
('VAT', 18.00, 'active'),
('Withholding', 5.00, 'active');
-- ============================================================
-- ElectroServe ERP System - Full Database Schema
-- ============================================================


-- ============================================================
-- STAFF / USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_code VARCHAR(20) UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(30),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','logistics','sales','technician','finance') DEFAULT 'sales',
    department VARCHAR(100),
    avatar VARCHAR(255),
    status ENUM('active','inactive','on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin
INSERT INTO staff (staff_code, full_name, email, phone, password, role, department, status)
VALUES ('ADM-001','Admin User','admin@electroserve.com','+1234567890',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','Management','active');
-- password: password

-- ============================================================
-- CLIENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(20) UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    city VARCHAR(100),
    notes TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- INVENTORY - CATEGORIES
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, description) VALUES
('Electrical Supplies','Cables, switches, breakers, wiring accessories'),
('Devices','Appliances, smart devices, equipment'),
('Plumbing Materials','Pipes, fittings, valves'),
('Tools & Accessories','Hand tools, power tools, accessories'),
('Safety Equipment','PPE, helmets, gloves, vests');

-- ============================================================
-- INVENTORY - SUB-CATEGORIES (with profit margin)
-- ============================================================
CREATE TABLE IF NOT EXISTS sub_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    profit_margin DECIMAL(5,2) DEFAULT 20.00 COMMENT 'Profit margin percentage - inherited by all items',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

INSERT INTO sub_categories (category_id, name, profit_margin) VALUES
(1,'Cables & Wires', 20),
(1,'Switches', 15),
(1,'Circuit Breakers', 18),
(1,'Conduits', 20),
(2,'Air Conditioners', 25),
(2,'Generators', 30),
(2,'Smart Meters', 22),
(3,'PVC Pipes', 18),
(3,'Ball Valves', 20),
(3,'Pipe Fittings', 19),
(4,'Hand Tools', 25),
(4,'Power Tools', 28),
(4,'Measuring Tools', 22),
(5,'Helmets', 20),
(5,'Gloves', 15),
(5,'Safety Vests', 18);

-- ============================================================
-- INVENTORY - ITEMS (4-digit format for item codes)
-- ============================================================
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(30) UNIQUE,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    sub_category_id INT,
    unit VARCHAR(30) DEFAULT 'piece',
    cost_price DECIMAL(12,2) DEFAULT 0,
    selling_price DECIMAL(12,2) DEFAULT 0,
    quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 5,
    description TEXT,
    supplier_id INT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (sub_category_id) REFERENCES sub_categories(id) ON DELETE SET NULL
);

INSERT INTO items (item_code,name,category_id,sub_category_id,unit,cost_price,selling_price,quantity,min_quantity) VALUES
('0001','2.5mm Copper Cable (per meter)',1,1,'meter',1.20,2.50,450,50),
('0002','16A Single Switch',1,2,'piece',3.50,7.00,200,20),
('0003','20A Circuit Breaker',1,3,'piece',8.00,16.00,85,15),
('0004','Split AC 1.5 Ton',2,5,'piece',350.00,600.00,12,3),
('0005','3KVA Generator',2,6,'piece',500.00,900.00,5,2),
('0006','50mm PVC Pipe (3m)',3,8,'piece',4.50,9.00,120,20),
('0007','Ball Valve 1/2"',3,9,'piece',6.00,12.00,75,10),
('0008','Digital Multimeter',4,13,'piece',25.00,50.00,18,5),
('0009','Safety Helmet',5,14,'piece',12.00,25.00,23,10),
('0010','Electrical Gloves',5,15,'pair',8.00,18.00,30,10);

-- ============================================================
-- STOCK MOVEMENT (Enhanced with ticket reference)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ticket_id INT COMMENT 'Reference to ticket if used in service',
    type ENUM('in','out','adjustment','ticket_used') NOT NULL,
    quantity INT NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- SUPPLIERS
-- ============================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(20) UNIQUE,
    company_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PURCHASE ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) UNIQUE,
    supplier_id INT,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending','approved','received','cancelled') DEFAULT 'pending',
    notes TEXT,
    staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- ============================================================
-- TECHNICIANS (extends staff)
-- ============================================================
CREATE TABLE IF NOT EXISTS technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNIQUE NOT NULL,
    specialization VARCHAR(200),
    rating DECIMAL(3,2) DEFAULT 5.00,
    total_jobs INT DEFAULT 0,
    status ENUM('active','on_leave','inactive') DEFAULT 'active',
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- ============================================================
-- SERVICE TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS service_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    base_rate DECIMAL(12,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active'
);

INSERT INTO service_types (name, description, base_rate) VALUES
('Electrical Installation','New wiring, panel setup, socket installation',150.00),
('Device Repair','Repair of electrical appliances and devices',80.00),
('Plumbing Service','Pipe installation, leak repair, fitting',100.00),
('Device Supply','Supply and delivery of electrical devices',50.00),
('Electrical Maintenance','Routine checkups and maintenance',120.00),
('Emergency Service','24/7 emergency electrical/plumbing calls',200.00);

-- ============================================================
-- TICKETS (Enhanced workflow with confirmation and time tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE,
    client_id INT NOT NULL,
    service_type_id INT,
    title VARCHAR(255),
    description TEXT,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('pending','assigned','confirmed','ongoing','completed','closed','denied') DEFAULT 'pending',
    technician_id INT,
    assigned_at DATETIME,
    confirmed_at DATETIME COMMENT 'When materials were confirmed by customer',
    started_at DATETIME,
    completed_at DATETIME,
    closed_at DATETIME,
    location TEXT,
    service_cost DECIMAL(12,2) DEFAULT 0,
    material_cost DECIMAL(12,2) DEFAULT 0,
    labor_cost DECIMAL(12,2) DEFAULT 0,
    profit_percent DECIMAL(5,2) DEFAULT 20,
    total_amount DECIMAL(12,2) DEFAULT 0,
    material_confirmed TINYINT DEFAULT 0 COMMENT 'Flag: 0=not confirmed, 1=confirmed',
    time_start DATETIME COMMENT 'Service start time',
    time_end DATETIME COMMENT 'Service end time',
    time_total_minutes INT DEFAULT 0 COMMENT 'Total active service time in minutes',
    notes TEXT,
    denial_reason TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL,
    FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- TICKET ITEMS (materials used)
-- ============================================================
CREATE TABLE IF NOT EXISTS ticket_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    item_id INT,
    item_name VARCHAR(200),
    quantity INT DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

-- ============================================================
-- TICKET TIME TRACKING (Track start/pause/resume events)
-- ============================================================
CREATE TABLE IF NOT EXISTS ticket_time_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    action ENUM('start','pause','resume','stop') NOT NULL,
    action_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    staff_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- TICKET STATUS LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    status VARCHAR(50),
    notes TEXT,
    staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- INVOICES
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE,
    ticket_id INT,
    client_id INT NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_percent DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2) DEFAULT 0,
    type ENUM('invoice','quotation','receipt') DEFAULT 'invoice',
    status ENUM('unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
    due_date DATE,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- PAYMENTS / TRANSACTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) UNIQUE,
    type ENUM('income','expense','payment','refund') NOT NULL,
    category VARCHAR(100),
    amount DECIMAL(12,2) NOT NULL,
    invoice_id INT,
    description TEXT,
    payment_method ENUM('cash','card','transfer','cheque','mobile') DEFAULT 'cash',
    staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- EXPENSES (with updated categories)
-- ============================================================
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category ENUM('marketing','branding','rent','office_consumables','inventory_purchase','salaries','maintenance','transportation','other') DEFAULT 'other',
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    receipt_number VARCHAR(100),
    staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

INSERT INTO expenses (title, category, amount, description, staff_id) VALUES
('Marketing Campaign', 'marketing', 500.00, 'Social media and online advertising', 1),
('Company Branding', 'branding', 1200.00, 'Logo and brand design services', 1),
('Monthly Rent', 'rent', 2000.00, 'Office space rental', 1),
('Office Consumables', 'office_consumables', 150.00, 'Printer ink, paper, stationery', 1),
('Stock Purchase - Cables', 'inventory_purchase', 1200.00, 'Bulk purchase of copper cables', 1),
('Monthly Salaries', 'salaries', 8500.00, 'Staff salaries for the month', 1),
('Vehicle Maintenance', 'maintenance', 350.00, 'Service van oil change and tire check', 1),
('Fuel Expenses', 'transportation', 280.00, 'Weekly fuel for technician vans', 1),
('Office Supplies', 'other', 90.00, 'Miscellaneous office supplies', 1);

-- ============================================================
-- CONTRACTS
-- ============================================================
CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(30) UNIQUE,
    client_id INT NOT NULL,
    ticket_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    agreement_details TEXT,
    start_date DATE NOT NULL,
    end_date DATE,
    terms_and_conditions TEXT,
    amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft','active','completed','terminated','archived') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- ============================================================
-- AI INSIGHTS & PREDICTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('revenue_trend','top_category','expense_analysis','stock_alert','sales_prediction','performance_analysis') DEFAULT 'revenue_trend',
    title VARCHAR(255) NOT NULL,
    insight_text TEXT NOT NULL,
    insight_value DECIMAL(10,2),
    insight_percent DECIMAL(5,2),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- MESSAGES - Updated for client-support chat
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    receiver_id INT,
    ticket_id INT,
    client_id INT,
    message_type ENUM('text','file','system') DEFAULT 'text',
    message TEXT NOT NULL,
    attachment_url VARCHAR(255),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (receiver_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

-- ============================================================
-- NOTIFICATIONS (with read tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    client_id INT,
    type ENUM('ticket','stock','payment','message','system','contract','performance') DEFAULT 'system',
    title VARCHAR(200),
    body TEXT,
    is_read TINYINT DEFAULT 0,
    read_at DATETIME,
    action_url VARCHAR(255),
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- ============================================================
-- SAMPLE DATA - CLIENTS
-- ============================================================
INSERT INTO clients (client_code, full_name, email, phone, address, city) VALUES
('C-1001','John Smith','john@email.com','+1-555-0101','123 Oak Street','Springfield'),
('C-1002','Sarah Johnson','sarah@email.com','+1-555-0102','456 Maple Ave','Riverside'),
('C-1003','James Williams','james@email.com','+1-555-0103','789 Pine Blvd','Lakewood'),
('C-1004','David Miller','david@email.com','+1-555-0104','321 Elm Court','Hillside'),
('C-1005','Emily Davis','emily@email.com','+1-555-0105','654 Cedar Lane','Westview'),
('C-1006','Robert Brown','robert@email.com','+1-555-0106','987 Birch Road','Eastfield'),
('C-1007','Linda Wilson','linda@email.com','+1-555-0107','147 Spruce Way','Northgate');

-- ============================================================
-- SAMPLE DATA - STAFF TECHNICIANS
-- ============================================================
INSERT INTO staff (staff_code, full_name, email, phone, password, role, department, status) VALUES
('TCH-001','John Doe','johndoe@electroserve.com','+1-555-0201','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','technician','Field Operations','active'),
('TCH-002','Mike Brown','mike@electroserve.com','+1-555-0202','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','technician','Field Operations','active'),
('TCH-003','Alex Turner','alex@electroserve.com','+1-555-0203','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','technician','Field Operations','active'),
('TCH-004','Sarah Lee','sarah.t@electroserve.com','+1-555-0204','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','technician','Field Operations','on_leave'),
('SLS-001','Emma Clark','emma@electroserve.com','+1-555-0301','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','sales','Sales','active'),
('FIN-001','Tom Harris','tom@electroserve.com','+1-555-0401','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','finance','Finance','active'),
('LOG-001','Lisa Scott','lisa@electroserve.com','+1-555-0501','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','logistics','Warehouse','active');

-- Technician records
INSERT INTO technicians (staff_id, specialization, rating, total_jobs, status)
SELECT id, 'Electrical & Wiring', 4.8, 45, 'active' FROM staff WHERE staff_code='TCH-001';
INSERT INTO technicians (staff_id, specialization, rating, total_jobs, status)
SELECT id, 'Device Repair', 4.6, 38, 'active' FROM staff WHERE staff_code='TCH-002';
INSERT INTO technicians (staff_id, specialization, rating, total_jobs, status)
SELECT id, 'Plumbing & Electrical', 4.9, 52, 'active' FROM staff WHERE staff_code='TCH-003';
INSERT INTO technicians (staff_id, specialization, rating, total_jobs, status)
SELECT id, 'Device Supply & Install', 4.7, 29, 'on_leave' FROM staff WHERE staff_code='TCH-004';

-- ============================================================
-- SAMPLE DATA - TICKETS
-- ============================================================
INSERT INTO tickets (ticket_number,client_id,service_type_id,title,status,priority,service_cost,material_cost,total_amount,created_by,created_at) VALUES
('TK-1254',1,1,'Electrical Installation - Main Panel',   'pending',  'high',  150,80,  280, 1, NOW()-INTERVAL 0 DAY),
('TK-1253',2,2,'Device Repair - Air Conditioner',         'ongoing',  'medium',80,45,  148, 1, NOW()-INTERVAL 1 DAY),
('TK-1252',3,3,'Plumbing Service - Pipe Leakage',         'ongoing',  'high',  100,60,  192, 1, NOW()-INTERVAL 1 DAY),
('TK-1251',4,5,'Electrical Maintenance - Office',         'completed','medium',120,30,  180, 1, NOW()-INTERVAL 2 DAY),
('TK-1250',5,4,'Device Supply - Smart Meter',             'completed','low',   50,200,  300, 1, NOW()-INTERVAL 2 DAY),
('TK-1249',6,1,'New Wiring - Residential',                'closed',   'medium',150,120, 324, 1, NOW()-INTERVAL 5 DAY),
('TK-1248',7,2,'Generator Repair',                        'closed',   'urgent',80,150,  276, 1, NOW()-INTERVAL 6 DAY),
('TK-1247',1,6,'Emergency Electrical Fault',              'denied',   'urgent',200,0,   0,   1, NOW()-INTERVAL 7 DAY);

-- Assign technicians
UPDATE tickets SET technician_id=2, assigned_at=NOW()-INTERVAL 1 DAY WHERE ticket_number='TK-1253';
UPDATE tickets SET technician_id=3, assigned_at=NOW()-INTERVAL 1 DAY WHERE ticket_number='TK-1252';
UPDATE tickets SET technician_id=1, assigned_at=NOW()-INTERVAL 2 DAY, completed_at=NOW()-INTERVAL 1 DAY WHERE ticket_number='TK-1251';
UPDATE tickets SET technician_id=4, assigned_at=NOW()-INTERVAL 2 DAY, completed_at=NOW()-INTERVAL 1 DAY WHERE ticket_number='TK-1250';
UPDATE tickets SET technician_id=1, assigned_at=NOW()-INTERVAL 5 DAY, completed_at=NOW()-INTERVAL 4 DAY, closed_at=NOW()-INTERVAL 3 DAY WHERE ticket_number='TK-1249';
UPDATE tickets SET technician_id=2, assigned_at=NOW()-INTERVAL 6 DAY, completed_at=NOW()-INTERVAL 5 DAY, closed_at=NOW()-INTERVAL 4 DAY WHERE ticket_number='TK-1248';

-- ============================================================
-- SAMPLE TRANSACTIONS (Revenue)
-- ============================================================
INSERT INTO transactions (reference,type,category,amount,description,payment_method,staff_id,created_at) VALUES
('TXN-001','income','service', 180.00,'Payment for TK-1251','cash',1,NOW()-INTERVAL 1 DAY),
('TXN-002','income','service', 300.00,'Payment for TK-1250','card',1,NOW()-INTERVAL 1 DAY),
('TXN-003','income','service', 324.00,'Payment for TK-1249','transfer',1,NOW()-INTERVAL 4 DAY),
('TXN-004','income','service', 276.00,'Payment for TK-1248','cash',1,NOW()-INTERVAL 3 DAY),
('TXN-005','expense','salaries',2350.00,'Staff salary batch','transfer',1,NOW()-INTERVAL 7 DAY),
('TXN-006','expense','inventory_purchase',1200.00,'Cables & supplies','cash',1,NOW()-INTERVAL 10 DAY);

-- Sample notifications
INSERT INTO notifications (staff_id,type,title,body,created_at) VALUES
(1,'ticket','New ticket #TK-1254 created','John Smith submitted a new ticket for Electrical Installation',NOW()-INTERVAL 2 MINUTE),
(1,'ticket','Technician John completed ticket #TK-1248','Ticket TK-1248 has been marked completed',NOW()-INTERVAL 15 MINUTE),
(1,'stock','Low stock alert: 23 items','Multiple items are running low on stock',NOW()-INTERVAL 35 MINUTE),
(1,'payment','Payment received from Client #C-1025','Receipt of $300 recorded successfully',NOW()-INTERVAL 60 MINUTE),
(1,'message','New message from Client #C-1028','Sarah Johnson sent a message about her ticket',NOW()-INTERVAL 120 MINUTE);

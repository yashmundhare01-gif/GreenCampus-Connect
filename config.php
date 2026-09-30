<?php
declare(strict_types=1);
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'greencampus';
const DB_USER = 'root';
const DB_PASS = '';
const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
const UPLOAD_DIR = __DIR__ . '/../uploads/';

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        $serverDsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
        $server = new PDO($serverDsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $server->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
        );
        bootstrap_database($pdo);
        return $pdo;
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'Database connection/setup failed. Start MySQL in XAMPP. Details: '.$e->getMessage()]);
        exit;
    }
}

function bootstrap_database(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL UNIQUE,
        contact_placeholder VARCHAR(255) DEFAULT 'Official contact details to be added by college',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY, reporter_type ENUM('Student','Staff') NOT NULL,
        name VARCHAR(120) NOT NULL, department VARCHAR(120) NOT NULL, contact VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_users_name(name)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(120) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY, report_code VARCHAR(20) NOT NULL UNIQUE, user_id INT NOT NULL,
        category_id INT NOT NULL, location_id INT NOT NULL, description TEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL,
        priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
        status ENUM('Pending','In Progress','Resolved') NOT NULL DEFAULT 'Pending',
        assigned_department_id INT DEFAULT NULL, resolution_remarks TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at DATETIME DEFAULT NULL,
        CONSTRAINT fk_reports_user FOREIGN KEY(user_id) REFERENCES users(id),
        CONSTRAINT fk_reports_category FOREIGN KEY(category_id) REFERENCES categories(id),
        CONSTRAINT fk_reports_location FOREIGN KEY(location_id) REFERENCES locations(id),
        CONSTRAINT fk_reports_department FOREIGN KEY(assigned_department_id) REFERENCES departments(id) ON DELETE SET NULL,
        INDEX idx_reports_status(status), INDEX idx_reports_category(category_id), INDEX idx_reports_location(location_id),
        INDEX idx_reports_priority(priority), INDEX idx_reports_created(created_at)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY, report_id INT NOT NULL, old_status VARCHAR(30) DEFAULT NULL,
        new_status VARCHAR(30) NOT NULL, remarks TEXT DEFAULT NULL, changed_by INT NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_history_report FOREIGN KEY(report_id) REFERENCES reports(id) ON DELETE CASCADE,
        CONSTRAINT fk_history_admin FOREIGN KEY(changed_by) REFERENCES admin_users(id)
    ) ENGINE=InnoDB");

    seed_if_empty($pdo, 'categories', [
        'Garbage / Waste','Cleanliness','Water Leakage / Water Wastage','Electricity Wastage',
        'Damaged Infrastructure','Unnecessary / Dumped Material','Garden / Green Area','Other Environmental Issue'
    ]);
    seed_if_empty($pdo, 'locations', [
        'Main Gate','Administration Building','CSE Department','Mechanical Department','Electrical Department',
        'Library','Canteen','Hostel','Parking Area','Garden','Playground','Other'
    ]);
    seed_if_empty($pdo, 'departments', [
        'Campus Welfare / Administration','Maintenance','Sanitation / Housekeeping',
        'Electrical Maintenance','Plumbing / Water Maintenance'
    ]);
    $n=(int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if($n===0){
        $st=$pdo->prepare("INSERT INTO admin_users(username,password_hash,full_name) VALUES(?,?,?)");
        $st->execute(['admin',password_hash('Admin@123',PASSWORD_DEFAULT),'Campus Administrator']);
    }
    $n=(int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
    if($n===0) seed_demo_reports($pdo);
}
function seed_if_empty(PDO $pdo,string $table,array $names): void {
    if((int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()>0)return;
    $st=$pdo->prepare("INSERT INTO `$table` (name) VALUES (?)");
    foreach($names as $name)$st->execute([$name]);
}
function seed_demo_reports(PDO $pdo): void {
    $u=$pdo->prepare("INSERT INTO users(reporter_type,name,department,contact) VALUES(?,?,?,?)");
    $demo=[
        ['Student','Aarav Patil','CSE','demo.student1@college.example'],
        ['Staff','Priya Deshmukh','Administration','demo.staff1@college.example'],
        ['Student','Rohan Shinde','Mechanical','demo.student2@college.example'],
        ['Student','Sneha More','CSE','demo.student3@college.example'],
        ['Staff','Vikram Jadhav','Electrical','demo.staff2@college.example']
    ];
    foreach($demo as $x){$u->execute($x);}
    $r=$pdo->prepare("INSERT INTO reports(report_code,user_id,category_id,location_id,description,priority,status,assigned_department_id,resolution_remarks,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $rows=[
      ['GC-0001',1,1,8,'Overflowing waste bin near the hostel entrance needs collection.','High','Pending',3,null],
      ['GC-0002',2,3,6,'Water tap near the library wash area appears to be leaking continuously.','High','In Progress',5,'Plumbing team has been informed for inspection.'],
      ['GC-0003',3,4,3,'Several classroom lights were left switched on after working hours.','Medium','Resolved',4,'Lights checked and reminder signage added.'],
      ['GC-0004',4,2,7,'Canteen-side area needs additional cleaning after lunch period.','Medium','Pending',3,null],
      ['GC-0005',5,7,10,'Dry leaves and plastic pieces have accumulated in the garden corner.','Low','Resolved',3,'Area cleaned and waste segregated.']
    ];
    foreach($rows as $x)$r->execute($x);
}

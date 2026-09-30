<?php
require_once __DIR__ . '/helpers.php';
require_admin();
require_method('GET');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_response(['success'=>false,'message'=>'Invalid report id'],422);

$stmt = db()->prepare("
SELECT r.*, u.reporter_type, u.name AS reporter, u.department AS reporter_department, u.contact,
       c.name AS category, l.name AS location, d.name AS assigned_department
FROM reports r
JOIN users u ON u.id=r.user_id
JOIN categories c ON c.id=r.category_id
JOIN locations l ON l.id=r.location_id
LEFT JOIN departments d ON d.id=r.assigned_department_id
WHERE r.id=?
");
$stmt->execute([$id]);
$report = $stmt->fetch();
if (!$report) json_response(['success'=>false,'message'=>'Report not found'],404);

$history = db()->prepare("
SELECT h.*, a.full_name AS changed_by_name
FROM report_status_history h
JOIN admin_users a ON a.id=h.changed_by
WHERE h.report_id=?
ORDER BY h.changed_at DESC
");
$history->execute([$id]);

json_response(['success'=>true,'data'=>$report,'history'=>$history->fetchAll()]);

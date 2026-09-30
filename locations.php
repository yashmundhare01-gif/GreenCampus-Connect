<?php
require_once __DIR__ . '/helpers.php';
require_method('GET');
$rows = db()->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();
json_response(['success' => true, 'data' => $rows]);

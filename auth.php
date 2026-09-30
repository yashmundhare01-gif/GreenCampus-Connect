<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['admin_id'])) {
        json_response([
            'success' => true,
            'authenticated' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'full_name' => $_SESSION['admin_name']
            ]
        ]);
    }
    json_response(['success' => true, 'authenticated' => false]);
}

require_method('POST');
$data = get_json_input();
$username = clean_string($data['username'] ?? '', 80);
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(['success' => false, 'message' => 'Username and password are required'], 422);
}

$stmt = db()->prepare('SELECT id, username, password_hash, full_name FROM admin_users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    json_response(['success' => false, 'message' => 'Invalid admin credentials'], 401);
}

session_regenerate_id(true);
$_SESSION['admin_id'] = (int)$admin['id'];
$_SESSION['admin_username'] = $admin['username'];
$_SESSION['admin_name'] = $admin['full_name'];

json_response([
    'success' => true,
    'authenticated' => true,
    'admin' => [
        'id' => (int)$admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name']
    ]
]);

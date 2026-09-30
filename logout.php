<?php
require_once __DIR__ . '/helpers.php';
require_method('POST');
$_SESSION = [];
session_destroy();
json_response(['success' => true]);

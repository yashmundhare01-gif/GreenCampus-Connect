<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function json_response(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) json_response(['success'=>false,'message'=>'Method not allowed'],405);
}
function require_admin(): void {
    if (empty($_SESSION['admin_id'])) json_response(['success'=>false,'message'=>'Admin authentication required'],401);
}
function clean_string(?string $value,int $max=255): string {
    $value=trim((string)$value);
    return $value===''?'':substr($value,0,$max);
}
function get_json_input(): array {
    $d=json_decode(file_get_contents('php://input') ?: '{}',true);
    return is_array($d)?$d:[];
}
function validate_enum(string $value,array $allowed,string $field): void {
    if(!in_array($value,$allowed,true)) json_response(['success'=>false,'message'=>"Invalid {$field}"],422);
}
function save_uploaded_image(array $file): ?string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)json_response(['success'=>false,'message'=>'Image upload failed'],422);
    if(($file['size']??0)>MAX_IMAGE_SIZE)json_response(['success'=>false,'message'=>'Image must be 5 MB or smaller'],422);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime]))json_response(['success'=>false,'message'=>'Only JPG, PNG and WEBP images are allowed'],422);
    $filename=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
    if(!move_uploaded_file($file['tmp_name'],UPLOAD_DIR.$filename))json_response(['success'=>false,'message'=>'Could not save uploaded image'],500);
    return 'uploads/'.$filename;
}

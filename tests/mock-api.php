<?php
// Server tiruan WA gateway / webhook untuk pengujian: catat request ke file JSONL.
$log = getenv('MOCK_LOG') ?: sys_get_temp_dir() . '/presensi-mock-api.jsonl';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];
file_put_contents($log, json_encode(['path' => $path, 'headers' => array_change_key_case($headers, CASE_LOWER), 'body' => $body, 'post' => $_POST]) . "\n", FILE_APPEND | LOCK_EX);
header('Content-Type: application/json');
if (str_contains($path, 'fail')) {
    http_response_code(200);
    echo json_encode(['status' => false, 'reason' => 'token invalid']);
    return;
}
echo json_encode(['status' => true, 'detail' => 'success! message in queue']);

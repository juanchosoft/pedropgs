<?php
session_start();
header("Content-type: application/json; charset=utf-8");

if (!isset($_SESSION['session_user'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

include '../classes/Util.php';

$ip = Util::get_real_ipaddress();
$url = "http://ip-api.com/json/{$ip}?fields=lat,lon";

$resp = false;
if (ini_get('allow_url_fopen')) {
    $resp = @file_get_contents($url);
}
if (!$resp && function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    curl_close($ch);
}

if ($resp) {
    $data = json_decode($resp, true);
    if ($data && isset($data['lat']) && isset($data['lon'])) {
        echo json_encode(['lat' => $data['lat'], 'lon' => $data['lon']]);
        exit;
    }
}

echo json_encode(['error' => 'Unable to determine location']);

<?php
/**
 * Zapisuje ustawienia pluginu unifi-site-scan (connection + wybrane site/SSID).
 * Wywoływane przez fetch() z poziomu frontendu (JSON in, JSON out).
 */
header('Content-Type: application/json');

$cfgDir = '/boot/config/plugins/unifi-site-scan';
$cfgFile = $cfgDir . '/unifi-site-scan.cfg';

if (!is_dir($cfgDir)) {
    mkdir($cfgDir, 0755, true);
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

function cfg_escape($v)
{
    return str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v);
}

$data = [
    'HOST' => $in['host'] ?? '',
    'PORT' => $in['port'] ?? '8443',
    'USERNAME' => $in['username'] ?? '',
    'PASSWORD' => $in['password'] ?? '',
    'APIKEY' => $in['apikey'] ?? '',
    'SELECTED_SITES' => isset($in['selected_sites']) ? implode(',', (array) $in['selected_sites']) : '',
    'SELECTED_SSIDS' => isset($in['selected_ssids']) ? implode(',', (array) $in['selected_ssids']) : '',
];

// Jeśli zapisujemy tylko checkboxy (bez pól połączenia w ciele), zachowaj
// istniejące dane połączenia zamiast je nadpisywać pustką.
if (!isset($in['host']) && file_exists($cfgFile)) {
    $existing = parse_ini_file($cfgFile);
    foreach (['HOST', 'PORT', 'USERNAME', 'PASSWORD', 'APIKEY'] as $k) {
        if (isset($existing[$k])) {
            $data[$k] = $existing[$k];
        }
    }
}

$lines = [];
foreach ($data as $k => $v) {
    $lines[] = $k . '="' . cfg_escape($v) . '"';
}
file_put_contents($cfgFile, implode("\n", $lines) . "\n");

echo json_encode(['ok' => true]);

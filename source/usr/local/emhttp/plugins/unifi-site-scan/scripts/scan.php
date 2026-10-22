<?php
/**
 * Łączy się z kontrolerem UniFi (dane z configu pluginu) i zwraca JSON z
 * listą site, SSID i klientów. Używane zarówno do wypełnienia checkboxów w
 * Ustawieniach, jak i do odświeżenia widoku Dashboard.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../include/UnifiClient.php';

$cfgFile = '/boot/config/plugins/unifi-site-scan/unifi-site-scan.cfg';
if (!file_exists($cfgFile)) {
    echo json_encode(['ok' => false, 'error' => 'Brak konfiguracji — uzupełnij i zapisz dane połączenia w Ustawieniach.']);
    exit;
}

$cfg = parse_ini_file($cfgFile);
$host = $cfg['HOST'] ?? '';
$port = $cfg['PORT'] ?? '8443';
$user = $cfg['USERNAME'] ?? '';
$pass = $cfg['PASSWORD'] ?? '';

if (!$host || !$user || !$pass) {
    echo json_encode(['ok' => false, 'error' => 'Niekompletna konfiguracja — uzupełnij host, login i hasło.']);
    exit;
}

try {
    $client = new UnifiClient($host, $port, $user, $pass);
    $sites = $client->scan();
    echo json_encode(['ok' => true, 'sites' => $sites]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

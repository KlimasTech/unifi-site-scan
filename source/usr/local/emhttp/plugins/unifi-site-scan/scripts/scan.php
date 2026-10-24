<?php
/**
 * Łączy się z kontrolerem UniFi (dane z configu pluginu) i zwraca JSON z
 * listą site, SSID i klientów. Używane zarówno do wypełnienia checkboxów w
 * Ustawieniach, jak i do odświeżenia widoku Dashboard.
 *
 * Odporne na błędy PHP: niezależnie od tego co się wysypie, zawsze zwraca
 * poprawny JSON zamiast pustej/HTML odpowiedzi (przyczyna błędu
 * "Unexpected end of JSON input" po stronie przeglądarki).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Błąd serwera: ' . $err['message']]);
    }
});

try {
    require_once __DIR__ . '/../include/UnifiClient.php';

    if (!function_exists('curl_init')) {
        throw new Exception('Rozszerzenie PHP cURL jest niedostępne na tym serwerze — plugin nie może się połączyć z kontrolerem.');
    }

    $cfgFile = '/boot/config/plugins/unifi-site-scan/unifi-site-scan.cfg';
    if (!file_exists($cfgFile)) {
        throw new Exception('Brak konfiguracji — uzupełnij i zapisz dane połączenia w Ustawieniach.');
    }

    $cfg = @parse_ini_file($cfgFile);
    if (!is_array($cfg)) {
        throw new Exception('Nie udało się odczytać pliku konfiguracji.');
    }
    $host = $cfg['HOST'] ?? '';
    $port = $cfg['PORT'] ?? '8443';
    $user = $cfg['USERNAME'] ?? '';
    $pass = $cfg['PASSWORD'] ?? '';

    if (!$host || !$user || !$pass) {
        throw new Exception('Niekompletna konfiguracja — uzupełnij host, login i hasło.');
    }

    $client = new UnifiClient($host, $port, $user, $pass);
    $sites = $client->scan();

    // Dashboard filtruje wg zapisanego wyboru w Ustawieniach (pusta lista = pokaz wszystko).
    // Settings/skan-do-checkboxow NIE filtruje (potrzebuje pelnej listy), dlatego tylko ?filtered=1.
    if (!empty($_GET['filtered'])) {
        $selSites = array_filter(array_map('trim', explode(',', $cfg['SELECTED_SITES'] ?? '')));
        $selSsids = array_filter(array_map('trim', explode(',', $cfg['SELECTED_SSIDS'] ?? '')));
        if ($selSites) {
            $sites = array_values(array_filter($sites, function ($s) use ($selSites) {
                return in_array($s['name'], $selSites, true);
            }));
        }
        if ($selSsids) {
            foreach ($sites as &$s) {
                $s['ssids'] = array_values(array_filter($s['ssids'], function ($ss) use ($selSsids) {
                    return in_array($ss['ssid'], $selSsids, true);
                }));
            }
            unset($s);
        }
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['ok' => true, 'sites' => $sites]);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

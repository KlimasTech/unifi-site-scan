<?php
/**
 * Zapisuje ustawienia pluginu unifi-site-scan (connection + wybrane site/SSID).
 * Wywoływane przez fetch() z poziomu frontendu (JSON in, JSON out).
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
    $cfgDir = '/boot/config/plugins/unifi-site-scan';
    $cfgFile = $cfgDir . '/unifi-site-scan.cfg';

    if (!is_dir($cfgDir) && !mkdir($cfgDir, 0755, true) && !is_dir($cfgDir)) {
        throw new Exception('Nie udało się utworzyć katalogu konfiguracji: ' . $cfgDir);
    }

    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        $in = $_POST;
    }

    $cfgEscape = function ($v) {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v);
    };

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
        $existing = @parse_ini_file($cfgFile);
        if (is_array($existing)) {
            foreach (['HOST', 'PORT', 'USERNAME', 'PASSWORD', 'APIKEY'] as $k) {
                if (isset($existing[$k])) {
                    $data[$k] = $existing[$k];
                }
            }
        }
    }

    $lines = [];
    foreach ($data as $k => $v) {
        $lines[] = $k . '="' . $cfgEscape($v) . '"';
    }
    if (file_put_contents($cfgFile, implode("\n", $lines) . "\n") === false) {
        throw new Exception('Nie udało się zapisać pliku konfiguracji: ' . $cfgFile);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

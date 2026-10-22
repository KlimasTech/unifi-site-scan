<?php
/**
 * UnifiClient — minimalny klient do klasycznego (samodzielnego) kontrolera
 * UniFi Network (np. obraz Docker jacobalberty/unifi), NIE dla konsol UniFi OS.
 *
 * Uwierzytelnianie: login + hasło, sesja na cookie. Klasyczny kontroler nie
 * wspiera kluczy API (X-API-KEY) — pole API key jest tu przyjmowane tylko
 * na wypadek przyszłej kompatybilności z UniFi OS, obecnie nieużywane do
 * realnych zapytań.
 */

class UnifiClient
{
    private $baseUrl;
    private $username;
    private $password;
    private $cookieFile;

    public function __construct($host, $port, $username, $password)
    {
        $host = preg_replace('#^https?://#', '', trim($host));
        $this->baseUrl = 'https://' . $host . ':' . intval($port);
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = '/tmp/unifi-site-scan-' . md5($host . $username) . '.cookies';
    }

    private function curl($path, $method = 'GET', $body = null)
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new Exception('Błąd połączenia: ' . $err);
        }
        $data = json_decode($resp, true);
        if ($data === null) {
            throw new Exception('Nieprawidłowa odpowiedź serwera (nie-JSON)');
        }
        return $data;
    }

    public function login()
    {
        $resp = $this->curl('/api/login', 'POST', [
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $rc = $resp['meta']['rc'] ?? 'error';
        if ($rc !== 'ok') {
            $msg = $resp['meta']['msg'] ?? 'nieznany błąd';
            if (strpos($msg, '2fa') !== false || strpos($msg, '2Fa') !== false) {
                throw new Exception(
                    'To konto wymaga weryfikacji dwuetapowej (2FA), której ten plugin nie obsługuje. ' .
                    'Utwórz dedykowane lokalne konto tylko-do-odczytu bez zdalnego dostępu ' .
                    '(Ustawienia → Administratorzy → "+" → odznacz "Zdalny dostęp" → rola "Tylko podgląd").'
                );
            }
            throw new Exception('Logowanie nieudane: ' . $msg);
        }
        return true;
    }

    public function getSites()
    {
        $resp = $this->curl('/api/stat/sites');
        if (($resp['meta']['rc'] ?? '') === 'error' && strpos($resp['meta']['msg'] ?? '', 'LoginRequired') !== false) {
            $this->login();
            $resp = $this->curl('/api/stat/sites');
        }
        if (($resp['meta']['rc'] ?? '') !== 'ok') {
            throw new Exception('Nie udało się pobrać listy site: ' . ($resp['meta']['msg'] ?? ''));
        }
        return $resp['data'];
    }

    public function getClients($siteName)
    {
        $resp = $this->curl('/api/s/' . rawurlencode($siteName) . '/stat/sta');
        if (($resp['meta']['rc'] ?? '') !== 'ok') {
            throw new Exception('Nie udało się pobrać klientów site "' . $siteName . '": ' . ($resp['meta']['msg'] ?? ''));
        }
        return $resp['data'];
    }

    /**
     * Pełne skanowanie: wszystkie site, dla każdego lista SSID z liczbą
     * podłączonych klientów i prostą listą nazw urządzeń.
     */
    public function scan()
    {
        $this->login();
        $sites = $this->getSites();
        $result = [];
        foreach ($sites as $site) {
            $name = $site['name'];
            $desc = $site['desc'] ?: $name;
            $wlanHealth = null;
            foreach (($site['health'] ?? []) as $h) {
                if (($h['subsystem'] ?? '') === 'wlan') {
                    $wlanHealth = $h;
                }
            }
            $clients = [];
            try {
                $clients = $this->getClients($name);
            } catch (Exception $e) {
                // brak uprawnień/klientów w tym site — pomijamy, nie przerywamy skanu
            }
            $ssidMap = [];
            foreach ($clients as $c) {
                if (!empty($c['is_wired'])) {
                    continue;
                }
                $ssid = $c['essid'] ?? '(nieznane)';
                if (!isset($ssidMap[$ssid])) {
                    $ssidMap[$ssid] = ['ssid' => $ssid, 'count' => 0, 'clients' => []];
                }
                $ssidMap[$ssid]['count']++;
                $ssidMap[$ssid]['clients'][] = [
                    'name' => $c['hostname'] ?? ($c['name'] ?? $c['mac']),
                    'ip' => $c['ip'] ?? '',
                    'signal' => $c['signal'] ?? null,
                ];
            }
            $result[] = [
                'name' => $name,
                'desc' => $desc,
                'num_ap' => $wlanHealth['num_adopted'] ?? 0,
                'num_clients' => count($clients),
                'ssids' => array_values($ssidMap),
            ];
        }
        return $result;
    }
}

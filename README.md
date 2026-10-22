# UniFi Site Scan — plugin do unRAID

Plugin do unRAID OS wyświetlający w WebGUI listę site'ów, SSID i aktywnych
klientów z Twojego kontrolera UniFi Network — klasycznego, samodzielnego
(nie UniFi OS/UDM).

![Dashboard](docs/screenshots/dashboard.png)
![Ustawienia](docs/screenshots/settings.png)

## Funkcje

- **Dashboard** — eleganckie karty per site: liczba AP, liczba klientów,
  rozbicie po SSID z liczbą podłączonych urządzeń, rozwijana lista
  klientów (nazwa, IP, siła sygnału)
- **Ustawienia** — host/port/login/hasło + pole na klucz API (zarezerwowane
  pod przyszłą kompatybilność z UniFi OS), przycisk **Zapisz**
- **Filtrowanie widoczności** — skan dostępnych site/SSID z checkboxami,
  wybierz które mają się pokazywać w Dashboardzie (nic zaznaczone =
  pokazuj wszystko)
- Wbudowana instrukcja tworzenia bezpiecznego konta integracyjnego
  (lokalne, tylko-do-odczytu, bez 2FA)
- Autoodświeżanie danych, spójny ciemny motyw

## Dlaczego login+hasło, nie klucz API

Klasyczny, samodzielny kontroler UniFi (obraz Docker
`jacobalberty/unifi`) **nie wspiera kluczy API** — ta funkcja istnieje
tylko na konsolach UniFi OS (UDM/Cloud Key Gen2+). Plugin loguje się
klasycznym `/api/login` z sesją na cookie, tak jak robi to WebGUI
kontrolera.

Jeśli Twoje konto administratora ma włączone 2FA, plugin się nim nie
zaloguje (nie obsługuje TOTP) — w zakładce Ustawienia jest pełna
instrukcja utworzenia dedykowanego **lokalnego** konta tylko-do-odczytu
bez 2FA (2FA jest wymuszane tylko dla kont ze zdalnym dostępem/Ubiquiti
SSO, nie dla kont czysto lokalnych).

## Instalacja

**Przez WebGUI unRAID:** Plugins → Install Plugin → wklej:
```
https://raw.githubusercontent.com/KlimasTech/unifi-site-scan/main/unifi-site-scan.plg
```

**Z terminala unRAID:**
```bash
plugin install https://raw.githubusercontent.com/KlimasTech/unifi-site-scan/main/unifi-site-scan.plg
```

Po instalacji: **Settings → Utilities → UniFi Site Scan**.

## Konfiguracja

1. Zakładka **⚙️ Ustawienia** → wpisz host/IP kontrolera, port (domyślnie
   8443), login i hasło konta integracyjnego → **Zapisz**.
2. Kliknij **🔍 Skanuj site i SSID** — plugin połączy się z kontrolerem i
   pokaże checkboxy wszystkich znalezionych site i SSID.
3. Odznacz te, których nie chcesz widzieć w Dashboardzie → **Zapisz
   zaznaczone**.
4. Wróć do zakładki **📡 Dashboard** — dane odświeżają się automatycznie
   przy wejściu, ręcznie przyciskiem **↻ Odśwież**.

## Struktura projektu

```
source/usr/local/emhttp/plugins/unifi-site-scan/
├── unifi-site-scan.page     # strona WebGUI (Dashboard + Ustawienia, PHP+HTML+JS+CSS)
├── default.cfg               # domyślna konfiguracja
├── include/
│   └── UnifiClient.php       # klient API kontrolera (login, stat/sites, stat/sta)
└── scripts/
    ├── save.php               # AJAX: zapis ustawień/wyboru site-SSID do cfg
    └── scan.php                # AJAX: pełny skan kontrolera, zwraca JSON
unifi-site-scan.plg            # manifest instalacyjny
pkg_build.sh                    # budowa paczki .txz (makepkg, uruchamiać na unRAID)
```

## Budowanie ze źródeł

```bash
# na serwerze unRAID (ma makepkg)
git clone https://github.com/KlimasTech/unifi-site-scan.git
cd unifi-site-scan
./pkg_build.sh 2026.10.22
# wypisze ścieżkę .txz i sumę MD5 — wklej MD5 do unifi-site-scan.plg
```

## Licencja

MIT

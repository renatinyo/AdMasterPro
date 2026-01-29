# AdMaster Pro v2.0

## Google Ads AI Asszisztens - RS Agency

Profi Google Ads kampány menedzsment eszköz magyar szolgáltató cégeknek.

---

## 🚀 Gyors Telepítés

### 1. Fájlok feltöltése
Töltsd fel az összes fájlt a webszerveredre.

### 2. Telepítő futtatása
Nyisd meg böngészőben: `https://yourdomain.com/admaster-pro/install.php`

A telepítő végigvezet:
1. ✅ Rendszerkövetelmények ellenőrzése
2. 📁 Könyvtárak létrehozása
3. 🔑 API kulcs beállítása
4. ⚙️ Opcionális beállítások
5. 🎉 Kész!

### 3. Telepítő törlése (FONTOS!)
```bash
rm install.php
```

---

## 🔄 Frissítések

### Frissítések keresése
Nyisd meg: `https://yourdomain.com/admaster-pro/update.php`

### Frissítési funkciók:
- 🔍 Automatikus verzió ellenőrzés
- 💾 Backup készítés frissítés előtt
- 🔄 Backup visszaállítás
- ℹ️ Rendszer információk

---

## 🚀 Funkciók

### ✍️ Hirdetésszöveg Generátor
- 15 headline + 4 description (RSA)
- Karakterszám ellenőrzés
- PIN javaslatok (H1/H2/H3)
- Konkurencia elemzés
- Negatív kulcsszó listák

### ⚙️ Beállítási Tanácsadó
- 4 bid stratégia részletesen
- Büdzsé javaslatok
- Ütemezés + bid modifier-ek
- Földrajzi célzás
- Eszköz beállítások

### 📊 CSV Elemzés
- Google Ads riportok importálása
- Kampány/Hirdetés/Kulcsszó elemzés
- Pazarló költések azonosítása
- Automatikus javaslatok

### 🌐 Landing Page Audit
- Technikai elemzés (HTTPS, sebesség, mobile)
- Tartalom elemzés (telefonszám, CTA, trust elemek)
- Konverzió optimalizálási javaslatok
- Google Ads Quality Score becslés
- Prioritásos teendő lista

### 💾 Projekt Mentés
- Beállítások mentése/betöltése
- Session alapú azonosítás
- JSON export/import

---

## 📁 Fájlstruktúra

```
admaster-pro-v2/
├── index.php              # Fő alkalmazás
├── api.php                # API endpoint
├── config.php             # Konfiguráció (API kulcs!)
├── .htaccess              # Apache biztonság
├── assets/
│   ├── style.css          # Stílusok
│   └── app.js             # Frontend JS
├── includes/
│   ├── Security.php       # Biztonsági funkciók
│   ├── ProjectManager.php # Projekt kezelés
│   ├── CsvAnalyzer.php    # CSV elemzés
│   └── LandingPageAnalyzer.php  # Landing page audit
├── data/
│   ├── industries.php     # Iparági adatok
│   └── projects/          # Mentett projektek
└── logs/                  # Log fájlok
```

---

## 🔧 Telepítés

### 1. Fájlok feltöltése
Töltsd fel az egész mappát a szerverre.

### 2. Jogosultságok
```bash
chmod 755 admaster-pro-v2/
chmod 644 admaster-pro-v2/*.php
chmod 644 admaster-pro-v2/.htaccess
chmod -R 750 admaster-pro-v2/data/
chmod -R 750 admaster-pro-v2/logs/
```

### 3. API kulcs beállítása

**Opció A: Környezeti változó (ajánlott)**
```bash
# .htaccess-ben:
SetEnv ANTHROPIC_API_KEY sk-ant-xxxxx

# vagy rendszer szinten:
export ANTHROPIC_API_KEY=sk-ant-xxxxx
```

**Opció B: config.php-ban közvetlenül**
```php
define('ANTHROPIC_API_KEY', 'sk-ant-xxxxx');
```

### 4. Tesztelés
Nyisd meg: `https://yourdomain.com/admaster-pro-v2/`

---

## 🔒 Biztonsági Funkciók

| Funkció | Leírás |
|---------|--------|
| **CSRF védelem** | Token alapú form validáció |
| **Rate limiting** | 30 kérés/óra session alapon |
| **Input sanitization** | XSS és injection védelem |
| **Session biztonság** | Strict mode, fingerprint, regenerate |
| **File upload validáció** | Típus, méret, kiterjesztés ellenőrzés |
| **Path traversal védelem** | Projekt fájl hozzáférés korlátozás |
| **Error handling** | Érzékeny infó elrejtése production-ben |
| **Security headers** | CSP, X-Frame-Options, stb. |

---

## ⚙️ Konfiguráció

### config.php beállítások

```php
// Környezet
define('APP_ENV', 'production'); // vagy 'development'
define('APP_DEBUG', false);      // true = részletes hibák

// Rate limiting
define('RATE_LIMIT_REQUESTS', 30);  // Max kérés
define('RATE_LIMIT_WINDOW', 3600);  // Időablak (mp)

// Fájl feltöltés
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB
```

---

## 📊 CSV Import Formátum

Támogatott Google Ads riportok:
- **Campaign report**: Campaign, Cost, Clicks, Impressions, Conversions
- **Ad report**: Campaign, Ad group, Headline 1, CTR, Conversions
- **Keyword report**: Keyword, Match type, Cost, Conversions
- **Search terms report**: Search term, Clicks, Cost, Conversions

Export Google Ads-ből:
1. Reports → Predefined reports
2. Válassz riport típust
3. Download → CSV

---

## 🛠️ Hibaelhárítás

### "API kulcs nincs beállítva"
→ Állítsd be a config.php-ban vagy környezeti változóként

### "CSRF token érvénytelen"
→ Frissítsd az oldalt (token lejárt)

### "Rate limit túllépve"
→ Várj 1 órát vagy növeld a limitet config.php-ban

### CSV nem töltődik fel
→ Ellenőrizd a fájl méretét (max 5MB) és formátumot (UTF-8 CSV)

### Projekt nem mentődik
→ Ellenőrizd a data/projects/ mappa írási jogosultságát

---

## 📝 Fejlesztés

### Új iparág hozzáadása

Szerkeszd: `data/industries.php`

```php
'uj_iparag' => [
    'name' => 'Új Iparág',
    'icon' => '🔨',
    'color' => '#FF5733',
    'usps' => [
        ['id' => 'usp1', 'text' => 'USP szöveg', 'impact' => 'high'],
    ],
    'benchmarks' => [
        'cpa_budapest' => 'X.XXX-X.XXX Ft',
        'cpc' => 'XXX-XXX Ft',
        'ctr' => 'X.X%',
        'daily_budget_min' => 10000,
        'daily_budget_recommended' => 20000,
        'daily_budget_max' => 40000
    ],
    'negative_keywords' => ['kulcsszó1', 'kulcsszó2'],
]
```

---

## 📄 Verzió

**v2.0** - 2024
- CSRF védelem
- Rate limiting
- CSV import és elemzés
- Landing Page Audit (technikai, tartalom, konverzió, SEO)
- Quality Score hatás becslés
- Projekt mentés/betöltés
- Biztonságos fájlkezelés
- Javított error handling

---

## 👤 Kapcsolat

RS Agency belső eszköz

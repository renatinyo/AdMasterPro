# AdMaster Pro v2.0 - Funkcionalitás Leírás

## 📋 Áttekintés

Az AdMaster Pro egy Google Ads kampány menedzsment eszköz, amely AI segítségével generál hirdetésszövegeket, beállítási javaslatokat ad, elemzi a CSV riportokat és auditálja a landing page-eket.

**Célcsoport:** RS Agency belső használat, magyar szolgáltató cégek (duguláselhárítás, vízszerelés, biztonsági rendszerek)

---

## 🏠 Főoldal / Dashboard

### Header
- **Logo:** AdMaster Pro - Google Ads AI Asszisztens v2.0.0
- **RS Agency badge:** Jobb felső sarok

### Navigáció (4 tab)
1. ✍️ **Hirdetésszöveg** - RSA hirdetések generálása
2. ⚙️ **Beállítások** - Kampány beállítási javaslatok
3. 📊 **CSV Elemzés** - Google Ads riportok elemzése
4. 🌐 **Landing Page Audit** - Weboldal elemzés

### Státusz kijelzés
- 🔴 **API kulcs hiányzik** - Figyelmeztetés, ha nincs beállítva
- 🟡 **Demo mód** - Ha DEMO_MODE=true a config-ban
- Rate limit számláló a footer-ben

---

## ✍️ 1. TAB: Hirdetésszöveg Generátor

### Funkció
AI-alapú Google Ads RSA (Responsive Search Ad) hirdetésszövegek generálása.

### Lépések

#### 1. Iparág választás (3 opció)
| Iparág | Ikon | CPA tartomány |
|--------|------|---------------|
| Duguláselhárítás | 🔧 | 2.500-4.500 Ft |
| Vízszerelés | 💧 | 3.000-5.000 Ft |
| Biztonsági Rendszerek | 🔒 | 6.000-12.000 Ft |

#### 2. Cég adatok
- **Cégnév** (kötelező, max 100 karakter)
- **Telefonszám** (opcionális, +36 formátum)
- **Terület** (Budapest/Vidék/Országos)
- **Ár információ** (opcionális, pl. "már 15.000 Ft-tól")

#### 3. Egyedi Előnyök (USP)
Iparág-specifikus checkboxok, pl.:
- ✅ 24 órás szolgáltatás
- ✅ Garanciával dolgozunk
- ✅ 30 percen belül
- ✅ Hétvégén is
- stb.

Minden USP-nek van impact jelzése: ⬆️ High / ➡️ Med / ➡️ Low

#### 4. Konkurencia elemzés (opcionális)
3 szövegdoboz konkurens hirdetések beillesztésére. Az AI elemzi és jobb alternatívákat javasol.

### Output
- **15 headline** (max 30 karakter)
  - Pin pozíciók jelölve (H1, H2, H3)
  - Karakterszám kijelzés
  - Másolás gomb
- **4 description** (max 90 karakter)
  - Karakterszám kijelzés
  - Másolás gomb
- **Konkurencia elemzés** (ha volt input)
  - Közös elemek
  - Kihasználatlan lehetőségek
  - Differenciálási stratégia

### Negatív kulcsszavak
Iparág-specifikus negatív kulcsszó lista automatikusan megjelenik, másolható.

---

## ⚙️ 2. TAB: Beállítások

### Funkció
Google Ads kampány beállítási javaslatok generálása AI segítségével.

### Lépések

#### 1-3. Iparág, Cég adatok, USP
Ugyanaz mint a Hirdetésszöveg tab-nál.

#### 4. Kampány Cél (4 opció)
| Cél | Ikon | Leírás | Ajánlott |
|-----|------|--------|----------|
| Konverziók | 🎯 | Hívások, űrlapok maximalizálása | Szolgáltatóknak |
| Weboldal Forgalom | 👥 | Minél több látogató | Új oldalak, blogok |
| Brand Awareness | 📢 | Márkaismertség növelése | Nagy cégek |
| Lead Generálás | 📋 | Érdeklődők gyűjtése | B2B, drága szolgáltatások |

#### 5. Bid Stratégia (4 opció)
| Stratégia | Leírás | Mikor használd |
|-----------|--------|----------------|
| Konverzió maximalizálás | Legtöbb konverzió a büdzsén belül | Új fiókok |
| Konverzió - Cél CPA | Konverziók meghatározott költség alatt | 30+ konverzió/hó után |
| Kattintás maximalizálás | Legtöbb kattintás | Brand kampányok |
| Kattintás - Max CPC | Kattintások max CPC-vel | Szigorú kontroll |

#### Benchmarkok
Iparági átlagok megjelenítése:
- CTR %
- CPC tartomány
- CPA tartomány
- Ajánlott napi büdzsé

### Output
- **Büdzsé javaslat** (min/ajánlott/max napi Ft)
- **Bid stratégia** ajánlás indoklással
- **Ütemezés** (napszakok, bid modifier-ek)
- **Célzás** (földrajzi, eszköz)
- **Kulcsszó stratégia** (match típusok, példák)
- **Tippek** (iparág-specifikus tanácsok)

---

## 📊 3. TAB: CSV Elemzés

### Funkció
Google Ads-ből exportált CSV riportok elemzése és javaslatok generálása.

### Támogatott riport típusok
1. **Campaign Report** - Kampány szintű adatok
2. **Ad Report** - Hirdetés szintű adatok
3. **Keyword Report** - Kulcsszó teljesítmény
4. **Search Terms Report** - Keresési kifejezések

### Használat
1. CSV fájl feltöltése (drag & drop vagy tallózás)
2. Max 5 MB, UTF-8 kódolás
3. Automatikus riport típus felismerés

### Output
- **Összesítés**
  - Összes költés
  - Kattintások
  - Konverziók
  - Átlag CPA
- **Prioritásos javaslatok**
  - 🔴 Kritikus problémák
  - 🟡 Figyelmeztetések
  - 🔵 Optimalizálási lehetőségek
- **Specifikus elemek** (típustól függően)
  - Legjobb/legrosszabb kulcsszavak
  - Negatívnak ajánlott keresések
  - Hirdetés teljesítmény összehasonlítás

---

## 🌐 4. TAB: Landing Page Audit

### Funkció
Weboldal elemzése Google Ads Quality Score szempontjából.

### Használat
1. Landing page URL megadása
2. Opcionális: Iparág kiválasztása (releváns kulcsszavak ellenőrzéséhez)

### Elemzési kategóriák

#### ⚙️ Technikai (15 pont)
- HTTPS biztonság
- Mobile-friendly viewport
- Betöltési sebesség (<3 másodperc)
- Gzip tömörítés
- Canonical URL

#### 📝 Tartalom (25 pont)
- Title tag (30-60 karakter)
- Meta description (120-160 karakter)
- H1 címsor
- Telefonszám láthatóság
- Iparági kulcsszavak
- Szöveg mennyiség (300+ szó)

#### 🛡️ Bizalmi elemek (10 pont)
- Vélemények/értékelések
- Garancia
- Tapasztalat évek
- Árinformáció
- Tanúsítványok
- Partner logók

#### 🎯 CTA elemek (20 pont)
- CTA gombok
- Above-the-fold CTA
- Kattintható telefon (tel: link)
- Kapcsolati űrlap

#### 🔍 SEO (15 pont)
- Képek alt attribútumok
- Schema.org markup
- Open Graph meta tagek

### Output
- **Pontszám** (0-100)
  - 90-100: Kiváló (zöld)
  - 75-89: Jó (zöld)
  - 60-74: Átlagos (narancs)
  - 40-59: Gyenge (narancs)
  - 0-39: Kritikus (piros)

- **Quality Score becslés**
  - Jelenlegi LP Experience
  - Javítások utáni potenciál
  - Becsült QS javulás
  - CPC hatás

- **Prioritásos javaslatok**
  - Konkrét teendők
  - Várható hatás

---

## 💾 Projekt Mentés/Betöltés

### Funkció
Kampány projektek mentése és visszatöltése.

### Mentés
1. "Projekt Mentése" gomb
2. Projekt név megadása
3. JSON formátumban tárolva

### Betöltés
- Header dropdown menüből
- Utolsó 50 projekt listázva
- Időbélyeg megjelenítés

### Tárolt adatok
- Iparág
- Cég adatok
- Kiválasztott USP-k
- Konkurencia szövegek
- Bid stratégia beállítások

---

## 🔧 Telepítés

### Követelmények
- PHP 7.4+
- cURL, JSON, mbstring, OpenSSL extension
- Apache mod_rewrite

### Lépések
1. Fájlok feltöltése
2. `install.php` megnyitása böngészőben
3. Varázsló követése (API kulcs, beállítások)
4. `install.php` törlése!

### Konfiguráció (config.php)
```php
define('ANTHROPIC_API_KEY', 'sk-ant-xxx');  // Kötelező
define('DEMO_MODE', false);                  // true = API nélkül működik
define('DB_HOST', '');                       // Opcionális MySQL
```

---

## 🔄 Frissítés

### update.php funkciók
- Verzió ellenőrzés
- Backup készítés
- Backup visszaállítás
- Rendszer információk

---

## 🔒 Biztonság

### Implementált védelmek
- **CSRF token** minden form-on
- **Rate limiting** (30 kérés/óra alapértelmezett)
- **Input sanitization** (XSS védelem)
- **File upload validáció** (típus, méret)
- **.htaccess** védelem érzékeny mappákra

### Védett mappák
- `/data/` - Projekt adatok
- `/logs/` - Hibanaplók
- `/includes/` - PHP osztályok
- `/backups/` - Mentések

---

## 📁 Fájlstruktúra

```
admaster-pro-v2/
├── index.php              # Fő alkalmazás
├── api.php                # API endpoint
├── config.php             # Konfiguráció
├── install.php            # Telepítő
├── update.php             # Frissítő
├── .htaccess              # Apache config
├── assets/
│   ├── style.css          # Stílusok
│   └── app.js             # Frontend JS
├── includes/
│   ├── Security.php       # Biztonsági funkciók
│   ├── ProjectManager.php # Projekt kezelés
│   ├── CsvAnalyzer.php    # CSV elemzés
│   ├── LandingPageAnalyzer.php # LP audit
│   └── Database.php       # MySQL (opcionális)
├── data/
│   ├── industries.php     # Iparág adatok
│   └── projects/          # Mentett projektek
├── logs/                  # Hibanaplók
└── backups/               # Backup-ok
```

---

## 🎨 UI/UX

### Design
- Sötét téma (#0a0a0f háttér)
- Narancs akcentus (#E85D04)
- Responsive (mobil-barát)
- Kártya alapú layout

### Interakciók
- Kattintható kártyák (iparág, USP, bid stratégia)
- Drag & drop fájl feltöltés
- Egy kattintásos másolás
- Loading animációk
- Toast értesítések

---

## 🔌 API Integráció

### Anthropic Claude API
- Model: claude-sonnet-4-20250514
- Max tokens: 4000
- JSON response parsing

### Demo mód
Ha `DEMO_MODE=true`:
- Nem hív valódi API-t
- Előre definiált válaszokat ad
- Teszteléshez és demózáshoz

---

## 📈 Jövőbeli fejlesztések

- [ ] Google Ads API integráció
- [ ] Automatikus kampány import
- [ ] A/B teszt javaslatok
- [ ] Több iparág hozzáadása
- [ ] Felhasználói fiókok
- [ ] Riport exportálás (PDF)

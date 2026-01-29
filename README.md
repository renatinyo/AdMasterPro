# AdMaster Pro - Chrome Extension

## 🚀 Telepítés

### 1. Developer Mode bekapcsolása
1. Nyisd meg: `chrome://extensions/`
2. Jobb felső sarokban kapcsold be: **Developer mode**

### 2. Extension betöltése
1. Kattints: **Load unpacked**
2. Válaszd ki ezt a mappát: `admaster-chrome-extension`
3. Kész! Az ikon megjelenik a toolbar-ban.

### 3. Beállítás
1. Kattints az AdMaster ikonra
2. Válaszd a ⚙️ Beállítások-at
3. Add meg az AdMaster Pro szerver URL-t (pl. `https://admaster.yourdomain.hu`)
4. Mentés

## 📋 Használat

### A Google Ads felületen:
1. Generálj hirdetésszövegeket az AdMaster Pro webalkalmazásban
2. A Chrome Extension automatikusan szinkronizálja az adatokat
3. A Google Ads hirdetésszerkesztőben kattints az AdMaster ikonra
4. Kattints a kívánt headline/description melletti 📥 gombra
5. A szöveg automatikusan beíródik a következő üres mezőbe!

### Funkciók:
- **📋 Másolás**: Vágólapra másolás
- **📥 Beillesztés**: Automatikus kitöltés a Google Ads mezőbe
- **Mind beillesztése**: Az összes headline/description egyszerre
- **🔄 Szinkronizálás**: Adatok frissítése a szerverről

## 🖼️ Ikonok

A Chrome Extension működéséhez PNG ikonok kellenek. Generáld őket az `icon.svg` fájlból:

- `icon16.png` - 16x16 pixel
- `icon48.png` - 48x48 pixel
- `icon128.png` - 128x128 pixel

Online konverter: https://svgtopng.com/

## ⚠️ Hibaelhárítás

### "A bővítmény nem működik a Google Ads-on"
- Győződj meg róla, hogy a `https://ads.google.com/*` engedélyezett
- Frissítsd az oldalt (Ctrl+Shift+R)

### "Nem tudok szinkronizálni"
- Ellenőrizd a szerver URL-t a beállításokban
- Győződj meg róla, hogy az AdMaster Pro fut
- Ellenőrizd a CORS beállításokat

### "A mezők nem töltődnek ki"
- A Google Ads felület változhat, lehet hogy a selectorok nem aktuálisak
- Próbáld a kézi másolás + beillesztés módszert

## 🔧 Fejlesztőknek

### Fájl struktúra:
```
admaster-chrome-extension/
├── manifest.json     # Extension konfiguráció
├── popup.html        # Popup felület
├── popup.css         # Popup stílusok
├── popup.js          # Popup logika
├── content.js        # Google Ads oldal integráció
├── content.css       # Oldal stílusok
├── options.html      # Beállítások oldal
├── options.js        # Beállítások logika
└── icons/            # Ikonok
```

### API végpontok (AdMaster oldalon):
- `extension_ping` - Kapcsolat teszt
- `get_extension_data` - Utolsó generált adatok lekérése
- `save_extension_data` - Adatok mentése

---

**Verzió:** 1.0.0  
**Kompatibilitás:** Chrome 88+, Edge 88+

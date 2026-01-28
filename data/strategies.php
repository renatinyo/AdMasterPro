<?php
/**
 * AdMaster Pro - Stratégiai Javaslatok és Extra Headlines
 */

// Extra, figyelemfelkeltő headline sablonok iparág szerint
$GLOBALS['extra_headlines'] = [
    'dugulas' => [
        'aggressive' => [
            'DUGULÁS? {perc} Perc és Ott Vagyunk!',
            'Éjjel 3-kor is Felvesszük! 🌙',
            'Míg Ezt Olvasod, Már Indulunk!',
            'Ha Nem Oldjuk Meg = INGYEN',
            'A Szomszéd Már Minket Hívott!',
            '⚡ Villámgyors Duguláselhárítás',
            '🔧 Most Hívj, {perc}p Múlva Ott!',
            'NE Próbáld Egyedül! Mi Megoldjuk',
            'Dugulás VÉSZHELYZET? Azonnal!',
            '100% Garancia VAGY Visszakapod'
        ],
        'trust' => [
            '{ev}+ Év Tapasztalat, 0 Reklamáció',
            'Google-ön ⭐⭐⭐⭐⭐ Értékelés',
            '{db}+ Elégedett Ügyfél Budapesten',
            'Családi Vállalkozás {ev}. Éve',
            'Profi Gépekkel, Nem Kézzel!'
        ],
        'price' => [
            'Fix Ár - NINCS Rejtett Költség!',
            'Kiszállás + Munkadíj = {ar} Ft-tól',
            'Előre Megmondjuk Az Árat!',
            'Olcsóbb Mint Gondolnád!',
            'Számla AZONNAL a Helyszínen'
        ]
    ],
    'vizszerelo' => [
        'aggressive' => [
            'CSŐTÖRÉS? Ne Pánikolj, Hívj MOST!',
            'Víz Folyik? {perc} Perc és Ott!',
            'Éjjel-Nappal, Hétvégén is!',
            '⚡ SOS Vízszerelő Azonnal',
            'Amíg Olvasol, a Víz Folyik...',
            'Egy Hívás és MEGOLDVA!',
            'Ne Várd Meg Amíg Beázik!'
        ],
        'trust' => [
            'Budapesti Vízszerelő {ev}+ Éve',
            'Garancia Minden Munkára!',
            'Szakképzett, Megbízható Mester'
        ],
        'price' => [
            'Kiszállás INGYENES Budapesten',
            'Tiszta Munka, Tiszta Ár',
            'Árajánlat 5 Percen Belül'
        ]
    ],
    'biztonsag' => [
        'aggressive' => [
            'Betörtek a Szomszédba. Te Jössz?',
            'Amíg Alszol, Ki Vigyáz Rád?',
            'A Biztonság Nem Luxus!',
            '🔒 Ma Rendeld, Holnap Védve!',
            'Riasztó Nélkül = Kockázat'
        ],
        'trust' => [
            '{ev}+ Év Tapasztalat Biztonságban',
            'Rendőrségi Gyorsriasztás',
            '24/7 Központi Felügyelet'
        ],
        'price' => [
            'Felmérés + Árajánlat INGYEN',
            'Havi {ar} Ft-tól Teljes Védelem',
            'Részletfizetés 0% THM'
        ]
    ],
    'zarszerviz' => [
        'aggressive' => [
            'KIZÁRTAD MAGAD? {perc} Perc!',
            'Ne Törd Be Az Ajtót! Hívj Minket!',
            'Zár Nyitás GYORSAN és OLCSÓN',
            '🔑 Kulcs Bent? Megoldjuk!',
            'Éjjel 2? Nem Gond, Megyünk!'
        ],
        'trust' => [
            'Kármentesen Nyitunk!',
            'Rendőrségi Igazolás Kiadható',
            'Ajtó Sértetlen Marad!'
        ],
        'price' => [
            'Zárnyitás {ar} Ft-tól',
            'Fix Ár, Nincs Meglepetés',
            'Számla + Garancia'
        ]
    ]
];

// Stratégiai javaslatok
$GLOBALS['strategies'] = [
    'top_of_page' => [
        'name' => '🥇 Top of Page Dominancia',
        'description' => 'Mindig az első helyen jelenj meg - a konkurensek FELETT',
        'difficulty' => 'medium',
        'budget_impact' => 'high',
        'settings' => [
            'bid_strategy' => 'Target Impression Share',
            'impression_share_target' => '95%',
            'location' => 'Absolute top of results page',
            'max_cpc_limit' => 'Állíts be limit-et túlköltés ellen!'
        ],
        'tips' => [
            'Csak magas intent kulcsszavakra használd',
            'Figyelj a Quality Score-ra (min. 7+)',
            'Mobil bid modifier +20-30%',
            'Éjszaka/hétvége lehet olcsóbb a top pozíció'
        ],
        'when_to_use' => 'Ha a konverzió értéke magas és fontos az azonnali elérhetőség (pl. sürgősségi szolgáltatások)'
    ],
    'competitor_conquest' => [
        'name' => '⚔️ Konkurens Meghódítás',
        'description' => 'Hirdetés konkurens cégnevekre - elcsábítod az ügyfeleiket',
        'difficulty' => 'advanced',
        'budget_impact' => 'medium',
        'settings' => [
            'keywords' => 'Konkurens cégnevek + "vélemény", "alternatíva"',
            'ad_copy' => 'Összehasonlító üzenet (ne legyen becsmérlő!)',
            'landing' => 'Külön landing page: "Miért mi vagyunk jobbak"'
        ],
        'tips' => [
            'NE használd a konkurens nevét a hirdetésben (trademark)!',
            'Fókuszálj az előnyeidre, ne a hibáikra',
            'Készíts összehasonlító táblázatot a landing-en',
            'A Quality Score alacsonyabb lesz - számolj vele'
        ],
        'when_to_use' => 'Ha van egyértelmű versenyelőnyöd (ár, gyorsaság, garancia)'
    ],
    'mobile_domination' => [
        'name' => '📱 Mobil Dominancia',
        'description' => 'Szolgáltatóknál a keresések 80%+ mobilról jön',
        'difficulty' => 'easy',
        'budget_impact' => 'medium',
        'settings' => [
            'mobile_bid' => '+30-50%',
            'ad_type' => 'Call-Only hirdetések KÖTELEZŐ',
            'extensions' => 'Call + Location extension',
            'landing' => 'Mobile-first, gyors betöltés (<3 sec)'
        ],
        'tips' => [
            'Call-Only és hagyományos hirdetés külön kampányban',
            'Click-to-call gomb JÓL LÁTHATÓ',
            'Ne legyen form a mobilos landing-en, csak telefon',
            'Teszteld a saját telefonodon!'
        ],
        'when_to_use' => 'Sürgősségi szolgáltatások, helyi szolgáltatók'
    ],
    'dayparting_profit' => [
        'name' => '⏰ Időzítés Optimalizálás',
        'description' => 'Adj többet amikor konvertálnak, kevesebbet amikor nem',
        'difficulty' => 'medium',
        'budget_impact' => 'low',
        'settings' => [
            'schedule' => 'Elemezd mikor jönnek a konverziók',
            'night_bid' => 'Éjszaka +20-30% (sürgősségi)',
            'monday_morning' => 'Hétfő reggel +15% (hétvégi problémák)',
            'friday_evening' => 'Péntek este -20% (kevés konverzió)'
        ],
        'tips' => [
            'Minimum 2-4 hét adat kell az elemzéshez',
            'Google Ads > Reports > Day & Hour',
            'Ne kapcsolj ki teljesen időszakot, csak csökkentsd',
            'Szezonalitás: nyáron klíma, télen fűtés stb.'
        ],
        'when_to_use' => 'Ha már van 50+ konverziós adatod'
    ],
    'local_hero' => [
        'name' => '📍 Lokális Hős Stratégia',
        'description' => 'Legyél a környék EGYETLEN választása',
        'difficulty' => 'easy',
        'budget_impact' => 'low',
        'settings' => [
            'location' => 'Szűk célzás: kerület/város',
            'keywords' => 'Lokális kulcsszavak: "[szolgáltatás] [kerület]"',
            'extensions' => 'Location extension KÖTELEZŐ',
            'gmb' => 'Google Business Profile optimalizálva'
        ],
        'tips' => [
            'Minden kerületre külön hirdetésszöveg',
            'Headline-ban a kerület neve: "Zuglói Vízszerelő"',
            'Google Maps hirdetések bekapcsolása',
            'Vélemények gyűjtése (GMB)!'
        ],
        'when_to_use' => 'Helyi szolgáltatók, kis fedezeti területtel'
    ],
    'remarketing_close' => [
        'name' => '🎯 Remarketing Lezárás',
        'description' => 'Aki már érdeklődött, azt zárd le!',
        'difficulty' => 'medium',
        'budget_impact' => 'low',
        'settings' => [
            'audience' => 'Weboldal látogatók (7-30 nap)',
            'bid' => '+50-100% remarketing listára',
            'ad_copy' => '"Még mindig gondolkodsz?" üzenet',
            'offer' => 'Limitált kedvezmény/ajándék'
        ],
        'tips' => [
            'Rövid remarketing ablak szolgáltatóknál (7-14 nap)',
            'Sürgősség: "Ma hívj, holnap kész"',
            'Display remarketing is (olcsó)',
            'Ne zaklasd túl - frequency cap!'
        ],
        'when_to_use' => 'Ha van elegendő weboldal forgalmad (min. 1000/hó)'
    ]
];

// Headline változók amiket cserélünk
$GLOBALS['headline_variables'] = [
    '{perc}' => ['28', '30', '25', '20', '35'],
    '{ev}' => ['10', '15', '20', '25', '12'],
    '{ar}' => ['9.900', '12.900', '15.000', '8.900', '19.900'],
    '{db}' => ['500', '1000', '2000', '800', '1500']
];

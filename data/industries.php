<?php
/**
 * AdMaster Pro - Industry Data
 * 
 * Iparág-specifikus adatok:
 * - USP-k
 * - Benchmarkok
 * - Negatív kulcsszavak
 * - Beállítási javaslatok
 */

return [
    'dugulas' => [
        'name' => 'Duguláselhárítás',
        'icon' => '🔧',
        'color' => '#E85D04',
        'usps' => [
            ['id' => '24h', 'text' => '24 órás szolgáltatás', 'impact' => 'high'],
            ['id' => '30min', 'text' => '30 percen belül nálad', 'impact' => 'high'],
            ['id' => 'hetvege', 'text' => 'Hétvégén és ünnepnapokon is', 'impact' => 'medium'],
            ['id' => 'garancia', 'text' => 'Garanciával dolgozunk', 'impact' => 'high'],
            ['id' => 'fixar', 'text' => 'Fix árak, rejtett költségek nélkül', 'impact' => 'high'],
            ['id' => 'szamla', 'text' => 'Számla azonnal', 'impact' => 'low'],
            ['id' => 'tapasztalat', 'text' => '15+ év tapasztalat', 'impact' => 'medium'],
            ['id' => 'modern', 'text' => 'Modern gépi technológia', 'impact' => 'medium']
        ],
        'benchmarks' => [
            'cpa_budapest' => '2.500-4.500 Ft',
            'cpa_videk' => '2.000-3.500 Ft',
            'cpc' => '150-400 Ft',
            'cpc_min' => 150,
            'cpc_max' => 400,
            'conversion_rate' => 5,
            'ctr' => '3.5%',
            'daily_budget_min' => 15000,
            'daily_budget_recommended' => 25000,
            'daily_budget_max' => 50000
        ],
        'schedule' => [
            'recommended' => '0-24 óra',
            'note' => 'Sürgősségi szolgáltatás - folyamatos hirdetés ajánlott',
            'bid_modifiers' => ['Éjszaka' => '+20%', 'Hétvége' => '+10%']
        ],
        'negative_keywords' => [
            'állás', 'munka', 'fizetés', 'tanfolyam', 'képzés', 'oktatás',
            'ingyen', 'házilag', 'házi praktika', 'video', 'youtube', 'hogyan',
            'duguláselhárító szer', 'lefolyótisztító', 'vegyszer', 'öntsünk',
            'wiki', 'wikipédia', 'fórum', 'gyakori kérdések'
        ],
        'keywords' => [
            'primary' => [
                'duguláselhárítás budapest',
                'duguláselhárító',
                'lefolyó tisztítás',
                'csőtisztítás',
                'wc dugulás'
            ],
            'secondary' => [
                'dugult lefolyó',
                'lassú lefolyó',
                'csatorna tisztítás'
            ]
        ]
    ],
    
    'vizszereles' => [
        'name' => 'Vízszerelés',
        'icon' => '💧',
        'color' => '#0077B6',
        'usps' => [
            ['id' => '24h', 'text' => '24 órás vízszerelő szolgálat', 'impact' => 'high'],
            ['id' => 'gyors', 'text' => 'Gyors kiszállás', 'impact' => 'high'],
            ['id' => 'hetvege', 'text' => 'Hétvégén is elérhető', 'impact' => 'medium'],
            ['id' => 'garancia', 'text' => 'Garanciális munkavégzés', 'impact' => 'high'],
            ['id' => 'fixar', 'text' => 'Előre megbeszélt árak', 'impact' => 'high'],
            ['id' => 'szamla', 'text' => 'Azonnali számla', 'impact' => 'low'],
            ['id' => 'tapasztalat', 'text' => 'Szakképzett vízszerelők', 'impact' => 'medium'],
            ['id' => 'komplex', 'text' => 'Teljes körű vízszerelés', 'impact' => 'medium']
        ],
        'benchmarks' => [
            'cpa_budapest' => '3.000-5.000 Ft',
            'cpa_videk' => '2.500-4.000 Ft',
            'cpc' => '180-450 Ft',
            'cpc_min' => 180,
            'cpc_max' => 450,
            'conversion_rate' => 4.5,
            'ctr' => '3.2%',
            'daily_budget_min' => 12000,
            'daily_budget_recommended' => 20000,
            'daily_budget_max' => 40000
        ],
        'schedule' => [
            'recommended' => '0-24 óra (sürgős) / 6:00-20:00 (tervezett)',
            'note' => 'Kombináld a sürgősségi és tervezett munkákat',
            'bid_modifiers' => ['Éjszaka' => '+15%', 'Hétvége' => '+10%']
        ],
        'negative_keywords' => [
            'állás', 'munka', 'fizetés', 'tanfolyam', 'képzés',
            'ingyen', 'házilag', 'video', 'youtube', 'szerszám',
            'vízszerelő tanfolyam', 'vízszerelő képzés', 'vízvezeték szerelő állás'
        ],
        'keywords' => [
            'primary' => [
                'vízszerelő budapest',
                'vízszerelés',
                'csőtörés',
                'vízszivárgás'
            ],
            'secondary' => [
                'kazán szerelés',
                'csap csere',
                'wc tartály javítás'
            ]
        ]
    ],
    
    'biztonsag' => [
        'name' => 'Biztonsági Rendszerek',
        'icon' => '🔒',
        'color' => '#2D3748',
        'usps' => [
            ['id' => 'tavfelugyelet', 'text' => '24/7 távfelügyelet', 'impact' => 'high'],
            ['id' => 'telepites', 'text' => 'Professzionális telepítés', 'impact' => 'high'],
            ['id' => 'garancia', 'text' => '2 év garancia', 'impact' => 'high'],
            ['id' => 'ingyenes', 'text' => 'Ingyenes felmérés', 'impact' => 'high'],
            ['id' => 'app', 'text' => 'Okostelefonos alkalmazás', 'impact' => 'medium'],
            ['id' => 'reakcio', 'text' => '5 perces reakcióidő', 'impact' => 'high'],
            ['id' => 'tapasztalat', 'text' => '10+ év tapasztalat', 'impact' => 'medium'],
            ['id' => 'hazai', 'text' => 'Magyar tulajdonú cég', 'impact' => 'low']
        ],
        'benchmarks' => [
            'cpa_budapest' => '6.000-12.000 Ft',
            'cpa_videk' => '5.000-10.000 Ft',
            'cpc' => '200-600 Ft',
            'cpc_min' => 200,
            'cpc_max' => 600,
            'conversion_rate' => 3,
            'ctr' => '2.8%',
            'daily_budget_min' => 10000,
            'daily_budget_recommended' => 18000,
            'daily_budget_max' => 35000
        ],
        'schedule' => [
            'recommended' => '6:00-22:00',
            'note' => 'Hosszabb döntési ciklus - remarketing fontos!',
            'bid_modifiers' => ['Éjszaka' => '0%', 'Hétvége' => '-10%']
        ],
        'negative_keywords' => [
            'állás', 'munka', 'fizetés', 'tanfolyam', 'képzés',
            'ingyen letöltés', 'szoftver', 'crack', 'torrent',
            'biztonsági őr', 'testőr', 'személyvédelem', 'biztonsági őr állás'
        ],
        'keywords' => [
            'primary' => [
                'riasztó szerelés',
                'kamera rendszer telepítés',
                'távfelügyelet',
                'lakásriasztó'
            ],
            'secondary' => [
                'biztonsági kamera',
                'riasztó rendszer',
                'beléptetőrendszer'
            ]
        ]
    ]
];

// Univerzális Negatív Kulcsszó Listák - minden kampányhoz
$GLOBALS['universal_negatives'] = [
    'diy' => [
        'name' => '❌ DIY / Csináld magad',
        'description' => 'Kiszűri azokat, akik maguk akarják megoldani',
        'keywords' => [
            'házilag', 'otthon', 'hogyan kell', 'hogyan', 'video', 'videó', 'youtube',
            'fórum', 'gyakori kérdések', 'útmutató', 'leírás', 'tippek', 'trükkök',
            'csináld magad', 'diy', 'saját kezűleg', 'magamnak', 'házi praktika',
            'recept', 'módszer', 'technika', 'ötlet', 'ötletek', 'megoldás otthon',
            'lépésről lépésre', 'bemutató', 'tutorial', 'tanács', 'tanácsok'
        ]
    ],
    'free' => [
        'name' => '❌ Ingyen / Olcsó kereső',
        'description' => 'Kiszűri a fizetni nem akaró érdeklődőket',
        'keywords' => [
            'ingyen', 'ingyenes', 'olcsón', 'olcsó', 'legolcsóbb', 'akciós', 'akció',
            'kedvezményes', 'használt', 'bontott', 'felújított', 'másodkézből',
            'apróhirdetés', 'vatera', 'jófogás', 'marketplace', 'facebook piac',
            'eladó', 'cserél', 'kölcsön', 'kölcsönöz', 'bérbe', 'albérlet',
            'filléres', 'potom', 'ajándék', 'nyeremény', 'sorsolás'
        ]
    ],
    'career' => [
        'name' => '❌ Oktatás / Karrier',
        'description' => 'Kiszűri az álláskeresőket és tanulókat',
        'keywords' => [
            'állás', 'munka', 'munkahely', 'fizetés', 'bér', 'órabér', 'karrierépítés',
            'tanfolyam', 'képzés', 'oktatás', 'iskola', 'egyetem', 'diploma',
            'jogszabály', 'törvény', 'rendelet', 'szabályzat', 'előírás',
            'wikipédia', 'wiki', 'lexikon', 'enciklopédia', 'fogalma', 'definíció',
            'szakdolgozat', 'diplomamunka', 'kutatás', 'statisztika', 'elemzés'
        ]
    ],
    'competitors' => [
        'name' => '❌ Versenytársak / Más cégek',
        'description' => 'Kiszűri a konkurens brand nevekre keresőket',
        'keywords' => [
            'vélemény', 'vélemények', 'tapasztalat', 'tapasztalatok', 'panasz',
            'reklamáció', 'fórum', 'hozzászólás', 'értékelés', 'teszt',
            'összehasonlítás', 'vs', 'vagy', 'melyik jobb', 'alternatíva'
        ]
    ],
    'info_seekers' => [
        'name' => '❌ Információkereső',
        'description' => 'Kiszűri akik csak tájékozódnak',
        'keywords' => [
            'mi az', 'mi a', 'mit jelent', 'jelentése', 'definíció',
            'mennyi ideig', 'meddig tart', 'átlagosan', 'általában',
            'érdemes', 'megéri', 'kell', 'kötelező', 'muszáj',
            'blog', 'cikk', 'poszt', 'újság', 'hírek', 'hír'
        ]
    ]
];

// Google Ads Script Sablonok
$GLOBALS['script_templates'] = [
    'link_checker' => [
        'name' => '🔗 Link Checker Script',
        'description' => 'Ellenőrzi óránként, hogy működik-e a landing page. Ha 404-es vagy 500-as hibát talál, e-mailben értesít.',
        'category' => 'monitoring'
    ],
    'overdelivery_alert' => [
        'name' => '💰 Overdelivery Alert',
        'description' => 'E-mailt küld, ha a költés délig eléri a napi keret 80%-át.',
        'category' => 'budget'
    ],
    'anomaly_detector' => [
        'name' => '🚨 Account Anomaly Detector',
        'description' => 'Jelez, ha a konverziók száma hirtelen 0-ra esik vagy drasztikusan változik.',
        'category' => 'monitoring'
    ],
    'pause_low_performers' => [
        'name' => '⏸️ Pause Low Performers',
        'description' => 'Automatikusan szünetelteti azokat a kulcsszavakat, amik 50+ kattintás után sem konvertáltak.',
        'category' => 'optimization'
    ],
    'bid_by_hour' => [
        'name' => '⏰ Bid by Hour',
        'description' => 'Óra alapján módosítja a bid-eket (pl. éjszaka +20%, reggel -10%).',
        'category' => 'bidding'
    ],
    'search_query_miner' => [
        'name' => '🔍 Search Query Miner',
        'description' => 'Kigyűjti a magas konverziójú keresési kifejezéseket és e-mailben küldi a listát.',
        'category' => 'optimization'
    ]
];

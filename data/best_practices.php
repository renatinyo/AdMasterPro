<?php
/**
 * AdMaster Pro - Best Practices (Nyertes Hirdetés Minták)
 * 
 * Ezek a valós, magas CTR-ű, konvertáló hirdetés szövegek
 * az AI few-shot tanítására szolgálnak.
 * 
 * SZABÁLY: Az AI NEM másolhatja ezeket 1:1-ben!
 * Inspirációként használja a stílust, hangnemet, szerkezetet.
 */

return [
    // =========================================
    // DUGULÁSELHÁRÍTÁS
    // =========================================
    'dugulas' => [
        'winning_headlines' => [
            // Sürgősség + Konkrét ígéret
            '30 Perc és Ott Vagyunk!',
            'Dugulás? Most Hívj, Ma Kész!',
            // Figyelemfelkeltő tiltás/kérdés
            'Ne Önts Rá Vegyszert!',
            'Dugulás Éjjel? Felvesszük!',
            // Ár + Bizalom
            'Fix 12.000 Ft-tól + Garancia',
            'Kiszállás 0 Ft Budapesten',
            // Social proof
            'A Szomszédod is Minket Hívott',
            '4200+ Sikeres Javítás Idén',
        ],
        'winning_descriptions' => [
            // PAS (Problem-Agitation-Solution)
            'Nem folyik le a víz? Ne kísérletezz vegyszerrel, mert drágább lesz a javítás. Gépi tisztítás 30 percen belül.',
            // Features→Benefits
            'Kamerás csőátvizsgálás - pontosan látjuk hol a baj. Bontás nélküli javítás, tiszta munka, garanciával.',
            // Social proof + CTA
            '15 éve a budapestiek bizalmát élvezzük. Hívj most, és ma este már szabadon folyik a lefolyód!',
            // Urgency + Price anchor
            'Duguláselhárítás fix áron, rejtett költségek nélkül. Hétvégén, ünnepnapon is! Számla azonnal a helyszínen.',
        ],
        'hooks' => [
            'fear' => 'Mi történik ha nem hívod: a víz visszajön, a bűz terjed, a javítás egyre drágább lesz.',
            'social_proof' => 'Több ezer elégedett ügyfél, a Google vélemények magukért beszélnek.',
            'urgency' => 'Minden perc számít - amíg gondolkodsz, a dugulás egyre rosszabb.',
            'price_anchor' => 'Egy hivatásos duguláselhárítás olcsóbb mint egy elromlott csőcsere.',
        ],
    ],

    // =========================================
    // VÍZSZERELÉS
    // =========================================
    'vizszereles' => [
        'winning_headlines' => [
            'Csőtörés? 30 Perc és Ott!',
            'Ne Várd Meg Amíg Beázik!',
            'Fix Áras Vízszerelő BP',
            'Éjjel-Nappal Felvesszük!',
            '0 Ft Kiszállás Budapesten',
            'Garanciával Dolgozunk!',
            '15+ Év Szakmai Tapasztalat',
            'Csapcsere Akár Ma Délután',
        ],
        'winning_descriptions' => [
            'Folyik a víz és nincs idő várni? Szakképzett vízszerelőink 30 percen belül a helyszínen. Garancia minden munkára!',
            'Csőtörés, szivárgás, kazán hiba? Előre megmondjuk az árat, rejtett költség nincs. Hétvégén is elérhetők vagyunk.',
            'Budapest egész területén gyors kiszállás. Modern eszközökkel dolgozunk, így nincs felesleges bontás. Számla azonnal.',
            'Egy szivárgó csap napi 30 liter vizet pazarol - és a számládat növeli. Hívj most, ma megoldjuk!',
        ],
        'hooks' => [
            'fear' => 'Egy kis szivárgásból napok alatt komoly vízkár lesz. A beázás javítása 10x drágább.',
            'social_proof' => 'Budapesten a legtöbb társasházkezelő minket hív elsőnek.',
            'urgency' => 'A víz nem vár - minden perccel nagyobb a kár.',
            'price_anchor' => 'Egy profi javítás most olcsóbb mint egy festő-mázoló később.',
        ],
    ],

    // =========================================
    // BIZTONSÁGI RENDSZEREK
    // =========================================
    'biztonsag' => [
        'winning_headlines' => [
            'Betörtek a Szomszédba. Te?',
            'Riasztó Nélkül = Kockázat',
            'Kamera Rendszer 89.900 Ft',
            '24/7 Távfelügyelet Havi 5990',
            'Ingyenes Felmérés + Árajánlat',
            'Ma Rendeled, Holnap Védve!',
            '5 Perces Reakcióidő!',
            'Okos Riasztó: Telefonra Szól',
        ],
        'winning_descriptions' => [
            'Tavaly 47.000 betörés volt Magyarországon. A riasztóval védett otthonokba 90%-kal kevesebbet törnek be. Kérj ajánlatot!',
            'Profi kamerarendszer + 24/7 központi felügyelet = teljes biztonság. Telepítés akár 48 órán belül. Részletfizetés elérhető.',
            'Az otthonod védelmét nem szabad a véletlenre bízni. Ingyenes helyszíni felmérés és személyre szabott biztonsági terv.',
            'Okostelefonról látod ki csengetett, mi történik otthon. Riasztás esetén 5 percen belül a helyszínen a járőr.',
        ],
        'hooks' => [
            'fear' => 'A betörők a felkészületlen otthonokat célozzák. Te felkészült vagy?',
            'social_proof' => 'A környéken már 200+ család választott minket.',
            'urgency' => 'A betörési szezon most indul - ne hagyd az utolsó pillanatra.',
            'price_anchor' => 'Egy riasztó havi 5.990 Ft - egy betörés káta átlagosan 1.200.000 Ft.',
        ],
    ],

    // =========================================
    // ZÁRSZERVÍZ
    // =========================================
    'zarszerviz' => [
        'winning_headlines' => [
            'Kizártad Magad? 20 Perc!',
            'Ne Törd Be! Hívj Inkább!',
            'Zárnyitás 8.900 Ft-tól',
            'Éjjel 2? Nem Gond, Megyünk!',
            'Ajtó Sértetlen Marad!',
            'Rendőrségi Igazolás Kész',
            'Non-Stop Zárszervíz BP',
            'Kulcs Bent? Megoldjuk!',
        ],
        'winning_descriptions' => [
            'Kizártad magad és pánikban vagy? Nyugi! Kármentesen nyitunk, az ajtó sértetlen marad. Éjjel-nappal, 20 percen belül.',
            'Profi zárnyitás, zárcsere és biztonsági zárak telepítése. Fix árak, számla a helyszínen. Rendőrségi igazolás kiadható.',
            'Ne próbáld magad kinyitni - a DIY kísérlet többe kerül mint a profi! Hívj most, 20 perc és szabad az út.',
            'Zárcsere betörés után? Biztonsági zárat szerelünk azonnal, hogy újra biztonságban érezd magad. Garancia 2 év.',
        ],
        'hooks' => [
            'fear' => 'Ha betörő járt nálad, percek alatt újra bejuthat a régi záron.',
            'social_proof' => 'Budapesten a rendőrség is minket ajánl zárnyitásra.',
            'urgency' => 'Az utcán állsz, hideg van - ne várd meg a reggelt.',
            'price_anchor' => 'Egy zárnyitás 8.900 Ft - egy ajtócsere 80.000+.',
        ],
    ],

    // =========================================
    // ÁLTALÁNOS (ha nincs iparág-specifikus)
    // =========================================
    'general' => [
        'winning_headlines' => [
            'Kérjen Ajánlatot Most!',
            'Profi Megoldás Garanciával',
            'Hívjon, Ma Megoldjuk!',
            'Fix Ár, 0 Rejtett Költség',
            'Elégedett Ügyfelek 1000+',
            'Gyors és Megbízható',
        ],
        'winning_descriptions' => [
            'Tapasztalt szakemberek, gyors kiszállás, garancia minden munkára. Kérjen ingyenes árajánlatot most!',
            'Ne keressen tovább - nálunk fix áron, rejtett költségek nélkül kap profi megoldást. Hívjon!',
        ],
        'hooks' => [
            'fear' => 'A halogatás mindig drágább lesz.',
            'social_proof' => 'Már több ezer elégedett ügyfél.',
            'urgency' => 'Ma hívjon, holnap megoldjuk.',
            'price_anchor' => 'Olcsóbb a megelőzés mint a javítás.',
        ],
    ],
];

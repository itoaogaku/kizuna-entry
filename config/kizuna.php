<?php

// サイトに表示する運営団体の情報と、過去の大会結果へのリンク
return [
    'organization' => [
        'name' => 'アスリートキャリアセンター',
        'postal_code' => '194-0021',
        'address' => '東京都町田市中町3−9−12−509',
        'fax' => '042-722-2930',
        'email' => 'info@acckizuna.com',
        'home_url' => env('KIZUNA_HOME_URL', 'https://acckizuna.com/'),
    ],

    // 領収書（入金が完了したら自動で発行し、入金完了メールに添付する）
    'receipt' => [
        'issuer' => env('KIZUNA_RECEIPT_ISSUER', '株式会社アスリートキャリアセンター'),
        // 適格請求書発行事業者の登録番号。領収書に登録番号と消費税額（10%・内税）を載せる（空にすると載せない）
        'registration_number' => env('KIZUNA_INVOICE_NUMBER', 'T6012301013979'),
        'address' => env('KIZUNA_RECEIPT_ADDRESS', '〒194-0021　東京都町田市中町3-9-12-509'),
        'phone' => env('KIZUNA_RECEIPT_PHONE', '042-722-2930'),
        'tax_rate' => 10,
    ],

    // プログラム用Excelで組分け案を作るときの、種目ごとの1組の人数（運営がExcelで調整する前提の目安）
    'heat_size' => [
        '800m' => 12,
        '1500m' => 20,
        '3000mSC' => 18,
        '3000m' => 40,
        '5000m' => 42,
        '10000m' => 45,
        'default' => 40,
    ],

    // 最小遂行人数（これに満たないレースはプログラム作成画面で知らせる）
    'min_participants' => 3,

    // 大会要項の「競技会担当者（お問い合わせ）」
    'contact' => [
        'name' => env('KIZUNA_CONTACT_NAME', 'アスリートキャリアセンター絆ランニング倶楽部 事務局 伊藤雅一'),
        'phone' => env('KIZUNA_CONTACT_PHONE', '090-8315-6008'),
        'email' => env('KIZUNA_CONTACT_EMAIL', 'kizuna.taikai@gmail.com'),
    ],

    // 過去の大会結果（年度 => 結果ページのURL）。
    // URLが空の年度は、このシステムの大会開催ページのその年度を表示する。
    'past_results' => [
        2021 => null,
        2022 => null,
        2023 => null,
        2024 => null,
        2025 => null,
    ],
];

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

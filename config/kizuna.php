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

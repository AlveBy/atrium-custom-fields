<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Qo\'shimcha maydonlar',
    'create' => 'Maydon qo\'shish',
    'button' => 'Maydonlar (:count)',
    'all_sites' => 'Barcha saytlar',
    'types' => [
        'string' => 'Satr',
        'text' => 'Matn',
        'number' => 'Butun son',
        'decimal' => 'O\'nli son',
        'boolean' => 'Ha / yo\'q',
        'date' => 'Sana',
        'select' => 'Ro\'yxat',
        'multiselect' => 'Ko\'p tanlov',
        'link' => 'Yozuvga havola',
        'file' => 'Fayl',
        'image' => 'Rasm',
    ],
    'fields' => [
        'module' => 'Modul',
        'label' => 'Nomi',
        'code' => 'Kodi',
        'type' => 'Turi',
        'options' => 'Variantlar',
        'link_module' => 'Havola moduli',
        'hint' => 'Izoh',
        'tab' => 'Yorliq',
        'required' => 'Majburiy',
        'show_in_list' => 'Ro\'yxatda ko\'rsatish',
        'filterable' => 'Filtrlanadigan',
        'sortable' => 'Saralanadigan',
        'searchable' => 'Qidiriladigan',
        'translatable' => 'Tarjima qilinadigan',
        'per_site' => 'Har sayt uchun alohida',
        'site' => 'Sayt',
    ],
    'hints' => [
        'fixed' => 'Maydon yaratilgandan keyin o\'zgarmaydi.',
        'code' => 'Lotin harflari, raqamlar va _, birinchisi harf: maydonning API, filtrlar va shablonlardagi nomi.',
        'options' => 'Har qatorda bitta variant: qiymat = Yorliq, yoki faqat yorliq.',
        'link_module' => 'Havola uchun: yozuvlarini ko\'rsatadigan modul.',
        'tab' => 'Modul shaklining yorligʻi, oʻziniki yoki «Yorliqlar»da qoʻshilgan; bunday yorliq boʻlmasa, maydon birinchisiga tushadi.',
        'filterable' => 'Do\'kon panelining filtrlari va hisoblagichlari: qiymatlar tezkor indeksga ko\'chiriladi.',
        'sortable' => 'Ro\'yxat va API shu bo\'yicha saralanadi.',
        'searchable' => 'Ro\'yxatdagi qidiruv bu yerga ham qaraydi.',
        'translatable' => 'Har kontent tili uchun alohida qiymat.',
        'per_site' => 'Har sayt uchun alohida qiymat.',
        'site' => 'Maydonni faqat bitta saytda ko\'rsatish.',
    ],
    'errors' => [
        'fixed' => 'O\'zgarmaydi: yozuvlar qiymatlarini shu kod va turda saqlaydi.',
        'code_taken' => 'Modulda bu kodli maydon allaqachon bor.',
        'code_column' => 'Bu nomni modul allaqachon ishlatgan.',
        'translatable' => 'Faqat satr yoki matn tarjima qilinadigan bo\'la oladi.',
        'filterable' => 'Matn, fayl yoki rasm bo\'yicha filtrlab bo\'lmaydi.',
        'sortable' => 'Faqat yagona qiymat bo\'yicha saralash mumkin.',
        'searchable' => 'Faqat har sayt uchun alohida bo\'lmagan satr yoki matn bo\'yicha qidirish mumkin.',
        'options' => 'Variantlarni har qatorga bittadan yozing.',
        'link_module' => 'Havola olib boradigan modulni tanlang.',
        'promoted' => 'Maydon :column ustunida: barcha tillar va saytlar uchun bitta qiymat.',
    ],
    'tabs' => [
        'module' => 'Oʻz yorliqlari',
        'create' => 'Yorliq qoʻshish',
        'button' => 'Yorliqlar (:count)',
        'hints' => [
            'code' => 'Lotin harflari, raqamlar va _, harfdan boshlanadi. Maydon oʻz yorligʻini shu kod bilan nomlaydi.',
            'sort' => 'Yorliqning shakl yorliqlari orasidagi oʻrni: son qanchalik kichik boʻlsa, boshiga shunchalik yaqin. Modulning oʻz yorliqlari 0, 10, 20 … deb raqamlangan; yangisi oxirgi boʻladi (500).',
            'site' => 'Yorliqni faqat bitta saytda koʻrsatish.',
        ],
        'errors' => [
            'code_taken' => 'Modul shaklida bunday kodli yorliq allaqachon bor.',
        ],
    ],
];

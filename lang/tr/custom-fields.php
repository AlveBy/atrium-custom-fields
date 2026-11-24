<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Özel alanlar',
    'create' => 'Alan ekle',
    'button' => 'Alanlar (:count)',
    'all_sites' => 'Tüm siteler',
    'types' => [
        'string' => 'Satır',
        'text' => 'Metin',
        'number' => 'Tam sayı',
        'decimal' => 'Ondalık sayı',
        'boolean' => 'Evet / hayır',
        'date' => 'Tarih',
        'select' => 'Liste',
        'multiselect' => 'Çoklu seçim',
        'link' => 'Kayda bağlantı',
        'file' => 'Dosya',
        'image' => 'Görsel',
    ],
    'fields' => [
        'module' => 'Modül',
        'label' => 'Ad',
        'code' => 'Kod',
        'type' => 'Tür',
        'options' => 'Seçenekler',
        'link_module' => 'Bağlı modül',
        'hint' => 'İpucu',
        'tab' => 'Sekme',
        'required' => 'Zorunlu',
        'show_in_list' => 'Listede göster',
        'filterable' => 'Filtrelenebilir',
        'sortable' => 'Sıralanabilir',
        'searchable' => 'Aranabilir',
        'translatable' => 'Çevrilebilir',
        'per_site' => 'Siteye özel',
        'site' => 'Site',
    ],
    'hints' => [
        'fixed' => 'Alan oluşturulduktan sonra değişmez.',
        'code' => 'Latin harfler, rakamlar ve _, harfle başlar: alanın API, filtreler ve şablonlardaki adı.',
        'options' => 'Satır başına bir seçenek: değer = Etiket, ya da yalnızca etiket.',
        'link_module' => 'Bağlantı için: kayıtlarını gösterdiği modül.',
        'tab' => 'Modül formunun sekmesi, kendisinin ya da «Sekmeler»de eklenen; yoksa alan ilk sekmeye gider.',
        'filterable' => 'Mağaza panelinin filtreleri ve sayıları: değerler hızlı bir dizine kopyalanır.',
        'sortable' => 'Liste ve API buna göre sıralanabilir.',
        'searchable' => 'Liste araması buraya da bakar.',
        'translatable' => 'Her içerik dili için bir değer.',
        'per_site' => 'Her site için bir değer.',
        'site' => 'Alanı yalnızca bir sitede göster.',
    ],
    'errors' => [
        'fixed' => 'Değiştirilemez: kayıtlar değerlerini bu kod ve türle saklar.',
        'code_taken' => 'Modülde bu kodla bir alan zaten var.',
        'code_column' => 'Modül bu adı zaten kullanıyor.',
        'translatable' => 'Yalnızca satır veya metin çevrilebilir.',
        'filterable' => 'Metin, dosya veya görsele göre filtrelenemez.',
        'sortable' => 'Yalnızca tek bir değere göre sıralanabilir.',
        'searchable' => 'Yalnızca siteye özel olmayan satır veya metin aranabilir.',
        'options' => 'Seçenekleri satır başına bir tane yazın.',
        'link_module' => 'Bağlantının gösterdiği modülü seçin.',
        'promoted' => 'Alan :column sütununda: tüm diller ve siteler için tek değer.',
    ],
    'tabs' => [
        'module' => 'Özel sekmeler',
        'create' => 'Sekme ekle',
        'button' => 'Sekmeler (:count)',
        'hints' => [
            'code' => 'Latin harfler, rakamlar ve _; bir harfle başlamalı. Alan sekmesini bu kodla belirtir.',
            'sort' => 'Sekmenin formdaki sekmeler arasındaki yeri: sayı küçüldükçe başa yaklaşır. Modülün kendi sekmeleri 0, 10, 20 … diye numaralanır; yeni sekme en sona gelir (500).',
            'site' => 'Sekmeyi yalnızca bir sitede göster.',
        ],
        'errors' => [
            'code_taken' => 'Modülün formunda bu kodlu bir sekme zaten var.',
        ],
    ],
];

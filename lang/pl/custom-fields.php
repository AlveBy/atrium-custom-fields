<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Pola dodatkowe',
    'create' => 'Dodaj pole',
    'button' => 'Pola (:count)',
    'all_sites' => 'Wszystkie witryny',
    'types' => [
        'string' => 'Wiersz',
        'text' => 'Tekst',
        'number' => 'Liczba całkowita',
        'decimal' => 'Liczba dziesiętna',
        'boolean' => 'Tak / nie',
        'date' => 'Data',
        'select' => 'Lista',
        'multiselect' => 'Wielokrotny wybór',
        'link' => 'Powiązanie z rekordem',
        'file' => 'Plik',
        'image' => 'Obraz',
    ],
    'fields' => [
        'module' => 'Moduł',
        'label' => 'Nazwa',
        'code' => 'Kod',
        'type' => 'Typ',
        'options' => 'Opcje',
        'link_module' => 'Moduł powiązania',
        'hint' => 'Podpowiedź',
        'tab' => 'Zakładka',
        'required' => 'Wymagane',
        'show_in_list' => 'Pokaż na liście',
        'filterable' => 'Filtrowalne',
        'sortable' => 'Sortowalne',
        'searchable' => 'Przeszukiwalne',
        'translatable' => 'Tłumaczone',
        'per_site' => 'Osobne dla witryny',
        'site' => 'Witryna',
    ],
    'hints' => [
        'fixed' => 'Nie zmienia się po utworzeniu pola.',
        'code' => 'Litery łacińskie, cyfry i _, na początku litera: nazwa pola w API, filtrach i szablonach.',
        'options' => 'Jedna opcja w wierszu: wartość = Etykieta, albo sama etykieta.',
        'link_module' => 'Dla powiązania: moduł, którego rekordy wskazuje.',
        'tab' => 'Zakładka formularza modułu, własna lub dodana w „Zakładkach”; jeśli jej nie ma, pole trafi na pierwszą.',
        'filterable' => 'Filtry i liczniki panelu sklepu: wartości są kopiowane do szybkiego indeksu.',
        'sortable' => 'Listę i API można po nim sortować.',
        'searchable' => 'Wyszukiwanie na liście zagląda też tutaj.',
        'translatable' => 'Osobna wartość dla każdego języka treści.',
        'per_site' => 'Osobna wartość dla każdej witryny.',
        'site' => 'Pokazuj pole tylko na jednej witrynie.',
    ],
    'errors' => [
        'fixed' => 'Nie można zmienić: rekordy trzymają wartości pod tym kodem i w tym typie.',
        'code_taken' => 'Moduł ma już pole z tym kodem.',
        'code_column' => 'Moduł już używa tej nazwy.',
        'translatable' => 'Tłumaczone mogą być tylko wiersz i tekst.',
        'filterable' => 'Nie można filtrować po tekście, pliku ani obrazie.',
        'sortable' => 'Sortować można tylko po pojedynczej wartości.',
        'searchable' => 'Szukać można w wierszu lub tekście, nie osobnym dla witryny.',
        'options' => 'Podaj opcje, po jednej w wierszu.',
        'link_module' => 'Wybierz moduł, na który wskazuje powiązanie.',
        'promoted' => 'Pole leży w kolumnie :column: jedna wartość dla wszystkich języków i witryn.',
    ],
    'tabs' => [
        'module' => 'Własne zakładki',
        'create' => 'Dodaj zakładkę',
        'button' => 'Zakładki (:count)',
        'hints' => [
            'code' => 'Litery łacińskie, cyfry i _, zaczynając od litery. Pole wskazuje swoją zakładkę tym kodem.',
            'sort' => 'Miejsce zakładki wśród zakładek formularza: im mniejsza liczba, tym bliżej początku. Zakładki samego modułu są numerowane 0, 10, 20 …; nowa trafia na koniec (500).',
            'site' => 'Pokaż zakładkę tylko na jednej stronie.',
        ],
        'errors' => [
            'code_taken' => 'Formularz modułu ma już zakładkę o tym kodzie.',
        ],
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Zusatzfelder',
    'create' => 'Feld hinzufügen',
    'button' => 'Felder (:count)',
    'all_sites' => 'Alle Websites',
    'types' => [
        'string' => 'Zeile',
        'text' => 'Text',
        'number' => 'Ganzzahl',
        'decimal' => 'Dezimalzahl',
        'boolean' => 'Ja / Nein',
        'date' => 'Datum',
        'select' => 'Liste',
        'multiselect' => 'Mehrfachauswahl',
        'link' => 'Verknüpfung mit einem Eintrag',
        'file' => 'Datei',
        'image' => 'Bild',
    ],
    'fields' => [
        'module' => 'Modul',
        'label' => 'Name',
        'code' => 'Code',
        'type' => 'Typ',
        'options' => 'Optionen',
        'link_module' => 'Verknüpftes Modul',
        'hint' => 'Hinweis',
        'tab' => 'Reiter',
        'required' => 'Pflichtfeld',
        'show_in_list' => 'In der Liste zeigen',
        'filterable' => 'Filterbar',
        'sortable' => 'Sortierbar',
        'searchable' => 'Durchsuchbar',
        'translatable' => 'Übersetzbar',
        'per_site' => 'Je Website',
        'site' => 'Website',
    ],
    'hints' => [
        'fixed' => 'Nach dem Anlegen nicht mehr änderbar.',
        'code' => 'Lateinische Buchstaben, Ziffern und _, beginnend mit einem Buchstaben: der Name des Felds in API, Filtern und Templates.',
        'options' => 'Eine Option pro Zeile: Wert = Bezeichnung, oder nur die Bezeichnung.',
        'link_module' => 'Bei einer Verknüpfung: das Modul, auf dessen Einträge sie zeigt.',
        'tab' => 'Der Reiter im Formular des Moduls; fehlt er, kommt das Feld auf den ersten.',
        'filterable' => 'Filter und Trefferzahlen eines Shop-Panels: die Werte werden in einen schnellen Index kopiert.',
        'sortable' => 'Liste und API lassen sich danach sortieren.',
        'searchable' => 'Die Listensuche durchsucht es mit.',
        'translatable' => 'Ein Wert je Inhaltssprache.',
        'per_site' => 'Ein Wert je Website.',
        'site' => 'Das Feld nur auf einer Website zeigen.',
    ],
    'errors' => [
        'fixed' => 'Nicht änderbar: die Einträge speichern ihre Werte unter diesem Code und Typ.',
        'code_taken' => 'Das Modul hat schon ein Feld mit diesem Code.',
        'code_column' => 'Diesen Namen verwendet das Modul bereits.',
        'translatable' => 'Nur Zeile oder Text können übersetzbar sein.',
        'filterable' => 'Nach Text, Datei oder Bild kann nicht gefiltert werden.',
        'sortable' => 'Sortieren geht nur nach einem Einzelwert.',
        'searchable' => 'Durchsuchbar sind nur Zeile oder Text, nicht je Website.',
        'options' => 'Geben Sie die Optionen an, eine pro Zeile.',
        'link_module' => 'Wählen Sie das Modul, auf das die Verknüpfung zeigt.',
        'promoted' => 'Das Feld liegt in der Spalte :column: ein Wert für alle Sprachen und Websites.',
    ],
];

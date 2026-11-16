<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Custom fields',
    'create' => 'Add a field',
    'button' => 'Fields (:count)',
    'all_sites' => 'All sites',
    'types' => [
        'string' => 'Line',
        'text' => 'Text',
        'number' => 'Integer',
        'decimal' => 'Decimal',
        'boolean' => 'Yes / no',
        'date' => 'Date',
        'select' => 'List',
        'multiselect' => 'Multiple choice',
        'link' => 'Link to a record',
        'file' => 'File',
        'image' => 'Image',
    ],
    'fields' => [
        'module' => 'Module',
        'label' => 'Name',
        'code' => 'Code',
        'type' => 'Type',
        'options' => 'Options',
        'link_module' => 'Linked module',
        'hint' => 'Hint',
        'tab' => 'Tab',
        'required' => 'Required',
        'show_in_list' => 'Show in the list',
        'filterable' => 'Filterable',
        'sortable' => 'Sortable',
        'searchable' => 'Searchable',
        'translatable' => 'Translatable',
        'per_site' => 'Per site',
        'site' => 'Site',
    ],
    'hints' => [
        'fixed' => 'Cannot be changed once the field exists.',
        'code' => 'Latin letters, digits and _, starting with a letter: the name of the field in the API, filters and templates.',
        'options' => 'One option per line: value = Label, or just the label.',
        'link_module' => 'For a link: the module whose records it points to.',
        'tab' => 'The tab of the module\'s form; if the form has no such tab, the field goes to the first one.',
        'filterable' => 'Filters and facet counts of a shop panel: the values are copied into a fast index.',
        'sortable' => 'The list and the API can be sorted by it.',
        'searchable' => 'The list search looks into it.',
        'translatable' => 'A value for every content language.',
        'per_site' => 'A value for every site.',
        'site' => 'Show the field on one site only.',
    ],
    'errors' => [
        'fixed' => 'Cannot be changed: the records keep their values under this code and type.',
        'code_taken' => 'The module has a field with this code already.',
        'code_column' => 'The module uses this name already.',
        'translatable' => 'Only a line or a text can be translatable.',
        'filterable' => 'A text, file or image cannot be filtered by.',
        'sortable' => 'Only a single value can be sorted by.',
        'searchable' => 'Only a line or a text, not per site, can be searched.',
        'options' => 'List the options, one per line.',
        'link_module' => 'Choose the module the link points to.',
        'promoted' => 'The field lives in the column :column: one value for every language and site.',
    ],
];

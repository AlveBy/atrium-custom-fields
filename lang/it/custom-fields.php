<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Campi personalizzati',
    'create' => 'Aggiungi un campo',
    'button' => 'Campi (:count)',
    'all_sites' => 'Tutti i siti',
    'types' => [
        'string' => 'Riga',
        'text' => 'Testo',
        'number' => 'Numero intero',
        'decimal' => 'Numero decimale',
        'boolean' => 'Sì / no',
        'date' => 'Data',
        'select' => 'Elenco',
        'multiselect' => 'Scelta multipla',
        'link' => 'Collegamento a un record',
        'file' => 'File',
        'image' => 'Immagine',
    ],
    'fields' => [
        'module' => 'Modulo',
        'label' => 'Nome',
        'code' => 'Codice',
        'type' => 'Tipo',
        'options' => 'Opzioni',
        'link_module' => 'Modulo collegato',
        'hint' => 'Suggerimento',
        'tab' => 'Scheda',
        'required' => 'Obbligatorio',
        'show_in_list' => 'Mostra nell\'elenco',
        'filterable' => 'Filtrabile',
        'sortable' => 'Ordinabile',
        'searchable' => 'Ricercabile',
        'translatable' => 'Traducibile',
        'per_site' => 'Per sito',
        'site' => 'Sito',
    ],
    'hints' => [
        'fixed' => 'Non si può cambiare dopo la creazione del campo.',
        'code' => 'Lettere latine, cifre e _, iniziando con una lettera: il nome del campo nell\'API, nei filtri e nei template.',
        'options' => 'Un\'opzione per riga: valore = Etichetta, o solo l\'etichetta.',
        'link_module' => 'Per un collegamento: il modulo ai cui record punta.',
        'tab' => 'La scheda del modulo; se manca, il campo va sulla prima.',
        'filterable' => 'Filtri e conteggi di un pannello negozio: i valori vengono copiati in un indice veloce.',
        'sortable' => 'Elenco e API si possono ordinare per questo campo.',
        'searchable' => 'La ricerca dell\'elenco guarda anche qui.',
        'translatable' => 'Un valore per ogni lingua dei contenuti.',
        'per_site' => 'Un valore per ogni sito.',
        'site' => 'Mostra il campo su un solo sito.',
    ],
    'errors' => [
        'fixed' => 'Non si può cambiare: i record tengono i valori con questo codice e tipo.',
        'code_taken' => 'Il modulo ha già un campo con questo codice.',
        'code_column' => 'Il modulo usa già questo nome.',
        'translatable' => 'Solo una riga o un testo possono essere traducibili.',
        'filterable' => 'Non si può filtrare per testo, file o immagine.',
        'sortable' => 'Si può ordinare solo per un valore singolo.',
        'searchable' => 'Si cerca solo in una riga o un testo, non per sito.',
        'options' => 'Indica le opzioni, una per riga.',
        'link_module' => 'Scegli il modulo a cui punta il collegamento.',
        'promoted' => 'Il campo è nella colonna :column: un valore per tutte le lingue e i siti.',
    ],
];

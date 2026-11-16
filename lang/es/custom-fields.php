<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Campos personalizados',
    'create' => 'Añadir un campo',
    'button' => 'Campos (:count)',
    'all_sites' => 'Todos los sitios',
    'types' => [
        'string' => 'Línea',
        'text' => 'Texto',
        'number' => 'Número entero',
        'decimal' => 'Número decimal',
        'boolean' => 'Sí / no',
        'date' => 'Fecha',
        'select' => 'Lista',
        'multiselect' => 'Selección múltiple',
        'link' => 'Enlace a un registro',
        'file' => 'Archivo',
        'image' => 'Imagen',
    ],
    'fields' => [
        'module' => 'Módulo',
        'label' => 'Nombre',
        'code' => 'Código',
        'type' => 'Tipo',
        'options' => 'Opciones',
        'link_module' => 'Módulo enlazado',
        'hint' => 'Ayuda',
        'tab' => 'Pestaña',
        'required' => 'Obligatorio',
        'show_in_list' => 'Mostrar en la lista',
        'filterable' => 'Filtrable',
        'sortable' => 'Ordenable',
        'searchable' => 'Buscable',
        'translatable' => 'Traducible',
        'per_site' => 'Por sitio',
        'site' => 'Sitio',
    ],
    'hints' => [
        'fixed' => 'No se puede cambiar una vez creado el campo.',
        'code' => 'Letras latinas, cifras y _, empezando por una letra: el nombre del campo en la API, los filtros y las plantillas.',
        'options' => 'Una opción por línea: valor = Etiqueta, o solo la etiqueta.',
        'link_module' => 'Para un enlace: el módulo a cuyos registros apunta.',
        'tab' => 'La pestaña del formulario del módulo; si no existe, el campo va a la primera.',
        'filterable' => 'Filtros y recuentos de un panel de tienda: los valores se copian a un índice rápido.',
        'sortable' => 'La lista y la API se pueden ordenar por él.',
        'searchable' => 'La búsqueda de la lista también mira aquí.',
        'translatable' => 'Un valor por idioma de contenido.',
        'per_site' => 'Un valor por sitio.',
        'site' => 'Mostrar el campo solo en un sitio.',
    ],
    'errors' => [
        'fixed' => 'No se puede cambiar: los registros guardan sus valores con este código y tipo.',
        'code_taken' => 'El módulo ya tiene un campo con este código.',
        'code_column' => 'El módulo ya usa este nombre.',
        'translatable' => 'Solo una línea o un texto pueden ser traducibles.',
        'filterable' => 'No se puede filtrar por un texto, archivo o imagen.',
        'sortable' => 'Solo se puede ordenar por un valor único.',
        'searchable' => 'Solo se busca en una línea o un texto, no por sitio.',
        'options' => 'Indique las opciones, una por línea.',
        'link_module' => 'Elija el módulo al que apunta el enlace.',
        'promoted' => 'El campo está en la columna :column: un valor para todos los idiomas y sitios.',
    ],
];

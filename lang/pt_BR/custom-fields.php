<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Campos personalizados',
    'create' => 'Adicionar campo',
    'button' => 'Campos (:count)',
    'all_sites' => 'Todos os sites',
    'types' => [
        'string' => 'Linha',
        'text' => 'Texto',
        'number' => 'Número inteiro',
        'decimal' => 'Número decimal',
        'boolean' => 'Sim / não',
        'date' => 'Data',
        'select' => 'Lista',
        'multiselect' => 'Múltipla escolha',
        'link' => 'Vínculo com um registro',
        'file' => 'Arquivo',
        'image' => 'Imagem',
    ],
    'fields' => [
        'module' => 'Módulo',
        'label' => 'Nome',
        'code' => 'Código',
        'type' => 'Tipo',
        'options' => 'Opções',
        'link_module' => 'Módulo vinculado',
        'hint' => 'Dica',
        'tab' => 'Aba',
        'required' => 'Obrigatório',
        'show_in_list' => 'Mostrar na lista',
        'filterable' => 'Filtrável',
        'sortable' => 'Ordenável',
        'searchable' => 'Pesquisável',
        'translatable' => 'Traduzível',
        'per_site' => 'Por site',
        'site' => 'Site',
    ],
    'hints' => [
        'fixed' => 'Não pode mudar depois que o campo existe.',
        'code' => 'Letras latinas, dígitos e _, começando com letra: o nome do campo na API, nos filtros e nos templates.',
        'options' => 'Uma opção por linha: valor = Rótulo, ou só o rótulo.',
        'link_module' => 'Para um vínculo: o módulo cujos registros ele aponta.',
        'tab' => 'A aba do formulário do módulo; se não existir, o campo vai para a primeira.',
        'filterable' => 'Filtros e contagens de um painel de loja: os valores são copiados para um índice rápido.',
        'sortable' => 'A lista e a API podem ser ordenadas por ele.',
        'searchable' => 'A busca da lista também olha aqui.',
        'translatable' => 'Um valor por idioma de conteúdo.',
        'per_site' => 'Um valor por site.',
        'site' => 'Mostrar o campo em um só site.',
    ],
    'errors' => [
        'fixed' => 'Não pode mudar: os registros guardam os valores sob este código e tipo.',
        'code_taken' => 'O módulo já tem um campo com este código.',
        'code_column' => 'O módulo já usa este nome.',
        'translatable' => 'Só uma linha ou um texto podem ser traduzíveis.',
        'filterable' => 'Não dá para filtrar por texto, arquivo ou imagem.',
        'sortable' => 'Só dá para ordenar por um valor único.',
        'searchable' => 'Só se pesquisa em linha ou texto, não por site.',
        'options' => 'Informe as opções, uma por linha.',
        'link_module' => 'Escolha o módulo para o qual o vínculo aponta.',
        'promoted' => 'O campo está na coluna :column: um valor para todos os idiomas e sites.',
    ],
];

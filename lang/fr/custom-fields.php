<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Champs personnalisés',
    'create' => 'Ajouter un champ',
    'button' => 'Champs (:count)',
    'all_sites' => 'Tous les sites',
    'types' => [
        'string' => 'Ligne',
        'text' => 'Texte',
        'number' => 'Nombre entier',
        'decimal' => 'Nombre décimal',
        'boolean' => 'Oui / non',
        'date' => 'Date',
        'select' => 'Liste',
        'multiselect' => 'Choix multiple',
        'link' => 'Lien vers un enregistrement',
        'file' => 'Fichier',
        'image' => 'Image',
    ],
    'fields' => [
        'module' => 'Module',
        'label' => 'Nom',
        'code' => 'Code',
        'type' => 'Type',
        'options' => 'Options',
        'link_module' => 'Module lié',
        'hint' => 'Aide',
        'tab' => 'Onglet',
        'required' => 'Obligatoire',
        'show_in_list' => 'Afficher dans la liste',
        'filterable' => 'Filtrable',
        'sortable' => 'Triable',
        'searchable' => 'Recherchable',
        'translatable' => 'Traduisible',
        'per_site' => 'Par site',
        'site' => 'Site',
    ],
    'hints' => [
        'fixed' => 'Ne peut plus changer une fois le champ créé.',
        'code' => 'Lettres latines, chiffres et _, en commençant par une lettre : le nom du champ dans l\'API, les filtres et les gabarits.',
        'options' => 'Une option par ligne : valeur = Libellé, ou seulement le libellé.',
        'link_module' => 'Pour un lien : le module dont il désigne les enregistrements.',
        'tab' => 'L\'onglet du formulaire du module ; s\'il n\'existe pas, le champ va sur le premier.',
        'filterable' => 'Filtres et compteurs d\'un panneau de boutique : les valeurs sont copiées dans un index rapide.',
        'sortable' => 'La liste et l\'API peuvent être triées par ce champ.',
        'searchable' => 'La recherche de la liste y regarde aussi.',
        'translatable' => 'Une valeur par langue de contenu.',
        'per_site' => 'Une valeur par site.',
        'site' => 'N\'afficher le champ que sur un site.',
    ],
    'errors' => [
        'fixed' => 'Ne peut pas changer : les enregistrements gardent leurs valeurs sous ce code et ce type.',
        'code_taken' => 'Le module a déjà un champ avec ce code.',
        'code_column' => 'Le module utilise déjà ce nom.',
        'translatable' => 'Seuls une ligne ou un texte peuvent être traduisibles.',
        'filterable' => 'On ne peut pas filtrer par un texte, un fichier ou une image.',
        'sortable' => 'On ne peut trier que par une valeur unique.',
        'searchable' => 'On ne cherche que dans une ligne ou un texte, pas par site.',
        'options' => 'Indiquez les options, une par ligne.',
        'link_module' => 'Choisissez le module vers lequel pointe le lien.',
        'promoted' => 'Le champ est dans la colonne :column : une valeur pour toutes les langues et tous les sites.',
    ],
];

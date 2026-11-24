<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'カスタムフィールド',
    'create' => 'フィールドを追加',
    'button' => 'フィールド (:count)',
    'all_sites' => 'すべてのサイト',
    'types' => [
        'string' => '1行テキスト',
        'text' => 'テキスト',
        'number' => '整数',
        'decimal' => '小数',
        'boolean' => 'はい / いいえ',
        'date' => '日付',
        'select' => 'リスト',
        'multiselect' => '複数選択',
        'link' => 'レコードへのリンク',
        'file' => 'ファイル',
        'image' => '画像',
    ],
    'fields' => [
        'module' => 'モジュール',
        'label' => '名前',
        'code' => 'コード',
        'type' => '種類',
        'options' => '選択肢',
        'link_module' => 'リンク先モジュール',
        'hint' => 'ヒント',
        'tab' => 'タブ',
        'required' => '必須',
        'show_in_list' => '一覧に表示',
        'filterable' => '絞り込み',
        'sortable' => '並べ替え',
        'searchable' => '検索',
        'translatable' => '翻訳',
        'per_site' => 'サイトごと',
        'site' => 'サイト',
    ],
    'hints' => [
        'fixed' => 'フィールド作成後は変更できません。',
        'code' => '英字・数字・_ で、先頭は英字: API、フィルター、テンプレートでのフィールド名です。',
        'options' => '1行に1つ: 値 = ラベル、またはラベルのみ。',
        'link_module' => 'リンクの場合: 参照するレコードのモジュール。',
        'tab' => 'モジュールのフォームのタブ（自前のもの、または「タブ」で追加したもの）。存在しない場合は最初のタブに表示されます。',
        'filterable' => 'ショップの絞り込みと件数表示: 値は高速なインデックスにコピーされます。',
        'sortable' => '一覧と API をこのフィールドで並べ替えられます。',
        'searchable' => '一覧の検索がこのフィールドも対象にします。',
        'translatable' => 'コンテンツ言語ごとの値。',
        'per_site' => 'サイトごとの値。',
        'site' => '1つのサイトでのみ表示します。',
    ],
    'errors' => [
        'fixed' => '変更できません: レコードはこのコードと種類で値を保存しています。',
        'code_taken' => 'このコードのフィールドは既にあります。',
        'code_column' => 'この名前はモジュールで既に使われています。',
        'translatable' => '翻訳できるのは1行テキストとテキストだけです。',
        'filterable' => 'テキスト、ファイル、画像では絞り込めません。',
        'sortable' => '並べ替えできるのは単一の値だけです。',
        'searchable' => '検索できるのはサイトごとでない1行テキストかテキストだけです。',
        'options' => '選択肢を1行に1つずつ入力してください。',
        'link_module' => 'リンク先のモジュールを選んでください。',
        'promoted' => 'このフィールドは列 :column にあります: すべての言語とサイトで1つの値です。',
    ],
    'tabs' => [
        'module' => '独自のタブ',
        'create' => 'タブを追加',
        'button' => 'タブ（:count）',
        'hints' => [
            'code' => 'ラテン文字、数字、_ が使え、先頭は文字です。フィールドはこのコードで自分のタブを指定します。',
            'sort' => 'フォームのタブの中での位置。数字が小さいほど先頭に近くなります。モジュール自身のタブには 0、10、20 … の番号が付き、新しいタブは最後（500）になります。',
            'site' => 'このタブを1つのサイトだけに表示します。',
        ],
        'errors' => [
            'code_taken' => 'このモジュールのフォームには、すでにこのコードのタブがあります。',
        ],
    ],
];

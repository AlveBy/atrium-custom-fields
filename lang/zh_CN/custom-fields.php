<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => '自定义字段',
    'create' => '添加字段',
    'button' => '字段 (:count)',
    'all_sites' => '所有站点',
    'types' => [
        'string' => '单行文本',
        'text' => '文本',
        'number' => '整数',
        'decimal' => '小数',
        'boolean' => '是 / 否',
        'date' => '日期',
        'select' => '列表',
        'multiselect' => '多选',
        'link' => '关联记录',
        'file' => '文件',
        'image' => '图片',
    ],
    'fields' => [
        'module' => '模块',
        'label' => '名称',
        'code' => '代码',
        'type' => '类型',
        'options' => '选项',
        'link_module' => '关联模块',
        'hint' => '提示',
        'tab' => '标签页',
        'required' => '必填',
        'show_in_list' => '在列表中显示',
        'filterable' => '可筛选',
        'sortable' => '可排序',
        'searchable' => '可搜索',
        'translatable' => '可翻译',
        'per_site' => '按站点区分',
        'site' => '站点',
    ],
    'hints' => [
        'fixed' => '字段创建后不可更改。',
        'code' => '拉丁字母、数字和 _，以字母开头：字段在 API、筛选和模板中的名称。',
        'options' => '每行一个选项：值 = 标签，或仅写标签。',
        'link_module' => '用于关联：所指向记录的模块。',
        'tab' => '模块表单的标签页；若不存在，字段显示在第一个标签页。',
        'filterable' => '商店面板的筛选和计数：值会复制到快速索引中。',
        'sortable' => '列表和 API 可按此字段排序。',
        'searchable' => '列表搜索也会查找此字段。',
        'translatable' => '每种内容语言一个值。',
        'per_site' => '每个站点一个值。',
        'site' => '仅在一个站点显示此字段。',
    ],
    'errors' => [
        'fixed' => '不可更改：记录以此代码和类型保存值。',
        'code_taken' => '该模块已有此代码的字段。',
        'code_column' => '该模块已使用此名称。',
        'translatable' => '只有单行文本或文本可以翻译。',
        'filterable' => '无法按文本、文件或图片筛选。',
        'sortable' => '只能按单个值排序。',
        'searchable' => '只能搜索非按站点区分的单行文本或文本。',
        'options' => '请填写选项，每行一个。',
        'link_module' => '请选择关联指向的模块。',
        'promoted' => '该字段位于列 :column 中：所有语言和站点共用一个值。',
    ],
];

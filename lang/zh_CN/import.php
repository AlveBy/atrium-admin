<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Import of a module's records from CSV and Excel files.
 */
return [
    'title' => '导入',

    'index' => [
        'upload' => '上传文件',
        'hint' => 'CSV 或 Excel (xlsx)，最大 :size MB。第一行为列名。编码（UTF-8 或 windows-1251）和分隔符（分号或逗号）会自动识别。从此列表导出的文件可以原样导入回来。',
        'choose' => '要导入的文件',
        'submit' => '上传',
        'history' => '历史导入',
        'empty' => '尚未导入任何内容。',
        'file' => '文件',
        'status' => '状态',
        'result' => '结果',
        'counts' => '已创建 :created，已更新 :updated，错误 :failed',
        'user' => '操作人',
        'console' => '控制台',
        'date' => '时间',
    ],

    'upload' => [
        'file' => '文件',
    ],

    'statuses' => [
        'new' => '等待检查',
        'checking' => '检查中',
        'checked' => '已检查',
        'importing' => '导入中',
        'done' => '完成',
        'failed' => '失败',
        'cancelled' => '已停止',
    ],

    'delimiters' => [
        ';' => '分号',
        ',' => '逗号',
        "\t" => '制表符',
    ],

    'show' => [
        'back' => '全部导入',
        'file' => ':format，:encoding，分隔符：:delimiter，共 :rows 行',
        'workbook' => ':format，共 :rows 行',
        'checking' => '正在检查文件…',
        'importing' => '正在导入…',
        'progress' => '已处理 :processed / :total 行',
        'cancel' => '停止',
        'cancel_confirm' => '要停止吗？已导入的内容会保留。',
        'report' => '报告',
        'check_report' => '检查',
        'checked' => '检查已结束，尚未写入任何内容。导入将执行以下操作。',
        'done' => '导入已结束。',
        'cancelled' => '导入已停止，已导入的内容会保留。',
        'counts' => [
            'created' => '已创建',
            'updated' => '已更新',
            'skipped' => '已跳过',
            'failed' => '错误',
            'nodes' => '已创建树节点',
        ],
        'will' => [
            'created' => '将创建',
            'updated' => '将更新',
            'skipped' => '将跳过',
            'failed' => '错误',
            'nodes' => '将创建的树节点',
        ],
        'row' => '行',
        'outcome' => '处理结果',
        'reason' => '原因',
        'skipped_row' => '已跳过',
        'failed_row' => '错误',
        'unlisted' => '另有 :count 行已计入，但未列出。',
        'run' => '导入',
        'run_hint' => '有错误的行不会导入，其余内容按检查结果导入。',
        'mapping' => '列',
        'mapping_hint' => '每一列填入哪个字段，根据列名推测。单元格为空则保持字段原样。',
        'column' => '列',
        'unnamed' => '第 :number 列',
        'samples' => '前几行内容',
        'target' => '字段',
        'skip' => '— 不导入 —',
        'options' => '导入方式',
        'key_hint' => '值相同的记录会被更新，不存在的记录会被创建。',
        'separator_hint' => '用于在路径中分隔上级与下级：“服装 / 夹克”。',
        'check' => '检查',
        'check_again' => '重新检查',
        'check_hint' => '检查会读取整个文件，但不写入任何内容：可以看到将创建和更新多少条记录，以及哪些行有错误。',
    ],

    'settings' => [
        'mapping' => '列',
        'key' => '按以下字段查找记录',
        'mode' => '执行操作',
        'separator' => '路径分隔符',
    ],

    'modes' => [
        'both' => '创建新记录并更新已有记录',
        'create' => '仅创建新记录',
        'update' => '仅更新已有记录',
    ],

    'validation' => [
        'twice' => '一个字段只能对应一列，但被多次选择：:fields。',
        'key' => '按“:field”查找记录，但没有任何一列填入该字段。',
    ],

    'failures' => [
        'empty' => '文件中没有数据行。',
        'format' => '这不是 CSV 或 Excel (xlsx) 文件。旧的 .xls 文件可在 Excel 中另存为 .xlsx 或 CSV。',
        'unreadable' => '无法读取该 Excel 文件：文件可能已损坏或设有密码保护。',
        'crashed' => '服务器出错，导入已停止。错误已记入日志；已导入的内容会保留。',
        'missing' => '文件 :file 不存在。',
        'download' => '无法从 :url 下载文件。',
        'column' => '--map 中没有这样的列：“:column”。请写成“列名=字段”。',
        'option' => '该模块没有可用于 --option 的选项“:option”。',
        'module' => '模块“:module”已不存在。',
        'key' => '无法按“:key”查找记录：该模块没有这个字段。',
    ],

    'problems' => [
        'no_key' => '“:field”为空：无法查找或创建记录。',
        'bad_id' => '“:value”不是有效的 ID：必须是大于零的整数。',
        'exists' => '记录“:key”已存在，而只会创建新记录。',
        'missing' => '记录“:key”不存在，而只会更新已有记录。',
        'database' => '数据库拒绝了该行。错误已记入日志。',
        'not_bool' => '“:field”：“:value”既不是“是”也不是“否”。',
        'not_option' => '“:field”：没有选项“:value”。',
        'ambiguous_option' => '“:field”：有多个选项名为“:value”。',
        'tree_missing' => '“:field”：不存在“:value”。',
        'tree_ambiguous' => '“:field”：有多个名为“:value”：请写出完整路径，“上级 / :value”。',
        'image_url' => '图片地址无效：:url',
        'image_path' => '不允许使用该图片路径：:path',
        'image_type' => '这不是图片（JPEG, PNG, GIF, WebP, SVG）：:path',
        'image_missing' => '站点上没有图片 :path。',
        'image_host' => '图片位于内网地址，此类图片不会被下载：:url',
        'image_download' => '无法下载图片：:url',
        'image_large' => '图片超过 :max KB：:url',
    ],

    'console' => [
        'module' => '没有接受导入的模块“:module”。接受导入的模块：:modules。',
        'source' => '请指定文件或其网址。',
        'like' => '该模块不存在编号为 :id 的导入。',
        'report' => '行数：:processed。已创建：:created，已更新：:updated，已跳过：:skipped，错误：:failed，已创建树节点：:nodes。',
        'row' => '第 :row 行：:messages',
    ],
];

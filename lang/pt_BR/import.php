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
    'title' => 'Importação',

    'index' => [
        'upload' => 'Enviar um arquivo',
        'hint' => 'CSV ou Excel (xlsx), até :size MB. A primeira linha contém os nomes das colunas. A codificação (UTF-8 ou windows-1251) e o separador (ponto e vírgula ou vírgula) são reconhecidos automaticamente. Um arquivo exportado desta lista pode ser importado de volta como está.',
        'choose' => 'Arquivo para importar',
        'submit' => 'Enviar',
        'history' => 'Importações anteriores',
        'empty' => 'Nada foi importado ainda.',
        'file' => 'Arquivo',
        'status' => 'Status',
        'result' => 'Resultado',
        'counts' => ':created criados, :updated atualizados, :failed erros',
        'user' => 'Quem',
        'console' => 'console',
        'date' => 'Quando',
    ],

    'upload' => [
        'file' => 'arquivo',
    ],

    'statuses' => [
        'new' => 'Aguardando verificação',
        'checking' => 'Verificando',
        'checked' => 'Verificado',
        'importing' => 'Importando',
        'done' => 'Concluído',
        'failed' => 'Falhou',
        'cancelled' => 'Interrompido',
    ],

    'delimiters' => [
        ';' => 'ponto e vírgula',
        ',' => 'vírgula',
        "\t" => 'tabulação',
    ],

    'show' => [
        'back' => 'Todas as importações',
        'file' => ':format, :encoding, separador: :delimiter, :rows linhas',
        'workbook' => ':format, :rows linhas',
        'checking' => 'Verificando o arquivo…',
        'importing' => 'Importando…',
        'progress' => ':processed de :total linhas',
        'cancel' => 'Interromper',
        'cancel_confirm' => 'Interromper? O que já foi importado permanece.',
        'report' => 'Relatório',
        'check_report' => 'Verificação',
        'checked' => 'A verificação terminou: nada foi gravado ainda. Veja o que a importação vai fazer.',
        'done' => 'A importação terminou.',
        'cancelled' => 'A importação foi interrompida. O que já havia sido importado permanece.',
        'counts' => [
            'created' => 'Criados',
            'updated' => 'Atualizados',
            'skipped' => 'Ignorados',
            'failed' => 'Erros',
            'nodes' => 'Itens da árvore criados',
        ],
        'will' => [
            'created' => 'Serão criados',
            'updated' => 'Serão atualizados',
            'skipped' => 'Serão ignorados',
            'failed' => 'Erros',
            'nodes' => 'Itens da árvore a criar',
        ],
        'row' => 'Linha',
        'outcome' => 'Resultado',
        'reason' => 'Motivo',
        'skipped_row' => 'ignorada',
        'failed_row' => 'erro',
        'unlisted' => 'Mais :count linhas foram contadas, mas não aparecem na lista.',
        'run' => 'Importar',
        'run_hint' => 'As linhas com erros ficam de fora; o restante é importado como a verificação mostrou.',
        'mapping' => 'Colunas',
        'mapping_hint' => 'Qual campo cada coluna preenche, deduzido pelos nomes das colunas. Uma célula vazia mantém o campo como está.',
        'column' => 'Coluna',
        'unnamed' => 'Coluna :number',
        'samples' => 'Nas primeiras linhas',
        'target' => 'Campo',
        'skip' => '— não importar —',
        'options' => 'Como importar',
        'key_hint' => 'Um registro com o mesmo valor é atualizado; se não existir, é criado.',
        'separator_hint' => 'Separa um item pai do seu filho em um caminho: “Roupas / Jaquetas”.',
        'check' => 'Verificar',
        'check_again' => 'Verificar de novo',
        'check_hint' => 'A verificação lê o arquivo inteiro e não grava nada: você verá quantos registros seriam criados e atualizados e em quais linhas há erros.',
    ],

    'settings' => [
        'mapping' => 'Colunas',
        'key' => 'Encontrar registros por',
        'mode' => 'O que fazer',
        'separator' => 'Separador do caminho',
    ],

    'modes' => [
        'both' => 'Criar registros novos e atualizar os existentes',
        'create' => 'Apenas criar registros novos',
        'update' => 'Apenas atualizar registros existentes',
    ],

    'validation' => [
        'twice' => 'Um campo aceita apenas uma coluna; escolhido mais de uma vez: :fields.',
        'key' => 'Os registros são encontrados por “:field”, mas nenhuma coluna o preenche.',
    ],

    'failures' => [
        'empty' => 'O arquivo não tem linhas.',
        'format' => 'Isto não é CSV nem Excel (xlsx). Um arquivo .xls antigo pode ser salvo no Excel como .xlsx ou CSV.',
        'unreadable' => 'Não foi possível ler o arquivo do Excel: ele pode estar danificado ou protegido por senha.',
        'crashed' => 'A importação parou por causa de um erro no servidor. Ele foi registrado; o que já havia sido importado permanece.',
        'missing' => 'O arquivo :file não existe.',
        'download' => 'Não foi possível baixar o arquivo de :url.',
        'column' => 'Não há essa coluna para --map: “:column”. Escreva assim: “Coluna=campo”.',
        'option' => 'O módulo não tem a opção “:option” para --option.',
        'module' => 'O módulo “:module” não existe mais.',
        'key' => 'Não é possível encontrar os registros por “:key”: o módulo não tem esse campo.',
    ],

    'problems' => [
        'no_key' => '“:field” está vazio: o registro não pode ser encontrado nem criado.',
        'bad_id' => '“:value” não é um id: deve ser um número inteiro maior que zero.',
        'exists' => 'O registro “:key” já existe, e só registros novos são criados.',
        'missing' => 'O registro “:key” não existe, e só registros existentes são atualizados.',
        'database' => 'O banco de dados recusou a linha. O erro foi registrado.',
        'not_bool' => '“:field”: “:value” não é sim nem não.',
        'not_option' => '“:field”: não existe a opção “:value”.',
        'ambiguous_option' => '“:field”: mais de uma opção se chama “:value”.',
        'tree_missing' => '“:field”: não existe “:value”.',
        'tree_ambiguous' => '“:field”: mais de um se chama “:value”: informe o caminho, “Superior / :value”.',
        'image_url' => 'O endereço da imagem não é válido: :url',
        'image_path' => 'O caminho da imagem não é permitido: :path',
        'image_type' => 'Não é uma imagem (JPEG, PNG, GIF, WebP ou SVG): :path',
        'image_missing' => 'A imagem :path não existe no site.',
        'image_host' => 'A imagem está em um endereço dentro da rede interna, que não é baixado: :url',
        'image_download' => 'Não foi possível baixar a imagem: :url',
        'image_large' => 'A imagem é maior que :max KB: :url',
    ],

    'console' => [
        'module' => 'Nenhum módulo “:module” aceita importações. Os que aceitam: :modules.',
        'source' => 'Informe o arquivo ou o endereço dele.',
        'like' => 'Não existe a importação de número :id para este módulo.',
        'report' => 'Linhas: :processed. Criados: :created, atualizados: :updated, ignorados: :skipped, erros: :failed, itens da árvore criados: :nodes.',
        'row' => 'Linha :row: :messages',
    ],
];

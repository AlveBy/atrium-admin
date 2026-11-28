<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Editor de página: :title',
    'open' => 'Abrir o editor de página',
    'back' => 'Voltar ao formulário',
    'save_draft' => 'Salvar rascunho',
    'publish' => 'Publicar',
    'failed' => 'Não deu certo. Verifique a conexão e tente novamente.',
    'leave' => 'As alterações não foram salvas. Sair do editor e perdê-las?',
    'stale' => 'A página publicada mudou depois que este rascunho foi iniciado. A publicação perguntará antes de substituir essas mudanças.',
    'hidden' => 'Esta página está desativada: publicá-la não a mostra no site.',
    'palette' => 'Blocos',
    'palette_hint' => 'Arraste um bloco para uma coluna ou pressione-o para colocá-lo depois do item selecionado.',
    'add_type' => 'Adicionar bloco: :type',
    'column_full' => 'Não há espaço para mais um bloco aqui.',
    'structure' => 'Estrutura da página',
    'drop_here' => 'Arraste um bloco para cá',
    'has_error' => 'Erro',
    'not_drawn' => 'Ainda não aparece na página',
    'settings' => 'Configurações',
    'nothing_selected' => 'Selecione uma seção, uma coluna ou um bloco para alterar as configurações.',
    'move_to' => 'Mover para a coluna',
    'move' => 'Mover',
    'status' => [
        'published' => 'Publicada',
        'unsaved' => 'Alterações não salvas',
        'draft' => 'Rascunho salvo em :date, não publicado',
        'draft_by' => 'Rascunho salvo em :date por :author, não publicado',
    ],
    'notices' => [
        'draft' => 'O rascunho foi salvo. O site mostra a página publicada.',
        'publish' => 'Publicada.',
        'discard' => 'As alterações foram descartadas.',
        'version' => 'A versão está aberta no editor. Publique-a para que apareça no site.',
    ],
    'discard' => [
        'button' => 'Descartar alterações',
        'title' => 'Descartar as alterações?',
        'question' => 'O rascunho e as alterações não salvas são removidos; o editor volta à página publicada.',
    ],
    'conflicts' => [
        'draft' => 'Alguém salvou ou descartou o rascunho desta página depois que você a abriu.',
        'published' => 'A página publicada mudou depois que esta edição começou.',
    ],
    'conflict' => [
        'title' => 'A página foi alterada em outro lugar',
        'hint' => 'Carregar a página mais nova descarta suas alterações não salvas. Continuar substitui as outras alterações pelas suas.',
        'reload' => 'Carregar a página mais nova',
        'force' => 'Continuar mesmo assim',
    ],
    'versions' => [
        'button' => 'Versões',
        'title' => 'Versões anteriores da página',
        'hint' => 'Abrir uma versão não muda nada no site até você publicá-la.',
        'replaces' => 'Abrir uma versão substitui suas alterações não salvas.',
        'open' => 'Abrir no editor',
        'current' => 'publicada agora',
    ],
    'preview' => [
        'title' => 'Pré-visualização',
        'updating' => 'Atualizando…',
        'hint' => 'Clique na página para selecionar uma parte dela.',
        'failed' => 'Não foi possível mostrar a pré-visualização.',
        'selected' => 'Selecionado: :place',
    ],
    'said' => [
        'added' => 'Adicionado: :place',
        'moved' => 'Movido, agora em: :place',
        'removed' => 'Removido: :place',
    ],
    'count' => [
        'sections' => ':count seção|:count seções',
        'blocks' => ':count bloco|:count blocos',
        'empty' => 'A página ainda não tem blocos.',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The installed module packages and their settings.
 */
return [
    'title' => 'Módulos',
    'index_hint' => 'Os pacotes de módulos instalados neste site. Um módulo com configurações tem um botão para elas.',
    'empty' => 'Nenhum pacote de módulo instalado.',
    'module' => 'Módulo',
    'package' => 'Pacote',
    'settings' => 'Configurações',
    'settings_title' => ':module: configurações',
    'per_site' => 'Estas são as configurações do site “:site”. Escolha outro site no cabeçalho para configurar as dele.',
    'saved' => 'Configurações salvas.',
    'enable' => 'Ativar',
    'disable' => 'Desativar',
    'enabled' => ':module está ativado.',
    'disabled' => ':module está desativado. Seus dados e configurações são mantidos.',
    'parent_off' => 'Ative :parent primeiro.',
    'config_locked' => 'Não foi possível editar config/atrium.php.',
    'required_title' => 'Requeridos, não instalados',
    'required_hint' => 'Pacotes do composer.json que ainda não estão instalados. Para instalar um:',
    'catalogue_title' => 'Catálogo',
    'catalogue_hint' => 'Módulos Atrium publicados no Packagist. Para adicionar um, execute na pasta do projeto:',
    'catalogue_loading' => 'Carregando o catálogo…',
    'catalogue_unavailable' => 'O Packagist não respondeu. Tente mais tarde.',
    'catalogue_empty' => 'Nenhum módulo encontrado no Packagist.',
    'status_installed' => 'Instalado',
    'status_disabled' => 'Desativado',
    'status_required' => 'Requerido',
    'downloads' => ':count downloads',
];

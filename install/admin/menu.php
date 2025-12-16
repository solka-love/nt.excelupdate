<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$menu = [
    [
        'parent_menu' => 'global_menu_content',
        'sort' => 500,
        'text' => Loc::getMessage('SOLKA_MODULE_MENU_TITLE'),
        'title' => Loc::getMessage('SOLKA_MODULE_MENU_TITLE'),
        'url' => 'solka_update_names.php',
        'icon' => 'iblock_menu_icon_settings',
        'items_id' => 'menu_solka_tools',
        'items' => [
            [
                'text' => Loc::getMessage('SOLKA_UPDATE_NAMES_TITLE'),
                'title' => Loc::getMessage('SOLKA_UPDATE_NAMES_TITLE'),
                'url' => 'solka_update_names.php',
                'icon' => 'iblock_menu_icon_settings',
                'more_url' => ['solka_update_names.php'],
            ],
        ],
    ],
];

return $menu;
?>
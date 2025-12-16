<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

return [
    'parent_menu' => 'global_menu_content',
    'section' => 'solka_parts_update',
    'sort' => 1000,
    'text' => 'Обновление авто и запчастей',
    'title' => 'Обновление авто и запчастей из Excel',
    'icon' => 'iblock_menu_icon_types',
    'page_icon' => 'iblock_menu_icon_types',
    'items_id' => 'menu_solka_parts_update',
    'items' => [
        [
            'text' => 'Загрузка Excel',
            'title' => 'Загрузка Excel и обновление данных',
            'url' => 'parts_update_test.php',
            'more_url' => [],
        ],
    ],
];

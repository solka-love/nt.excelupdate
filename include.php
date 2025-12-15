<?php
// /local/modules/solka_module/include.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\IO\File;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;

class SolkaAutoPartsUpdater
{
    const PROPERTY_CODE = 'PRIMENIMOST'; // Код свойства применимость
    const PROPERTY_ID = 161; // ID свойства применимость
    
    /**
     * Основной метод обновления из CSV файла
     */
    public static function updateFromCsv($filePath)
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Файл не найден: ' . $filePath];
        }
        
        try {
            // Читаем CSV файл
            $data = self::readCsvFile($filePath);
            
            if (empty($data)) {
                return ['success' => false, 'message' => 'Файл пуст или не содержит данных'];
            }
            
            // Обрабатываем данные
            $updates = [];
            foreach ($data as $index => $row) {
                if ($index < 2) continue; // Пропускаем заголовки
                
                $oldName = isset($row[0]) ? trim($row[0]) : '';
                $newName = isset($row[2]) ? trim($row[2]) : '';
                
                // Очищаем названия
                $oldName = self::cleanName($oldName);
                $newName = self::cleanName($newName);
                
                if (!empty($oldName) && !empty($newName) && $oldName !== $newName) {
                    $updates[] = [
                        'old' => $oldName,
                        'new' => $newName,
                        'row' => $index + 1
                    ];
                }
            }
            
            if (empty($updates)) {
                return ['success' => false, 'message' => 'Нет изменений для обновления'];
            }
            
            // Выполняем обновление через API Битрикс
            $stats = self::executeUpdates($updates);
            
            // Сохраняем лог
            self::saveLog($updates, $stats, basename($filePath));
            
            return [
                'success' => true,
                'message' => 'Обновление выполнено успешно!',
                'stats' => $stats,
                'updates_count' => count($updates),
                'log_file' => '/upload/solka_updater/update_log.txt'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Чтение CSV файла
     */
    private static function readCsvFile($filePath)
    {
        $data = [];
        
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 10000, ',')) !== FALSE) {
                $data[] = $row;
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    /**
     * Очистка названия
     */
    private static function cleanName($name)
    {
        if (empty($name)) return '';
        
        $name = trim($name);
        
        // Убираем точки в начале
        $name = preg_replace('/^\.+/', '', $name);
        
        // Убираем лишние пробелы
        $name = preg_replace('/\s+/', ' ', $name);
        
        return $name;
    }
    
    /**
     * Выполнение обновлений через API Битрикс
     */
    private static function executeUpdates($updates)
    {
        $stats = [
            'sections' => 0,
            'properties' => 0,
            'elements' => 0,
            'enum_values' => 0
        ];
        
        // Подключаем модуль информационных блоков
        Loader::includeModule('iblock');
        
        // Обновляем каждую запись
        foreach ($updates as $update) {
            $oldName = $update['old'];
            $newName = $update['new'];
            
            // 1. Обновляем названия в разделах (секциях)
            $sectionsUpdated = self::updateSections($oldName, $newName);
            $stats['sections'] += $sectionsUpdated;
            
            // 2. Обновляем значения свойств элементов
            $propsUpdated = self::updateElementProperties($oldName, $newName);
            $stats['properties'] += $propsUpdated;
            
            // 3. Обновляем названия элементов
            $elementsUpdated = self::updateElements($oldName, $newName);
            $stats['elements'] += $elementsUpdated;
            
            // 4. Обновляем значения в списках свойств
            $enumUpdated = self::updatePropertyEnum($oldName, $newName);
            $stats['enum_values'] += $enumUpdated;
        }
        
        return $stats;
    }
    
    /**
     * Обновление разделов (секций)
     */
    private static function updateSections($oldName, $newName)
    {
        $updated = 0;
        
        // Ищем разделы с старым названием
        $dbSections = CIBlockSection::GetList(
            [],
            ['NAME' => $oldName],
            false,
            ['ID', 'NAME', 'IBLOCK_ID']
        );
        
        while ($section = $dbSections->Fetch()) {
            // Обновляем название раздела
            $fields = ['NAME' => $newName];
            
            $sectionObject = new CIBlockSection();
            if ($sectionObject->Update($section['ID'], $fields)) {
                $updated++;
                
                // Логируем обновление
                self::logToFile("Обновлен раздел ID: {$section['ID']}, '{$oldName}' -> '{$newName}'");
            }
        }
        
        return $updated;
    }
    
    /**
     * Обновление свойств элементов
     */
    private static function updateElementProperties($oldName, $newName)
    {
        $updated = 0;
        
        // Получаем ID свойства "применимость"
        $propertyId = self::getPropertyId();
        if (!$propertyId) {
            return 0;
        }
        
        // Ищем элементы со свойством равным старому названию
        $dbElements = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::getIblockId(),
                'PROPERTY_' . self::PROPERTY_CODE => $oldName
            ],
            false,
            false,
            ['ID', 'NAME', 'IBLOCK_ID']
        );
        
        while ($element = $dbElements->Fetch()) {
            // Обновляем значение свойства
            CIBlockElement::SetPropertyValues(
                $element['ID'],
                $element['IBLOCK_ID'],
                $newName,
                self::PROPERTY_CODE
            );
            
            $updated++;
            
            // Логируем обновление
            self::logToFile("Обновлено свойство элемента ID: {$element['ID']}, '{$oldName}' -> '{$newName}'");
        }
        
        return $updated;
    }
    
    /**
     * Обновление названий элементов
     */
    private static function updateElements($oldName, $newName)
    {
        $updated = 0;
        
        // Ищем элементы с старым названием
        $dbElements = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::getIblockId(),
                'NAME' => $oldName
            ],
            false,
            false,
            ['ID', 'NAME', 'IBLOCK_ID']
        );
        
        while ($element = $dbElements->Fetch()) {
            // Обновляем название элемента
            $fields = ['NAME' => $newName];
            
            $elementObject = new CIBlockElement();
            if ($elementObject->Update($element['ID'], $fields)) {
                $updated++;
                
                // Логируем обновление
                self::logToFile("Обновлен элемент ID: {$element['ID']}, '{$oldName}' -> '{$newName}'");
            }
        }
        
        return $updated;
    }
    
    /**
     * Обновление значений в списках свойств
     */
    private static function updatePropertyEnum($oldName, $newName)
    {
        $updated = 0;
        
        $propertyId = self::getPropertyId();
        if (!$propertyId) {
            return 0;
        }
        
        // Ищем значение в списке свойств
        $dbEnum = CIBlockPropertyEnum::GetList(
            [],
            [
                'PROPERTY_ID' => $propertyId,
                'VALUE' => $oldName
            ]
        );
        
        while ($enum = $dbEnum->Fetch()) {
            // Обновляем значение
            $fields = ['VALUE' => $newName];
            
            $enumObject = new CIBlockPropertyEnum();
            if ($enumObject->Update($enum['ID'], $fields)) {
                $updated++;
                
                // Логируем обновление
                self::logToFile("Обновлено значение свойства ID: {$enum['ID']}, '{$oldName}' -> '{$newName}'");
            }
        }
        
        return $updated;
    }
    
    /**
     * Получение ID свойства "применимость"
     */
    private static function getPropertyId()
    {
        // Если уже знаем ID - возвращаем его
        if (self::PROPERTY_ID) {
            return self::PROPERTY_ID;
        }
        
        // Ищем свойство по коду
        $dbProperty = CIBlockProperty::GetList(
            [],
            [
                'IBLOCK_ID' => self::getIblockId(),
                'CODE' => self::PROPERTY_CODE
            ]
        );
        
        if ($property = $dbProperty->Fetch()) {
            return $property['ID'];
        }
        
        return false;
    }
    
    /**
     * Получение ID инфоблока (нужно определить)
     */
    private static function getIblockId()
    {
        // Нужно определить ID вашего инфоблока с запчастями
        // Можно получить через поиск или задать константой
        
        // Вариант 1: Поиск по символьному коду
        $dbIblock = CIBlock::GetList(
            [],
            ['CODE' => 'autoparts'] // Замените на ваш код
        );
        
        if ($iblock = $dbIblock->Fetch()) {
            return $iblock['ID'];
        }
        
        // Вариант 2: Первый найденный инфоблок типа catalog
        $dbIblock = CIBlock::GetList(
            [],
            ['TYPE' => 'catalog']
        );
        
        if ($iblock = $dbIblock->Fetch()) {
            return $iblock['ID'];
        }
        
        // Если не нашли, возвращаем 0 (будет искать во всех)
        return 0;
    }
    
    /**
     * Сохранение лога
     */
    private static function saveLog($updates, $stats, $fileName)
    {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/solka_updater/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $log = "=== ОБНОВЛЕНИЕ ИЗ ФАЙЛА: {$fileName} ===\n";
        $log .= "Время: " . date('Y-m-d H:i:s') . "\n";
        $log .= "Найдено записей: " . count($updates) . "\n\n";
        
        $log .= "СПИСОК ИЗМЕНЕНИЙ:\n";
        foreach ($updates as $update) {
            $log .= sprintf("Строка %d: '%s' → '%s'\n", 
                $update['row'], 
                $update['old'], 
                $update['new']
            );
        }
        
        $log .= "\n=== РЕЗУЛЬТАТЫ ===\n";
        $log .= "Обновлено разделов: {$stats['sections']}\n";
        $log .= "Обновлено свойств: {$stats['properties']}\n";
        $log .= "Обновлено элементов: {$stats['elements']}\n";
        $log .= "Обновлено значений: {$stats['enum_values']}\n";
        $log .= "=================================\n\n";
        
        file_put_contents($uploadDir . 'update_log.txt', $log, FILE_APPEND);
    }
    
    /**
     * Дополнительное логирование в файл
     */
    private static function logToFile($message)
    {
        $logDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/solka_updater/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'detailed_log.txt';
        $logMessage = date('Y-m-d H:i:s') . " - " . $message . "\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Получение лога обновлений
     */
    public static function getUpdateLog()
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/solka_updater/update_log.txt';
        
        if (!file_exists($logFile)) {
            return 'Лог обновлений пуст. Загрузите CSV файл для начала работы.';
        }
        
        $content = file_get_contents($logFile);
        return $content;
    }
    
    /**
     * Тестирование подключения модулей
     */
    public static function testConnection()
    {
        $result = [
            'iblock_module' => Loader::includeModule('iblock'),
            'main_module' => Loader::includeModule('main'),
            'upload_dir' => is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/'),
            'property_id' => self::getPropertyId(),
            'iblock_id' => self::getIblockId()
        ];
        
        return $result;
    }
}
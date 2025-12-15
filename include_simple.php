<?php
// /local/modules/solka_module/include_simple.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class SolkaAutoPartsUpdaterSimple
{
    const PROPERTY_ID = 161;
    
    public static function updateFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Файл не найден'];
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            $data = self::readCsv($filePath);
        } elseif ($extension === 'xlsx' || $extension === 'xls') {
            $data = self::convertExcelToArray($filePath);
        } else {
            return ['success' => false, 'message' => 'Неверный формат. Используйте CSV или Excel'];
        }
        
        if (empty($data)) {
            return ['success' => false, 'message' => 'Файл пуст'];
        }
        
        $updates = [];
        foreach ($data as $index => $row) {
            if ($index < 2) continue;
            
            $oldName = isset($row[0]) ? trim($row[0]) : '';
            $newName = isset($row[2]) ? trim($row[2]) : '';
            
            if (!empty($oldName) && !empty($newName) && $oldName !== $newName) {
                $updates[] = [
                    'old' => self::cleanName($oldName),
                    'new' => self::cleanName($newName)
                ];
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'Нет изменений для обновления'];
        }
        
        $stats = self::executeUpdates($updates);
        self::saveLog($updates, $stats, basename($filePath));
        
        return [
            'success' => true,
            'message' => 'Обновлено ' . count($updates) . ' записей',
            'stats' => $stats
        ];
    }
    
    private static function readCsv($filePath)
    {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    private static function convertExcelToArray($filePath)
    {
        // Простой конвертер Excel в массив через PHP
        $data = [];
        
        if (!function_exists('zip_open')) {
            return self::readExcelAsText($filePath);
        }
        
        // Для XLSX (это ZIP архив)
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'xlsx') {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                // Ищем файл с данными
                $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheetContent) {
                    $data = self::parseXmlSheet($sheetContent);
                }
                $zip->close();
            }
        }
        
        return $data;
    }
    
    private static function parseXmlSheet($xmlContent)
    {
        $data = [];
        $xml = simplexml_load_string($xmlContent);
        
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $value = (string)$cell->v;
                $rowData[] = $value;
            }
            $data[] = $rowData;
        }
        
        return $data;
    }
    
    private static function readExcelAsText($filePath)
    {
        // Читаем как текстовый файл
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        $data = [];
        foreach ($lines as $line) {
            if (strlen($line) > 10) {
                // Разбиваем по табам или множественным пробелам
                $columns = preg_split('/\t|\s{2,}/', $line);
                if (count($columns) >= 3) {
                    $data[] = $columns;
                }
            }
        }
        
        return $data;
    }
    
    private static function cleanName($name)
    {
        $name = trim($name);
        $name = preg_replace('/^\.+/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }
    
    private static function executeUpdates($updates)
    {
        $connection = Application::getConnection();
        $stats = ['sections' => 0, 'properties' => 0, 'elements' => 0];
        
        foreach ($updates as $update) {
            // Экранируем строки
            $old = $connection->getSqlHelper()->forSql($update['old']);
            $new = $connection->getSqlHelper()->forSql($update['new']);
            
            $result = $connection->queryExecute("UPDATE b_iblock_section SET NAME = '{$new}' WHERE NAME = '{$old}'");
            $stats['sections'] += $result->getAffectedRowsCount();
            
            $result = $connection->queryExecute("UPDATE b_iblock_element_property SET VALUE = '{$new}' WHERE IBLOCK_PROPERTY_ID = " . self::PROPERTY_ID . " AND VALUE = '{$old}'");
            $stats['properties'] += $result->getAffectedRowsCount();
            
            $result = $connection->queryExecute("UPDATE b_iblock_element SET NAME = '{$new}' WHERE NAME = '{$old}'");
            $stats['elements'] += $result->getAffectedRowsCount();
        }
        
        return $stats;
    }
    
    private static function saveLog($updates, $stats, $fileName)
    {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/solka_updater/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $log = "=== " . date('Y-m-d H:i:s') . " ===\n";
        $log .= "Файл: {$fileName}\n";
        $log .= "Записей: " . count($updates) . "\n\n";
        
        foreach ($updates as $i => $update) {
            $log .= ($i + 1) . ". {$update['old']} -> {$update['new']}\n";
        }
        
        $log .= "\nИтого:\n";
        $log .= "- Секций: {$stats['sections']}\n";
        $log .= "- Свойств: {$stats['properties']}\n";
        $log .= "- Элементов: {$stats['elements']}\n\n";
        
        file_put_contents($uploadDir . 'update_log.txt', $log, FILE_APPEND);
    }
    
    public static function getUpdateLog()
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/solka_updater/update_log.txt';
        return file_exists($logFile) ? file_get_contents($logFile) : 'Лог пуст';
    }
}
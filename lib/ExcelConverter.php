<?php
// /local/modules/solka_module/lib/ExcelConverterSimple.php
class ExcelConverterSimple
{
    /**
     * Простая конвертация Excel в CSV
     */
    public static function excelToCsv($excelFile)
    {
        if (!file_exists($excelFile)) {
            throw new Exception('Файл не найден');
        }
        
        $extension = strtolower(pathinfo($excelFile, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            return $excelFile;
        }
        
        // Генерируем CSV файл
        $csvFile = tempnam(sys_get_temp_dir(), 'csv_') . '.csv';
        
        // Простой метод: читаем как текст и парсим
        $content = file_get_contents($excelFile);
        
        // Для XLSX (это ZIP)
        if ($extension === 'xlsx' && class_exists('ZipArchive')) {
            return self::parseXlsxSimple($excelFile, $csvFile);
        }
        
        // Для других форматов - простой текстовый парсинг
        return self::parseAsText($content, $csvFile);
    }
    
    private static function parseXlsxSimple($excelFile, $csvFile)
    {
        $zip = new ZipArchive;
        if ($zip->open($excelFile) !== TRUE) {
            return false;
        }
        
        // Ищем sheet1.xml
        $sheetContent = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, 'xl/worksheets/sheet') !== false) {
                $sheetContent = $zip->getFromIndex($i);
                break;
            }
        }
        
        if (empty($sheetContent)) {
            $zip->close();
            return false;
        }
        
        // Простой парсинг XML
        $xml = simplexml_load_string($sheetContent);
        $handle = fopen($csvFile, 'w');
        
        if (isset($xml->sheetData) && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                if (isset($row->c)) {
                    foreach ($row->c as $cell) {
                        if (isset($cell->v)) {
                            $rowData[] = (string)$cell->v;
                        }
                    }
                }
                if (!empty($rowData)) {
                    fputcsv($handle, $rowData);
                }
            }
        }
        
        fclose($handle);
        $zip->close();
        
        return filesize($csvFile) > 0 ? $csvFile : false;
    }
    
    private static function parseAsText($content, $csvFile)
    {
        // Ищем текстовые данные
        preg_match_all('/[^\x00-\x1F\x7F-\xFF]{3,}/u', $content, $matches);
        
        $handle = fopen($csvFile, 'w');
        $currentRow = [];
        
        foreach ($matches[0] as $match) {
            // Если строка похожа на данные автомобиля
            if (preg_match('/[a-zA-Zа-яА-Я]/u', $match)) {
                $currentRow[] = trim($match);
                
                // Если в строке есть закрывающая скобка ] - считаем что строка закончена
                if (strpos($match, ']') !== false && count($currentRow) >= 1) {
                    // Добавляем пустую колонку посередине
                    if (count($currentRow) === 1) {
                        array_splice($currentRow, 1, 0, '');
                    }
                    fputcsv($handle, $currentRow);
                    $currentRow = [];
                }
            }
        }
        
        fclose($handle);
        
        return filesize($csvFile) > 0 ? $csvFile : false;
    }
}
<?php
namespace Solka;

use Bitrix\Main\Loader;

class TestUpdater
{
    const PROP_PRIMENIMOST = 161;
    private static $doUpdate = false;

    /**
     * Основной метод обработки CSV файла
     */
    public static function process($filePath, $updateMode = false)
    {
        self::$doUpdate = $updateMode;
        Loader::includeModule('iblock');

        // Логирование начала операции
        self::log("Начало обработки файла: $filePath (режим: " . ($updateMode ? 'обновление' : 'предпросмотр') . ")");

        $reader = array_map('str_getcsv', file($filePath));
        $result = ['updated' => [], 'errors' => [], 'summary' => [
            'autos_updated' => 0,
            'parts_updated' => 0,
            'total_parts' => 0
        ]];

        foreach ($reader as $i => $row) {
            if ($i === 0) continue;

            $rawOld = trim($row[0]);
            $rawNew = trim($row[2]);

            if (!$rawOld || !$rawNew) continue;

            if (!preg_match('/\[(\d+)\]/', $rawNew, $m)) continue;
            $autoId = (int)$m[1];

            $oldAutoName = self::cleanName($rawOld);
            $newAutoName = self::cleanName($rawNew);

            $section = \CIBlockSection::GetByID($autoId)->Fetch();
            if (!$section) {
                $errorMsg = "Раздел с ID $autoId не найден";
                $result['errors'][] = $errorMsg;
                self::log("ОШИБКА: $errorMsg");
                continue;
            }

            $autoUpdated = false;
            if ($section['NAME'] !== $newAutoName) {
                if (self::$doUpdate) {
                    $bs = new \CIBlockSection;
                    if ($bs->Update($autoId, ['NAME' => $newAutoName])) {
                        $autoUpdated = true;
                        $result['summary']['autos_updated']++;
                        self::log("Обновлен раздел ID $autoId: {$section['NAME']} -> $newAutoName");
                    }
                }
            }

            $parts = [];
            $rsParts = \CIBlockElement::GetList(
                [],
                ['PROPERTY_' . self::PROP_PRIMENIMOST => $autoId],
                false,
                false,
                ['ID', 'NAME', 'IBLOCK_ID']
            );

            while ($p = $rsParts->Fetch()) {
                $oldPartName = $p['NAME'];
                $newPartName = self::updatePartName($oldPartName, $oldAutoName, $newAutoName);
                
                if ($oldPartName !== $newPartName) {
                    $partUpdated = false;
                    
                    if (self::$doUpdate) {
                        $el = new \CIBlockElement;
                        if ($el->Update($p['ID'], ['NAME' => $newPartName])) {
                            $partUpdated = true;
                            $result['summary']['parts_updated']++;
                            self::log("Обновлена запчасть ID {$p['ID']}: $oldPartName -> $newPartName");
                        }
                    }
                    
                    $parts[] = [
                        'PART_ID' => $p['ID'],
                        'OLD_NAME' => $oldPartName,
                        'NEW_NAME' => $newPartName,
                        'UPDATED' => $partUpdated
                    ];
                }
            }

            $result['summary']['total_parts'] += count($parts);
            $result['updated'][] = [
                'AUTO_ID' => $autoId,
                'SECTION_OLD' => $section['NAME'],
                'SECTION_NEW' => $newAutoName,
                'AUTO_UPDATED' => $autoUpdated,
                'PARTS_COUNT' => count($parts),
                'PARTS' => $parts
            ];
        }

        self::log("Завершено. Обновлено авто: {$result['summary']['autos_updated']}, запчастей: {$result['summary']['parts_updated']}");

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        die();
    }

    private static function cleanName($text)
    {
        $text = preg_replace('/\.+/', '', $text);
        $text = preg_replace('/\s*\[\d+\]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function updatePartName($partName, $oldAutoName, $newAutoName)
    {
        if ($oldAutoName === $newAutoName) {
            return $partName;
        }

        $pattern = '/(.*?)(' . preg_quote($oldAutoName, '/') . ')(.*)/u';
        
        if (preg_match($pattern, $partName, $matches)) {
            return $matches[1] . $newAutoName . $matches[3];
        }
        
        return $partName . ' ' . $newAutoName;
    }

    private static function log($message)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/solka_module.log';
        $logDir = dirname($logFile);
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND);
    }
}
?>
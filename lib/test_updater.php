<?php
namespace Solka;

use Bitrix\Main\Loader;

class TestUpdater
{
    const PROP_PRIMENIMOST = 161;
    private static $doUpdate = false;

    public static function process($filePath, $updateMode = false)
    {
        self::$doUpdate = $updateMode;
        Loader::includeModule('iblock');

        $reader = array_map('str_getcsv', file($filePath));
        $result = ['updated' => [], 'errors' => []];

        foreach ($reader as $i => $row) {
            if ($i === 0) continue;

            $rawOld = trim($row[0]);
            $rawNew = trim($row[2]);

            if (!$rawOld || !$rawNew) continue;

            if (!preg_match('/\[(\d+)\]/', $rawNew, $m)) continue;
            $autoId = (int)$m[1];

            // Очищаем оба названия для сравнения
            $oldAutoName = self::cleanName($rawOld);
            $newAutoName = self::cleanName($rawNew);

            // Получаем раздел автомобиля
            $section = \CIBlockSection::GetByID($autoId)->Fetch();
            if (!$section) {
                $result['errors'][] = "Раздел с ID $autoId не найден";
                continue;
            }

            // Обновляем название раздела авто
            $autoUpdated = false;
            if ($section['NAME'] !== $newAutoName) {
                if (self::$doUpdate) {
                    $bs = new \CIBlockSection;
                    if ($bs->Update($autoId, ['NAME' => $newAutoName])) {
                        $autoUpdated = true;
                    }
                }
            }

            // Ищем запчасти, привязанные к этому авто
            $parts = [];
            $rsParts = \CIBlockElement::GetList(
                [],
                ['PROPERTY_' . self::PROP_PRIMENIMOST => $autoId],
                false,
                false,
                ['ID', 'NAME', 'IBLOCK_ID']
            );

            while ($p = $rsParts->Fetch()) {
                // Заменяем старое название авто в названии запчасти на новое
                $oldPartName = $p['NAME'];
                $newPartName = self::updatePartName($oldPartName, $oldAutoName, $newAutoName);
                
                if ($oldPartName !== $newPartName) {
                    $partUpdated = false;
                    
                    if (self::$doUpdate) {
                        $el = new \CIBlockElement;
                        if ($el->Update($p['ID'], ['NAME' => $newPartName])) {
                            $partUpdated = true;
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

            $result['updated'][] = [
                'AUTO_ID' => $autoId,
                'SECTION_OLD' => $section['NAME'],
                'SECTION_NEW' => $newAutoName,
                'AUTO_UPDATED' => $autoUpdated,
                'PARTS_COUNT' => count($parts),
                'PARTS' => $parts
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        die();
    }

    private static function cleanName($text)
    {
        // Убираем точки и ID
        $text = preg_replace('/\.+/', '', $text);
        $text = preg_replace('/\s*\[\d+\]/', '', $text);

        // Убираем лишние пробелы
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function updatePartName($partName, $oldAutoName, $newAutoName)
    {
       
        if ($oldAutoName === $newAutoName) {
            return $partName;
        }

        // Ищем старое название авто в названии запчасти
        $pattern = '/(.*?)(' . preg_quote($oldAutoName, '/') . ')(.*)/u';
        
        if (preg_match($pattern, $partName, $matches)) {
            // Сохраняем префикс (например, "Гайка Audi ") и суффикс
            $prefix = $matches[1];
            $suffix = $matches[3];
            
            // Формируем новое название
            return $prefix . $newAutoName . $suffix;
        }
        
        return $partName . ' ' . $newAutoName;
    }
}
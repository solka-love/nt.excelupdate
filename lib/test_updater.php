<?php
namespace Solka;

use Bitrix\Main\Loader;

class UpdaterTest
{
    const PROP_PRIMENIMOST = 161;

    private static $mode = 'test';
    private static $logFile = '/bitrix/logs/parts_update.log';

    public static function process($filePath, $mode = 'test')
    {
        self::$mode = ($mode === 'update') ? 'update' : 'test';

        Loader::includeModule('iblock');

        self::log('START', [
            'mode' => self::$mode,
            'file' => $filePath
        ]);

        $rows = array_map('str_getcsv', file($filePath));

        $result = [
            'MODE' => self::$mode,
            'AUTOS' => [],
            'ERRORS' => []
        ];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;

            $rawOld = trim($row[0] ?? '');
            $rawNew = trim($row[2] ?? '');

            if (!$rawOld || !$rawNew) continue;

            if (!preg_match('/\[(\d+)\]/', $rawNew, $m)) continue;
            $autoId = (int)$m[1];

            $oldAutoName = self::cleanName($rawOld);
            $newAutoName = self::cleanName($rawNew);

            $section = \CIBlockSection::GetByID($autoId)->Fetch();
            if (!$section) {
                $result['ERRORS'][] = "Раздел авто ID {$autoId} не найден";
                continue;
            }

            /** --- АВТО --- */
            $autoWillChange = ($section['NAME'] !== $newAutoName);
            $autoUpdated = false;

            if ($autoWillChange && self::$mode === 'update') {
                $bs = new \CIBlockSection;
                $autoUpdated = $bs->Update($autoId, ['NAME' => $newAutoName]);
            }

            if ($autoWillChange) {
                self::log('AUTO', [
                    'AUTO_ID' => $autoId,
                    'OLD' => $section['NAME'],
                    'NEW' => $newAutoName,
                    'UPDATED' => $autoUpdated
                ]);
            }

            /** --- ЗАПЧАСТИ --- */
            $parts = [];

            $rsParts = \CIBlockElement::GetList(
                [],
                ['PROPERTY_' . self::PROP_PRIMENIMOST => $autoId],
                false,
                false,
                ['ID', 'NAME']
            );

            while ($p = $rsParts->Fetch()) {
                $newPartName = self::updatePartName(
                    $p['NAME'],
                    $oldAutoName,
                    $newAutoName
                );

                if ($p['NAME'] !== $newPartName) {
                    $partUpdated = false;

                    if (self::$mode === 'update') {
                        $el = new \CIBlockElement;
                        $partUpdated = $el->Update($p['ID'], ['NAME' => $newPartName]);
                    }

                    self::log('PART', [
                        'PART_ID' => $p['ID'],
                        'OLD' => $p['NAME'],
                        'NEW' => $newPartName,
                        'UPDATED' => $partUpdated
                    ]);

                    $parts[] = [
                        'PART_ID' => $p['ID'],
                        'OLD_NAME' => $p['NAME'],
                        'NEW_NAME' => $newPartName,
                        'UPDATED' => $partUpdated
                    ];
                }
            }

            $result['AUTOS'][] = [
                'AUTO_ID' => $autoId,
                'OLD_NAME' => $section['NAME'],
                'NEW_NAME' => $newAutoName,
                'AUTO_WILL_CHANGE' => $autoWillChange,
                'AUTO_UPDATED' => $autoUpdated,
                'PARTS_FOUND' => count($parts),
                'PARTS' => $parts
            ];
        }

        self::log('FINISH');

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
        // 1. Если новое название УЖЕ есть — ничего не делаем
        if (mb_stripos($partName, $newAutoName) !== false) {
            return $partName;
        }

        // 2. Если есть старое — заменяем
        if ($oldAutoName && mb_stripos($partName, $oldAutoName) !== false) {
            return preg_replace(
                '/' . preg_quote($oldAutoName, '/') . '/u',
                $newAutoName,
                $partName,
                1 // ТОЛЬКО ПЕРВОЕ ВХОЖДЕНИЕ
            );
        }

        // 3. Если нет ни старого, ни нового — НИЧЕГО НЕ ДЕЛАЕМ
        // (или можешь добавить вручную, если решишь)
        return $partName;
    }


    private static function log($message, $context = [])
    {
        $line = date('Y-m-d H:i:s') . ' | ' . $message;

        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $line .= PHP_EOL;

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . self::$logFile,
            $line,
            FILE_APPEND
        );
    }
}

<?php
namespace Solka;

use Bitrix\Main\Loader;

class AutoPartsUpdater
{
    const PROP_PRIMENIMOST = 161;
    const LOG_FILE = '/upload/logs/auto_parts_update.log';

    private static $mode = 'test';

    public static function process(string $filePath, string $mode = 'test'): void
    {
        self::$mode = ($mode === 'update') ? 'update' : 'test';

        Loader::includeModule('iblock');
        global $DB;

        self::log('START', [
            'mode' => self::$mode,
            'file' => $filePath
        ]);

        $rows = self::readCsv($filePath);

        $result = [
            'MODE' => self::$mode,
            'AUTOS' => [],
            'ERRORS' => []
        ];

        if (self::$mode === 'update') {
            $DB->StartTransaction();
        }

        try {
            foreach ($rows as $i => $row) {

                // пропускаем заголовок
                if ($i === 0) {
                    continue;
                }

                $rawOld = trim($row[0] ?? '');
                $rawNew = trim($row[2] ?? '');

                if ($rawOld === '' || $rawNew === '') {
                    $result['ERRORS'][] = "Строка {$i}: пустые данные";
                    continue;
                }

                if (!preg_match('/\[(\d+)\]/', $rawNew, $m)) {
                    $result['ERRORS'][] = "Строка {$i}: не найден ID авто";
                    continue;
                }

                $autoId = (int)$m[1];
                $oldAutoName = self::cleanName($rawOld);
                $newAutoName = self::cleanName($rawNew);

                $section = \CIBlockSection::GetByID($autoId)->Fetch();
                if (!$section) {
                    $result['ERRORS'][] = "Авто ID {$autoId} не найдено";
                    continue;
                }

                /** --- АВТО --- */
                $autoChanged = ($section['NAME'] !== $newAutoName);
                $autoUpdated = false;

                if ($autoChanged && self::$mode === 'update') {
                    $bs = new \CIBlockSection;
                    $autoUpdated = $bs->Update($autoId, ['NAME' => $newAutoName]);
                }

                if ($autoChanged) {
                    self::log('AUTO', [
                        'ID' => $autoId,
                        'OLD' => $section['NAME'],
                        'NEW' => $newAutoName,
                        'UPDATED' => $autoUpdated
                    ]);
                }

                /** --- ЗАПЧАСТИ --- */
                $parts = [];

                $rsParts = \CIBlockElement::GetList(
                    [],
                    [
                        'PROPERTY_' . self::PROP_PRIMENIMOST . '_VALUE' => $autoId
                    ],
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

                    if ($newPartName !== $p['NAME']) {
                        $updated = false;

                        if (self::$mode === 'update') {
                            $el = new \CIBlockElement;
                            $updated = $el->Update($p['ID'], ['NAME' => $newPartName]);
                        }

                        self::log('PART', [
                            'ID' => $p['ID'],
                            'OLD' => $p['NAME'],
                            'NEW' => $newPartName,
                            'UPDATED' => $updated
                        ]);

                        $parts[] = [
                            'PART_ID' => $p['ID'],
                            'OLD' => $p['NAME'],
                            'NEW' => $newPartName,
                            'UPDATED' => $updated
                        ];
                    }
                }

                $result['AUTOS'][] = [
                    'AUTO_ID' => $autoId,
                    'OLD_NAME' => $section['NAME'],
                    'NEW_NAME' => $newAutoName,
                    'AUTO_CHANGED' => $autoChanged,
                    'PARTS_CHANGED' => count($parts),
                    'PARTS' => $parts
                ];
            }

            if (self::$mode === 'update') {
                $DB->Commit();
            }

        } catch (\Throwable $e) {

            if (self::$mode === 'update') {
                $DB->Rollback();
            }

            self::log('ROLLBACK', ['error' => $e->getMessage()]);
            $result['ERRORS'][] = $e->getMessage();
        }

        self::log('FINISH');

        header('Content-Type: application/json; charset=utf-8');
        echo '<pre>' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        die();
    }

    /**
     * 🔑 ЧТЕНИЕ CSV С АВТООПРЕДЕЛЕНИЕМ РАЗДЕЛИТЕЛЯ
     */
    private static function readCsv(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        $firstLine = $lines[0];
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        return array_map(
            fn($line) => str_getcsv($line, $delimiter),
            $lines
        );
    }

    private static function cleanName(string $text): string
    {
        $text = preg_replace('/\.+/', '', $text);
        $text = preg_replace('/\s*\[\d+\]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function updatePartName(string $partName, string $oldAuto, string $newAuto): string
    {
        // уже есть новое — не трогаем
        if (mb_stripos($partName, $newAuto) !== false) {
            return $partName;
        }

        // заменяем старое на новое (1 раз)
        if ($oldAuto && mb_stripos($partName, $oldAuto) !== false) {
            return preg_replace(
                '/' . preg_quote($oldAuto, '/') . '/u',
                $newAuto,
                $partName,
                1
            );
        }

        // ничего не делаем
        return $partName;
    }

    private static function log(string $type, array $data = []): void
    {
        $line = date('Y-m-d H:i:s') . " | {$type}";
        if ($data) {
            $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;

        $path = $_SERVER['DOCUMENT_ROOT'] . self::LOG_FILE;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $line, FILE_APPEND);
    }
}

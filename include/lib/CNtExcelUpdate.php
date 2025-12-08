<?php
namespace NtExcelUpdate;

use CIBlockElement;

class CNtExcelUpdate
{
    public static function process($file)
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($ext == "xlsx") {
            $rows = self::loadXLSX($file);
        } else {
            $rows = array_map('str_getcsv', file($file));
        }

        $updated = [];

        foreach ($rows as $r) {
            if (!isset($r[0])) continue;

            $name = trim($r[0]);

            // --- Ищем ID в квадратных скобках ---
            if (preg_match('/\[(\d+)\]/', $name, $m)) {
                $id = intval($m[1]);

                // --- Обновление названия элемента ---
                $el = new CIBlockElement;
                $el->Update($id, ["NAME" => $name]);

                $updated[] = "Обновлено: ID $id → $name";
            }
        }

        return $updated;
    }

    private static function loadXLSX($file)
    {
        if ($xlsx = \SimpleXLSX::parse($file)) {
            return $xlsx->rows();
        }
        return [];
    }
}

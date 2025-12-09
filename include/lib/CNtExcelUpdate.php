<?php
// Основная логика: парсинг файла, обновление разделов и затем - обновление названий элементов

class CNtExcelUpdate
{
    protected $iblockId = 21; // целевой инфоблок с запчастями
    // если у вас другой IBLOCK_ID — замените здесь или прокиньте в конструкторе

    public function __construct($iblockId = 21)
    {
        $this->iblockId = intval($iblockId);
    }

    /**
     * Основной процессинг загруженного файла
     * Поддерживается CSV и простые XLSX (SimpleXLSX минимальная реализация).
     * Формат ожидаемых строк:
     *   [0] - Как сейчас (old)  — пример: ". . . . . . 100 2 (C2) 1978 - 1982 [1218]"
     *   [1] - Как нужно (new)   — пример: ". . . . . . 100 С2 1978 - 1982 [1218]" или "100 C2 1978 - 1982"
     */
    public function processUploadedFile($tmpPath, $originalName)
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $rows = [];

        if (in_array($ext, ['csv', 'txt'])) {
            $rows = $this->parseCsv($tmpPath);
        } else {
            if (!class_exists('SimpleXLSX')) {
                return "SimpleXLSX not available";
            }
            $xlsx = SimpleXLSX::parse($tmpPath);
            if (!$xlsx) return 'Ошибка чтения xlsx';
            // some SimpleXLSX implementations return $xlsx->rows(), others ->rows
            if (is_callable([$xlsx, 'rows'])) $rows = $xlsx->rows();
            elseif (isset($xlsx->rows) && is_array($xlsx->rows)) $rows = $xlsx->rows;
            else $rows = (array)$xlsx->rows;
        }

        if (empty($rows)) return 'Файл пуст или не прочитан';

        CModule::IncludeModule('iblock');

        $log = [];
        foreach ($rows as $r) {
            $old = trim($r[0] ?? '');
            $new = trim($r[1] ?? '');
            if ($old === '' || $new === '') continue;

            // извлекаем ID раздела из квадратных скобок в старом названии, если есть
            $sectionId = null;
            if (preg_match('/\[(\d+)\]/', $old, $m)) {
                $sectionId = intval($m[1]);
            }

            if ($sectionId) {
                // обновляем раздел по ID
                $bs = new CIBlockSection();
                $res = CIBlockSection::GetByID($sectionId);
                if ($ar = $res->Fetch()) {
                    $ok = $bs->Update($sectionId, ['NAME' => $new]);
                    if ($ok) {
                        $log[] = "Раздел #$sectionId обновлён: \"$old\" -> \"$new\"";
                    } else {
                        $log[] = "Ошибка обновления раздела #$sectionId: \"$old\" -> \"$new\"";
                    }
                } else {
                    $log[] = "Раздел с ID $sectionId не найден (строка: \"$old\")";
                }

                // ищем элементы, в названии которых встречается старая часть имени (без [ID])
                $searchPattern = preg_replace('/\s*\[\d+\]/', '', $old);
                $searchPattern = trim($searchPattern);
                if ($searchPattern !== '') {
                    $cnt = $this->updateElementsReplaceName($searchPattern, $new);
                    $log[] = "Обновлено названий элементов: $cnt (\"$searchPattern\" -> \"$new\")";
                }
            } else {
                // если ID не найден, попробуем найти раздел по точному совпадению NAME
                $dbs = CIBlockSection::GetList([], ['IBLOCK_ID' => $this->iblockId, 'NAME' => $old], false);
                if ($ar = $dbs->Fetch()) {
                    $sid = $ar['ID'];
                    $bs = new CIBlockSection();
                    $ok = $bs->Update($sid, ['NAME' => $new]);
                    $log[] = $ok ? "Раздел #$sid обновлён: \"$old\" -> \"$new\"" : "Ошибка обновления раздела #$sid";
                    $searchPattern = preg_replace('/\s*\[\d+\]/', '', $old);
                    $searchPattern = trim($searchPattern);
                    if ($searchPattern !== '') {
                        $cnt = $this->updateElementsReplaceName($searchPattern, $new);
                        $log[] = "Обновлено названий элементов: $cnt (\"$searchPattern\" -> \"$new\")";
                    }
                } else {
                    $log[] = "Раздел не найден по имени: \"$old\"";
                }
            }
        }

        return implode(PHP_EOL, $log);
    }

    /**
     * Ищем элементы в iblockId с NAME LIKE %$search% и заменяем в NAME -> $replace
     * Возвращаем количество обновлённых элементов.
     */
    protected function updateElementsReplaceName($search, $replace)
    {
        $cnt = 0;
        $filter = ['IBLOCK_ID' => $this->iblockId, '%NAME' => $search];
        $rs = CIBlockElement::GetList([], $filter, false, false, ['ID', 'NAME']);
        while ($ar = $rs->GetNext()) {
            $id = intval($ar['ID']);
            $name = $ar['NAME'];
            $newName = str_replace($search, $replace, $name);
            if ($newName !== $name) {
                $el = new CIBlockElement();
                $ok = $el->Update($id, ['NAME' => $newName]);
                if ($ok) $cnt++;
            }
        }
        return $cnt;
    }

    protected function parseCsv($path)
    {
        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($h, 0, ',')) !== false) {
                $rows[] = $data;
            }
            fclose($h);
        }
        return $rows;
    }
}

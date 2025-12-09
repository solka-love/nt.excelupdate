<?php
class SimpleXLSX {
    public static function parse($file) {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) return false;
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($xml === false) {
            $zip->close();
            return false;
        }
        preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $xml, $r);
        $rows = [];
        foreach ($r[1] as $rowXml) {
            preg_match_all('/<v>(.*?)<\/v>/s', $rowXml, $vals);
            $rows[] = $vals[1];
        }
        $zip->close();
        // совместимость: вернуть объект с callable rows()
        return (object)[
            'rows' => $rows,
            'rowsCount' => count($rows),
            'rowsFunc' => function() use ($rows){ return $rows; }
        ];
    }
    public static function parseError(){return 'error';}
    public function rows(){ return []; }
}

<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Loader;
Loader::includeModule('iblock');

require_once($_SERVER['DOCUMENT_ROOT'].'/local/modules/nt.excelupdate/include/include.php');

$iblock = 21; // при необходимости изменить

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['FILE'])) {
    $tmp = $_FILES['FILE']['tmp_name'];
    $name = $_FILES['FILE']['name'];
    $handler = new CNtExcelUpdate($iblock);
    $result = $handler->processUploadedFile($tmp, $name);
    echo '<div style="white-space:pre-wrap; background:#f7f7f7; padding:10px; border:1px solid #ddd;">'.htmlspecialchars($result).'</div>';
}
?>

<h1>NT Excel Update — Обновление разделов и наименований</h1>
<p>Формат: CSV или XLSX. Первая колонка — <b>Как сейчас</b> (например: <code>. . . . . . 100 2 (C2) 1978 - 1982г[1218]</code>), вторая колонка — <b>Как нужно</b> (новое название).</p>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="FILE" accept=".csv,.xlsx" required>
    <br><br>
    <input type="submit" value="Загрузить и обновить" class="adm-btn-save">
</form>

<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php'); ?>

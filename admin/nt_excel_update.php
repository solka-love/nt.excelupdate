<?php
require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php";
require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/nt.excelupdate/include.php";

if ($_FILES['file']) {
    $res = \NtExcelUpdate\CNtExcelUpdate::process($_FILES['file']['tmp_name']);
    echo "<pre>";
    print_r($res);
    echo "</pre>";
}
?>

<h2>Импорт Excel / CSV</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="file">
    <br><br>
    <button class="adm-btn-save" type="submit">Загрузить</button>
</form>

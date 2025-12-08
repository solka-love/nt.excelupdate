<?php
use Bitrix\Main\ModuleManager;

class nt_excelupdate extends CModule
{
    public $MODULE_ID = "nt.excelupdate";
    public $MODULE_VERSION = "1.0.0";
    public $MODULE_VERSION_DATE = "2025-12-05";
    public $MODULE_NAME = "Excel Update Module";
    public $MODULE_DESCRIPTION = "Импорт из Excel/CSV и обновление элементов по ID в скобках";

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);

        CopyDirFiles(
            __DIR__ . "/../admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin",
            true,
            true
        );

        return true;
    }

    public function DoUninstall()
    {
        DeleteDirFiles(
            __DIR__ . "/../admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"
        );

        UnRegisterModule($this->MODULE_ID);
        return true;
    }
}

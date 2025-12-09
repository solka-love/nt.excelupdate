<?php
use Bitrix\Main\ModuleManager;

class nt_excelupdate extends CModule
{
    public $MODULE_ID = "nt.excelupdate";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = "NT Excel Update";
    public $MODULE_DESCRIPTION = "Обновление разделов и наименований элементов из Excel";

    public function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__.'/version.php');
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        CopyDirFiles(__DIR__ . "/../admin", $_SERVER['DOCUMENT_ROOT'] . "/bitrix/admin", true, true);
        CopyDirFiles(__DIR__ . "/../include", $_SERVER['DOCUMENT_ROOT'] . "/local/modules/".$this->MODULE_ID, true, true);
        return true;
    }

    public function DoUninstall()
    {
        DeleteDirFiles(__DIR__ . "/../admin", $_SERVER['DOCUMENT_ROOT'] . "/bitrix/admin");
        UnRegisterModule($this->MODULE_ID);
        return true;
    }
}

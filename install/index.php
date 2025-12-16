<?php
use Bitrix\Main\ModuleManager;

class solka_module extends CModule
{
    public $MODULE_ID = 'solka_module';
    public $MODULE_NAME = 'Solka: Обновление авто и запчастей';
    public $MODULE_DESCRIPTION = 'Обновление названий авто и запчастей из Excel';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2025-12-08';

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}

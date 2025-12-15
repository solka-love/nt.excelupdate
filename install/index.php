<?php
// /local/modules/solka_module/install/index.php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

class solka_module extends CModule
{
    public $MODULE_ID = 'solka_module';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    
    public function __construct()
    {
        $arModuleVersion = [];
        
        include __DIR__ . '/version.php';
        
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('SOLKA_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SOLKA_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('SOLKA_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('SOLKA_PARTNER_URI');
    }
    
    public function DoInstall()
    {
        global $APPLICATION;
        
        // Проверяем права
        if (!check_bitrix_sessid()) {
            return false;
        }
        
        // Регистрируем модуль
        ModuleManager::registerModule($this->MODULE_ID);
        
        // Создаем директорию для загрузки файлов
        $uploadDir = Application::getDocumentRoot() . '/upload/solka_updater';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Регистрируем административный файл
        RegisterModuleDependences(
            "main",
            "OnBuildGlobalMenu",
            $this->MODULE_ID,
            "CSolkaModuleAdmin",
            "OnBuildGlobalMenu"
        );
        
        // Копируем административный файл
        CopyDirFiles(
            __DIR__ . '/admin',
            Application::getDocumentRoot() . '/bitrix/admin',
            true,
            true
        );
        
        // Копируем публичную часть (если нужна)
        CopyDirFiles(
            __DIR__ . '/public',
            Application::getDocumentRoot() . '/solka_updater',
            true,
            true
        );
        
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('SOLKA_INSTALL_TITLE'),
            __DIR__ . '/step.php'
        );
        
        return true;
    }
    
    public function DoUninstall()
    {
        global $APPLICATION;
        
        // Проверяем права
        if (!check_bitrix_sessid()) {
            return false;
        }
        
        // Удаляем административный файл
        DeleteDirFiles(
            __DIR__ . '/admin',
            Application::getDocumentRoot() . '/bitrix/admin'
        );
        
        // Удаляем публичную часть
        DeleteDirFiles(
            __DIR__ . '/public',
            Application::getDocumentRoot() . '/solka_updater'
        );
        
        // Отменяем регистрацию модуля
        ModuleManager::unregisterModule($this->MODULE_ID);
        
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('SOLKA_UNINSTALL_TITLE'),
            __DIR__ . '/unstep.php'
        );
        
        return true;
    }
    
    public function InstallFiles()
    {
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/solka_module/install/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true
        );
        return true;
    }
    
    public function UnInstallFiles()
    {
        DeleteDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/solka_module/install/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );
        return true;
    }
}
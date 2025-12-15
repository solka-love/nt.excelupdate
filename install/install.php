<?php
class fobyte_autoupdate extends CModule
{
    public function __construct()
    {
        $this->MODULE_ID = "fobyte.autoupdate";
        $this->MODULE_NAME = "Обновление авто и запчастей";
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        return true;
    }

    public function DoUninstall()
    {
        UnRegisterModule($this->MODULE_ID);
        return true;
    }
}

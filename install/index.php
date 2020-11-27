<?
/**
 * User: Pavel Kopytov
 */

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Config as Conf;
use \Bitrix\Main\Config\Option;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Entity\Base;
use \Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

Class kopytov_clsync extends CModule
{
    var $exclusionAdminFiles;

    function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");

        $this->exclusionAdminFiles = array(
            '..',
            '.',
            'menu.php',
            'operation_description.php',
            'task_description.php'
        );

        $this->MODULE_ID = 'kopytov.clsync';
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage("KOPYTOV_CLSYNC_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("KOPYTOV_CLSYNC_MODULE_DESC");

        $this->PARTNER_NAME = Loc::getMessage("KOPYTOV_CLSYNC_PARTNER_NAME");
        $this->PARTNER_URI = Loc::getMessage("KOPYTOV_CLSYNC_PARTNER_URI");

        $this->MODULE_SORT = 1;
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS = "Y";
    }

    //Определяем место размещения модуля
    public function GetPath($notDocumentRoot = false)
    {
        if ($notDocumentRoot)
            return str_ireplace(Application::getDocumentRoot(), '', dirname(__DIR__));
        else
            return dirname(__DIR__);
    }

    //Проверяем что система поддерживает D7
    public function isVersionD7()
    {
        return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '14.00.00');
    }

    // Проверяем утсановлен ли модуль Торгового каталога
    public function isCatalogInstalled()
    {
        return \Bitrix\Main\ModuleManager::getVersion('catalog');
    }

    function InstallEvents()
    {

//        \Bitrix\Main\EventManager::getInstance()->registerEventHandler($this->MODULE_ID, 'TestEventD7', $this->MODULE_ID, '\Academy\D7\Event', 'eventHandler');
    }

    function UnInstallEvents()
    {
//        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler($this->MODULE_ID, 'TestEventD7', $this->MODULE_ID, '\Academy\D7\Event', 'eventHandler');
    }

    function InstallFiles($arParams = array())
    {

        $path = $this->GetPath() . "/install/components";

        if (\Bitrix\Main\IO\Directory::isDirectoryExists($path)) {
            CopyDirFiles($path, $_SERVER["DOCUMENT_ROOT"] . "/bitrix/components", true, true);
        } else {
            throw new \Bitrix\Main\IO\InvalidPathException($path);
        }

        if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin')) {
            CopyDirFiles($this->GetPath() . "/install/admin/", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"); //если есть файлы для копирования
            if ($dir = opendir($path)) {
                while (false !== $item = readdir($dir)) {
                    if (in_array($item, $this->exclusionAdminFiles))
                        continue;
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $this->MODULE_ID . '_' . $item,
                        '<' . '? require($_SERVER["DOCUMENT_ROOT"]."' . $this->GetPath(true) . '/admin/' . $item . '");?' . '>');
                }
                closedir($dir);
            }
        }

        return true;
    }

    function UnInstallFiles()
    {

        \Bitrix\Main\IO\Directory::deleteDirectory($_SERVER["DOCUMENT_ROOT"] . '/bitrix/components/' . explode(".", $this->MODULE_ID)[0] . '/');

        if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin')) {
            DeleteDirFiles($_SERVER["DOCUMENT_ROOT"] . $this->GetPath() . '/install/admin/', $_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin');
            if ($dir = opendir($path)) {
                while (false !== $item = readdir($dir)) {
                    if (in_array($item, $this->exclusionAdminFiles))
                        continue;
                    \Bitrix\Main\IO\File::deleteFile($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $this->MODULE_ID . '_' . $item);
                }
                closedir($dir);
            }
        }

        return true;
    }

    function DoInstall()
    {

        global $APPLICATION;

        $prepModuleName = strtr($this->MODULE_ID, array('.' => '_'));

        if ($this->isVersionD7() && $this->isCatalogInstalled()) {
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();

            \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

            #работа с .settings.php
            /*
            $configuration = Conf\Configuration::getInstance();
            $$prepModuleName = array(
                'live' => array(
                    'server_address' => '',
                    'api_key' => ''
                ),
                'dev' => array(
                    'server_address' => '',
                    'api_key' => ''
                )
            );
            $configuration->add($prepModuleName, $$prepModuleName);
            $configuration->saveConfiguration();
            */
            #работа с .settings.php

        } elseif (!$this->isCatalogInstalled()) {
            $APPLICATION->ThrowException(Loc::getMessage('KOPYTOV_CLSYNC_INSTALL_ERROR_CATALOG'));
        } else {
            $APPLICATION->ThrowException(Loc::getMessage('KOPYTOV_CLSYNC_INSTALL_ERROR_VERSION'));
        }

        $APPLICATION->IncludeAdminFile(Loc::getMessage('KOPYTOV_CLSYNC_INSTALL_TITLE'), $this->GetPath() . '/install/step.php');

    }

    function DoUninstall()
    {

        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallEvents();

        \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

        #работа с .settings.php
        /*
        $configuration = Conf\Configuration::getInstance();
        $configuration->delete(strtr($this->MODULE_ID, array('.' => '_')));
        $configuration->saveConfiguration();
        */
        #работа с .settings.php

        $APPLICATION->IncludeAdminFile(Loc::getMessage('KOPYTOV_CLSYNC_UNINSTALL_TITLE'), $this->GetPath() . '/install/unstep.php');

    }


    function GetModuleRightList()
    {

        return array(
            "reference_id" => array("D", "K", "S", "W"),
            "reference" => array(
                "[D] " . Loc::getMessage("KOPYTOV_CLSYNC_DENIED"),
                "[K] " . Loc::getMessage("KOPYTOV_CLSYNC_READ_COMPONENT"),
                "[S] " . Loc::getMessage("KOPYTOV_CLSYNC_WRITE_SETTINGS"),
                "[W] " . Loc::getMessage("KOPYTOV_CLSYNC_FULL"))
        );

    }
}

?>
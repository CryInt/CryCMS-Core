<?php
namespace CryCMS;

use RuntimeException;

class Core
{
    protected $url = [];
    protected $configs = [];
    protected $moduleParams = [];

    protected $runningModule;
    protected $runningModuleParams = [];

    public $configsPath = 'Configs/';
    public $modulesPath = 'Modules/';

    protected $baseUrl;
    protected $router;
    protected $template;

    protected $instances = [];

    protected static $_core;

    public function __construct($baseUrl = null)
    {
        if ($baseUrl !== null) {
            $this->baseUrl = $baseUrl;
        }

        self::$_core = $this;

        $this->parseUrl();
    }

    public function init(): void
    {
        if (defined('DR') === false) {
            define('DR', $_SERVER['DOCUMENT_ROOT']);
        }

        $this->initConfigs();
    }

    public function initInstance($name, $class, ...$params): void
    {
        $this->instances[$name] = new $class(...$params);
    }

    public function getInstance($name)
    {
        if ($name === 'template') {
            return $this->template;
        }

        if (!isset($this->instances[$name])) {
            throw new RuntimeException('Instance "' . $name . '" not init', 5);
        }

        return $this->instances[$name];
    }

    public function initAutoload($path): void
    {
        spl_autoload_register(static function ($class) use ($path) {
            $classPath = DR . '/' . $path . $class . '.php';
            if (file_exists($classPath)) {
                require_once $classPath;
            }
        });
    }

    public function run(): void
    {
        $this->initRouter();
        $this->initTemplate();
        $content = $this->runModule();
        $this->template->setContent($content);
        $this->template->run();
    }

    private function parseUrl(): void
    {
        if (!empty($_GET['path'])) {
            $path = Helpers::clean($_GET['path']);
            if ($this->baseUrl !== null) {
                $path = preg_replace('/' . str_replace('/', '\/', $this->baseUrl) . '/', '', $path, 1);
            }
            $path = trim($path, '/');
            $this->url = explode('/', $path);
        }
    }

    private function initConfigs(): void
    {
        $configsPath = DR . '/' . $this->configsPath;
        if (file_exists($configsPath) && is_dir($configsPath)) {
            $list = glob($configsPath . "*.php");
            if (!empty($list) && count($list) > 0) {
                foreach ($list as $once) {
                    $class = basename($once, '.php');
                    $classKey = lcfirst(str_replace('Config', '', $class));
                    require_once $once;
                    $this->configs[$classKey] = call_user_func('\Config\\' . $class . '::get');
                }
            }
        }
    }

    private function initRouter(): void
    {
        if (array_key_exists('router', $this->configs)) {
            $this->router = new Router($this->configs['router'], $this->url);
            $this->moduleParams = $this->router->params;
        }
    }

    private function initTemplate(): void
    {
        if (array_key_exists('template', $this->configs)) {
            $this->template = new Template($this->configs['template'], $this);
        }
    }

    public function runModule($moduleName = null, $params = [], $echo = false)
    {
        $parentModule = $this->getRunningModule();

        if ($moduleName === null) {
            $moduleName = $this->router->getModule();
        }

        $params = array_merge(
            $this->moduleParams,
            $params
        );

        if ($echo) {
            echo $this->runModuleAction($moduleName, $params);

            if ($parentModule !== null) {
                $this->setRunningModule($parentModule);
            }

            return true;
        }

        $return = $this->runModuleAction($moduleName, $params);

        if ($parentModule !== null) {
            $this->setRunningModule($parentModule);
        }

        return $return;
    }

    protected function runModuleAction($moduleName, $params = [])
    {
        $modulePath = $this->getModulePath($moduleName);

        if (!file_exists($modulePath)) {
            throw new RuntimeException('Module "' . $moduleName . '" not exists', 1);
        }

        $this->setRunningModule($moduleName);
        $this->setRunningModuleParams($params);

        ob_start();
        include($modulePath);
        return ob_get_clean();
    }

    protected function setRunningModuleParams($params): void
    {
        $this->runningModuleParams = $params;
    }

    public function getRunningModuleParams(): array
    {
        return $this->runningModuleParams;
    }

    protected function setRunningModule($module): void
    {
        $this->runningModule = $module;
    }

    public function getRunningModule(): ?string
    {
        return $this->runningModule;
    }

    protected function getModulePath($moduleName): string
    {
        return DR . '/' . $this->modulesPath . $moduleName . '/controller.php';
    }

    public static function this(): Core
    {
        return self::$_core;
    }

    public function end($withDb = false): void
    {
        if (
            PHP_SAPI !== 'cli' &&
            !empty($_ENV['DEBUG']) &&
            (
                (
                    is_object($this->template) &&
                    $this->template->contentOnly !== true
                )
                ||
                is_object($this->template) === false
            )
        ) {
            Helpers::apre('END');
            if ($withDb && class_exists(Db::class)) {
                Helpers::apre(Db::getLog());
            }
        }
    }
}
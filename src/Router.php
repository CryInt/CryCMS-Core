<?php
namespace CryCMS;

class Router
{
    protected $module = '404';
    protected $config;

    public $params = [];

    public function __construct($config, $url)
    {
        $this->config = $config;

        $this->checkBefore();

        if (
            is_array($config) &&
            array_key_exists('beforeFalse', $config) &&
            $config['beforeFalse'] === $this->module
        ) {
            return;
        }

        $this->findModuleByRoutes($url);
    }

    protected function checkBefore(): void
    {
        if (empty($this->config['before'])) {
            return;
        }

        if (is_array($this->config['before'])) {
            foreach ($this->config['before'] as $once) {
                if ($once() === false) {
                    $this->module = $this->config['beforeFalse'];
                    if (!empty($this->config['beforeFalseParams'])) {
                        $this->params = $this->config['beforeFalseParams'];
                    }
                    break;
                }
            }
        }
        elseif ($this->config['before']() === false) {
            $this->module = $this->config['beforeFalse'];
        }
    }

    protected function findModuleByRoutes($url): void
    {
        if (empty($this->config['routes'])) {
            return;
        }

        if (empty($url) && $this->findModuleByRoutesOnce('/', [], []) === true) {
            return;
        }

        if ($this->findModuleByRoutesOnce('/*', $url, []) === true) {
            return;
        }

        $this->findModuleByRoutesSlice($url);
    }

    protected function findModuleByRoutesSlice($url, int $sliceCount = 0): void
    {
        $slice = array_slice($url, 0, count($url) - $sliceCount);
        if (empty($slice)) {
            return;
        }

        $find = implode('/', $slice);
        if ($sliceCount > 0) {
            $find .= '/*';
        }

        if ($this->findModuleByRoutesOnce($find, $url, $slice) === true) {
            return;
        }

        $this->findModuleByRoutesSlice($url, ($sliceCount + 1));
    }

    protected function findModuleByRoutesOnce($find, $url, $slice): bool
    {
        if (isset($this->config['routes'][$find]) && !empty($this->config['routes'][$find]['module'])) {
            $this->module = $this->config['routes'][$find]['module'];

            if (!empty($this->config['routes'][$find]['params'])) {
                $this->params = $this->config['routes'][$find]['params'];
            }

            $this->params = array_merge(
                $this->params,
                array_values(array_diff($url, $slice))
            );

            return true;
        }

        return false;
    }

    public function getModule(): string
    {
        return $this->module;
    }
}
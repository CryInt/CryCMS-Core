<?php
namespace CryCMS;

use http\Exception\RuntimeException;

class Router
{
    /** @var array{
     *     before?: string|array<string>,
     *     beforeFalse?: string,
     *     beforeFalseParams?: array<string, string>,
     *     routes?: array<string, array{module?: string, params?: array{string, string}}>
     * }
     */
    protected array $config;
    protected string $module = '404';

    /** @var array<string> $params */
    public array $params = [];

    /**
     * @param array{
     *      before?: string|array<string>,
     *      beforeFalse?: string,
     *      beforeFalseParams?: array<string, string>,
     *      routes?: array<string, array{module?: string, params?: array{string, string}}>
     *  } $config
     * @param array<string> $url
     */
    public function __construct(array $config, array $url)
    {
        $this->config = $config;

        $this->checkBefore();

        if (
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

        if (empty($this->config['beforeFalse'])) {
            throw new RuntimeException('action "beforeFalse" should be defined if "before" is defined');
        }

        if (is_array($this->config['before'])) {
            foreach ($this->config['before'] as $once) {
                if (is_callable($once) && $once() === false) {
                    $this->module = $this->config['beforeFalse'];
                    if (!empty($this->config['beforeFalseParams'])) {
                        $this->params = $this->config['beforeFalseParams'];
                    }
                    break;
                }
            }
        }
        elseif (is_callable($this->config['before']) && $this->config['before']() === false) {
            $this->module = $this->config['beforeFalse'];
        }
    }

    /**
     * @param array<string> $url
     *
     * @return void
     */
    protected function findModuleByRoutes(array $url): void
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

    /**
     * @param array<string> $url
     * @param int $sliceCount
     *
     * @return void
     */
    protected function findModuleByRoutesSlice(array $url, int $sliceCount = 0): void
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

    /**
     * @param string $find
     * @param array<string> $url
     * @param array<string> $slice
     *
     * @return bool
     */
    protected function findModuleByRoutesOnce(string $find, array $url, array $slice): bool
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
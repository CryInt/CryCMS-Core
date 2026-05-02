<?php
namespace CryCMS;

use Exception;
use RuntimeException;

/**
 * @phpstan-type elementArrayString array<string, array<string, string>>
 *
 * @phpstan-type elementArrayStringOrBool array<string, array<string, string>|array<string, bool>>
 *
 * @phpstan-type headArray array{
 *     meta?: elementArrayStringOrBool,
 *     css?: elementArrayStringOrBool,
 *     js?: elementArrayStringOrBool,
 *     favicon?: string,
 *     canonical?: string,
 *     link?: elementArrayStringOrBool
 * }
 */
class Template
{
    protected Core $core;

    /**
     * @var array{
     *     template: string,
     *     vars: array<string, string>,
     *     head: headArray
     * }
     */
    protected array $config;

    /**
     * @var headArray
     */
    private array $head = [
        'meta'         => [],
        'css'          => [],
        'js'           => [],
        'favicon'      => '',
        'canonical'    => '',
        'link'         => [],
    ];

    protected string $content = '';

    protected string $fullContent = '';

    /**
     * @var array<string, string>
     */
    protected array $_vars = [];

    public bool $contentOnly = false;

    /**
     * @param array{
     *     template: string,
     *     vars: array<string, string>,
     *     head: headArray
     * } $config
     * @param Core $core
     */
    public function __construct(array $config, Core $core)
    {
        $this->core = $core;
        $this->config = $config;

        if (empty($this->config['template'])) {
            throw new RuntimeException('Template is not specified in config', 1);
        }

        if (!file_exists(DR . '/' . $this->config['template'] . '/content.php')) {
            throw new RuntimeException('Template "' . $this->config['template'] . '" is not exists', 2);
        }

        if (!defined('DR')) {
            throw new RuntimeException('DR constant should be defined as server document_root', 4);
        }

        $this->parseConfigVars();
    }

    private function parseConfigVars(): void
    {
        if (!empty($this->config['vars'])) {
            foreach ($this->config['vars'] as $key => $value) {
                $this->setVar($key, $value);
            }
        }

        if (!empty($this->config['head'])) {
            foreach ($this->config['head'] as $type => $values) {
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        $this->setHead($type, $key, $value);
                    }
                }
                else {
                    $this->setHead($type, null, $values);
                }
            }
        }
    }

    public function setContent(string $content, bool $replace = false): void
    {
        if ($replace === false) {
            $this->content .= $content;
            return;
        }

        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $type
     * @param string|null $key
     * @param string|elementArrayStringOrBool|array<string, string> $value
     * @return bool
     */
    public function setHead(string $type, ?string $key, string|array $value): bool
    {
        if (!isset($this->head[$type])) {
            return false;
        }

        if (in_array($type, ['meta', 'css', 'js', 'link'], true) && is_array($value)) {
            if ($key === null) {
                $this->head[$type] = $value;
            }
            else {
                $this->head[$type][$key] = $value;
            }

            return true;
        }

        if ($type === 'favicon' && is_string($value)) {
            $this->head[$type] = $value;
        }

        /*
        if (is_array($value)) {
            $mayBeLink = $value['src'] ?? $value['href'] ?? false;
        }
        else {
            $mayBeLink = $value;
        }

        if (is_string($mayBeLink) && !str_contains($mayBeLink, 'http')) {
            if (empty($value['absolute']) || $value['absolute'] !== true) {
                $mayBeLinkWithPath = '/' . $this->config['template'] . $mayBeLink;
            }
            else {
                $mayBeLinkWithPath = $mayBeLink;
            }

            if (file_exists(DR . $mayBeLinkWithPath) === false) {
                if (!empty($_ENV['DEBUG'])) {
                    Helpers::apre('file not exists: ' . DR . $mayBeLinkWithPath);
                }

                return false;
            }

            $version = $this->getVersion();

            $mayBeLinkWithPath .= (!empty($version) ? '?' . $version : '');

            if (is_array($value)) {
                $arrayKey = array_search($mayBeLink, $value, true);
                if ($arrayKey !== false) {
                    $value[$arrayKey] = $mayBeLinkWithPath;
                }
            }
            else {
                $value = $mayBeLinkWithPath;
            }
        }

        if ($key !== null) {
            $this->head[$type][$key] = $value;
        }
        else {
            $this->head[$type] = $value;
        }

        return true;
        */

        return false;
    }

    /**
     * @param string $type
     * @return null|string|elementArrayStringOrBool
     */
    public function getHead(string $type): null|string|array
    {
        return $this->head[$type] ?? null;
    }

    public function setVar(string $key, string $value, bool $append = false): void
    {
        if ($append === true) {
            $this->_vars[$key] = ($this->_vars[$key] ?? '' ) . $value;
            return;
        }

        $this->_vars[$key] = $value;
    }

    public function getVar(string $key): ?string
    {
        return $this->_vars[$key] ?? null;
    }

    public function render(): void
    {
        $this->renderPart('header');
        $this->renderPart('content');
        $this->renderPart('footer');

        $this->runModules();
        $this->placeVariables();
    }

    protected function getVersion(): string
    {
        $version = '';

        if (!empty($this->config['vars']['version'])) {
            $version = $this->config['vars']['version'];
        }

        if (!empty($_ENV['DEBUG'])) {
            try {
                $version = (string)random_int(1000000, 9999999);
            }
            catch (Exception) {}
        }

        return $version;
    }

    private function renderPart(string $part): void
    {
        $headerFile = DR . '/' . $this->config['template'] . '/' . $part . '.php';

        if (!file_exists($headerFile)) {
            throw new RuntimeException($part . ' part of template is not exists', 3);
        }

        ob_start();
        require_once($headerFile);
        $this->fullContent .= ob_get_clean();
    }

    private function runModules(): void
    {
        preg_match_all('/{{::(.*)::}}/U', $this->fullContent, $modules);

        if (!empty($modules[1])) {
            foreach ($modules[1] as $one) {
                $data = explode('::', $one);
                if (!empty($data[0])) {
                    $module = $data[0];
                    unset($data[0]);
                    $param = self::explodeModuleParam($data);

                    $moduleContent = '';

                    try {
                        $moduleContent = $this->core->runModule($module, $param);
                    } catch (RuntimeException $e) {
                        Helpers::aPre($e->getMessage());
                    }

                    $this->fullContent = str_replace('{{::' . $one . '::}}', $moduleContent, $this->fullContent);
                }
            }
        }
    }

    /**
     * @param array<string> $vars
     * @return array<string, string>
     */
    private static function explodeModuleParam(array $vars): array
    {
        $param = [];

        if (!empty($vars)) {
            foreach ($vars as $one) {
                $exp = explode('=', $one);
                if (count($exp) === 2) {
                    $param[$exp[0]] = $exp[1];
                }
            }
        }

        return $param;
    }

    private function placeVariables(): void
    {
        preg_match_all('/{{(.*)}}/U', $this->fullContent, $vars);

        if (!empty($vars[1])) {
            foreach ($vars[1] as $one) {
                $value = $this->getVar($one);
                if ($value !== null) {
                    $this->fullContent = str_replace('{{' . $one . '}}', $value, $this->fullContent);
                    continue;
                }

                $this->fullContent = str_replace('{{' . $one . '}}', '', $this->fullContent);
                if (!empty($_ENV['DEBUG'])) {
                    Helpers::aPre('Content var: ' . $one . ' is not isset');
                }
            }
        }
    }

    /**
     * @param string $template
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function module(string $template, array $params = []): void
    {
        extract($params, EXTR_SKIP);

        $runningModule = $this->core->getRunningModule();
        if ($runningModule === null) {
            return;
        }

        // first - in this module template
        $path = DR . '/' . $this->core->modulesPath . $runningModule . '/templates/' . $template . '.tpl.php';
        if (file_exists($path)) {
            include $path;
            return;
        }

        // second - in all templates of modules
        $path = DR . '/' . $this->core->modulesPath . '.templates/' . $template . '.tpl.php';
        if (file_exists($path)) {
            include $path;
            return;
        }

        // third - in site template
        $path = DR . '/' . $this->config['template'] . '/modules/' . $runningModule . '/' . $template . '.tpl.php';
        if (file_exists($path)) {
            include $path;
            return;
        }

        // fourth - in all templates in site templates
        $path = DR . '/' . $this->config['template'] . '/.templates/' . $template . '.tpl.php';
        if (file_exists($path)) {
            include $path;
            return;
        }

        throw new RuntimeException('Template "' . $template . '" is not exists in module "' . $runningModule . '"', 4);
    }

    public function run(): void
    {
        if ($this->contentOnly === true) {
            echo $this->content;
            return;
        }

        $this->render();
        echo $this->fullContent;
    }
}
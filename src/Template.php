<?php
namespace CryCMS;

use Exception;
use RuntimeException;

class Template
{
    protected $core;

    protected $config;

    private $head = [
        'meta'         => [],
        'css'          => [],
        'js'           => [],
        'favicon'      => '',
        'canonical'    => '',
        'link'         => [],
    ];

    protected $content = '';

    protected $fullContent = '';

    protected $_vars = [];

    public $contentOnly = false;

    public function __construct($config, $core)
    {
        $this->core = $core;
        $this->config = $config;

        if (empty($this->config['template'])) {
            throw new RuntimeException('Template not specified in config', 1);
        }

        if (!file_exists(DR . '/' . $this->config['template'] . '/content.php')) {
            throw new RuntimeException('Template "' . $this->config['template'] . '" not exists', 2);
        }

        $this->parseConfigVars();
    }

    private function parseConfigVars(): void
    {
        if (!empty($this->config['vars']) && is_array($this->config['vars']) && count($this->config['vars']) > 0) {
            foreach ($this->config['vars'] as $key => $value) {
                $this->setVar($key, $value);
            }
        }

        if (!empty($this->config['head']) && is_array($this->config['head']) && count($this->config['head']) > 0) {
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

    public function setContent($content, $replace = false): void
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

    public function setHead($type, $key, $value): bool
    {
        if (!isset($this->head[$type])) {
            return false;
        }

        if (is_array($value)) {
            $mayBeLink = $value['src'] ?? $value['href'] ?? false;
        }
        else {
            $mayBeLink = $value;
        }

        if ($mayBeLink !== false && strpos($mayBeLink, 'http') === false) {
            $mayBeLinkWithPath = '/' . $this->config['template'] . $mayBeLink;
            if (file_exists(DR . $mayBeLinkWithPath) === false) {
                if (!empty($_ENV['DEBUG'])) {
                    Helpers::apre('file not exists: ' . DR . $mayBeLinkWithPath);
                }

                return false;
            }

            $version = '';

            if (!empty($this->config['vars']['version'])) {
                $version = $this->config['vars']['version'];
            }

            if (!empty($_ENV['DEBUG'])) {
                try {
                    $version = random_int(1000000, 9999999);
                }
                catch (Exception $e) {}
            }

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
    }

    public function getHead($type)
    {
        return $this->head[$type] ?? false;
    }

    public function setVar($key, $value, $append = false): void
    {
        if ($append === true) {
            $this->_vars[$key] = ($this->_vars[$key] ?? '' ) . $value;
            return;
        }

        $this->_vars[$key] = $value;
    }

    public function getVar($key)
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

    private function renderPart($part): void
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

        if (!empty($modules[1]) && is_array($modules[1]) && count($modules[1]) > 0) {
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
                        Helpers::apre($e->getMessage());
                    }

                    $this->fullContent = str_replace('{{::' . $one . '::}}', $moduleContent, $this->fullContent);
                }
            }
        }
    }

    private static function explodeModuleParam($vars): array
    {
        $param = [];

        if (!empty($vars) && is_string($vars)) {
            $tmp = explode('::', $vars);
            if (count($tmp) > 0) {
                foreach ($tmp as $one) {
                    $exp = explode('=', $one);
                    if (count($exp) === 2) {
                        $param[$exp[0]] = $exp[1];
                    }
                }
            }
        }

        if (!empty($vars) && is_array($vars) && count($vars) > 0) {
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

        if (!empty($vars[1]) && is_array($vars[1]) && count($vars[1]) > 0) {
            foreach ($vars[1] as $one) {
                $value = $this->getVar($one);
                if ($value !== null) {
                    $this->fullContent = str_replace('{{' . $one . '}}', $value, $this->fullContent);
                    continue;
                }

                $this->fullContent = str_replace('{{' . $one . '}}', '', $this->fullContent);
                if (!empty($_ENV['DEBUG'])) {
                    Helpers::apre('no isset content var: ' . $one);
                }
            }
        }
    }

    public function module($template, array $params = []): void
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

        throw new RuntimeException('Not exist template "' . $template . '" in module "' . $runningModule . '"', 4);
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
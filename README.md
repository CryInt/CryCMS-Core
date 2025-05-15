# Core

---

```php
<?php
use CryCMS\Core;

const DR = __DIR__;

require_once DR . '/vendor/autoload.php';

$core = new Core();
$core->init();
$core->run();
$core->end(true);
```

```php
# RouterConfig.php

<?php
namespace Config;

use CryCMS\ConfigInterface;
use CryCMS\Helpers;

class RouterConfig implements ConfigInterface
{
    public static function get(): array
    {
        return [
            'routes' => [
                '/' => [
                    'module' => 'mainPage',
                ],
            ],
        ];
    }
}
```

```php
# TemplateConfig.php

<?php
namespace Config;

use CryCMS\ConfigInterface;

class TemplateConfig implements ConfigInterface
{
    public static function get(): array
    {
        return [
            'template' => 'Templates/default',
            'vars' => [
                'version' => '0.01',
                'title' => 'Project',
                'copyright' => '&copy 2005-2025',
            ],
            'head' => [
                'meta' => [
                    'content-type' => [
                        'http-equiv' => 'content-type',
                        'content' => 'text/html; charset=utf-8',
                    ],
                    'theme-color' => [
                        'name' => 'theme-color',
                        'content' => '#7952b3',
                    ],
                    'viewport' => [
                        'name' => 'viewport',
                        'content' => 'width=device-width, initial-scale=1',
                    ],
                ],
                'css' => [
                    'main' => [
                        'src' => '/css/main.css',
                    ],
                ],
                'js' => [
                    'main' => [
                        'src' => '/js/main.js',
                    ],
                ],
                'favicon' => '/images/favicon.ico',
                'link' => [
                    'fav' => [
                        'rel' => 'icon',
                        'href' => '/images/favicon.ico',
                        'type' => 'image/x-icon',
                    ],
                ],
            ],
        ];
    }
}
```

```php
# Templates/default/header.php

<!DOCTYPE html>
<html lang='ru'>
<head>
<?php
use CryCMS\HTML;

$favicon = $this->getHead('favicon');
if (!empty($favicon)) {
    echo HTML::link($favicon, 'icon', ['type' => 'image/x-icon']);
}

$meta = $this->getHead('meta');
foreach ($meta as $key => $value) {
    echo HTML::meta($value);
}

$css = $this->getHead('css');
foreach ($css as $key => $value) {
    echo HTML::link($value['src'], 'stylesheet', ['type' => 'text/css']);
}

$link = $this->getHead('link');
foreach ($link as $key => $value) {
    echo HTML::link($value['href'], $value['rel'], $value);
}

$canonical = $this->getHead('canonical');
if (!empty($canonical)) {
    echo HTML::link($canonical, 'canonical');
}
?>
<title>{{title}}</title>
</head>
<body>
```

```php
# Templates/default/content.php

<?php
use CryCMS\Core;

echo $this->content;
```

```php
# Templates/default/footer.php

<?php
use CryCMS\HTML;

$js = $this->getHead('js');
foreach ($js as $key => $value) {
    echo HTML::scriptSrc($value['src'], $value);
}
?>

</body>
</html>
```
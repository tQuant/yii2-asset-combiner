# Asset combiner for Yii 2

Yii 2 extension to compress and concatenate assets

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require tquant/yii2-asset-combiner
```

or add

```
"tquant/yii2-asset-combiner": "*"
```

to the `require` section of your `composer.json` file.

## Configuring

Note: to use `UglifyJsFilter` and `UglifyCssFilter` you must have installed
[UglifyJs](https://github.com/mishoo/UglifyJS2) and [UglifyCSS](https://github.com/fmarcia/UglifyCSS)

### Console command

```php
$config = [
    ...
    'controllerMap' => [
        'asset-combiner' => [
            'class' => 'AssetCombiner\AssetCombinerController',
            'aliases' => [
                '@webroot' => '@app/web',
                '@web' => '/',
            ],
            'assetManager' => [
                'basePath' => '@webroot/assets',
                'baseUrl' => '@web/assets',
            ],

            // Output puth and url
            //'outputPath' => '@webroot/assets/ac',
            //'outputUrl' => '@web/assets/ac',

            // directory where all assets stored
            //'assetsDir' => '@app/assets',

            // assets namespace
            //'assetsNamespace' => 'app\assets',
            
            // recursive search for assets
            //'recursive' => true,
            
            // process dependent assets
            //'processDependent' => true,

            // Additional bundles to minify
            //'bundles' => [
            //    'yii\web\YiiAsset',
            //    'yii\web\JqueryAsset',
            //],

            // Filter to process JS files. If not set, then SimpleJsFilter will be used
            'filterJs' => [
                'class' => 'AssetCombiner\filters\UglifyJsFilter',
                'sourceMap' => false,
                'compress' => false,
                'mangle' => false,
            ],

            // Filter to process JS files. If not set, then SimpleCssFilter will be used
            'filterCss' => 'AssetCombiner\filters\UglifyCssFilter',
        ],
    ],
],
```

### View behavior

```php
$config = [
    ...
    'components' => [
        ...
        'view' => [
            'class' => 'yii\web\View',
            'as assetCombiner' => [
                'class' => 'AssetCombiner\AssetCombinerBehavior',

                // Output puth and url
                //'outputPath' => '@webroot/assets/ac',
                //'outputUrl' => '@web/assets/ac',

                // Filter to process JS files. If not set, then SimpleJsFilter will be used
                'filterJs' => [
                    'class' => 'AssetCombiner\filters\UglifyJsFilter',
                    'sourceMap' => false,
                    'compress' => false,
                    'mangle' => false,
                ],

                // Filter to process JS files. If not set, then SimpleCssFilter will be used
                'filterCss' => 'AssetCombiner\filters\UglifyCssFilter',
            ],
        ],
    ],
],
```

## Usage

### Precompiled in console
You can use the first configuration option and use this extension as console command

```bash
./yii asset-combiner config/assets.php
```

Execute this command every time when files changed and include generated file into your web config.
This will concat and compress all files into one group (one **js** and one **css**) for each asset.

### Compiled on the fly
You can use the second configuration option and compress and concatenate assets on the fly for each page.
In this case all files in all assets registered on requested page will be concatenated into _N_ groups.
For example, you have one shared asset for all pages. Mark it as monolith:

```php
class AppAsset extends AssetBundle {
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        ...,
    ];
    public $js = [
        ...,
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
        'yii\bootstrap\BootstrapPluginAsset',
    ];

    // Mark asset as monolith
    public $publishOptions = [
        'monolith' => true,
    ];
}
```

Now this asset files and all it dependencies will be combined into one group (one **js** and one **css**).
All others assets on page will be combined into second group.

So as result you will have two **js** and two **css** files for each page. First pair will be common for all pages,
and second - unique for each page.

Simple file concatenation is pretty fast, but if you want to compress files, then this option is not very good,
even though the recombination assets into group happens only when files is changed.

### Combined compilation
If you want concatenate and compress files while maintaining performance then you can use both options.

Compress all assets in console after each changes:

```php
$config = [
    ...
    'controllerMap' => [
        'asset-combiner' => [
            'class' => 'AssetCombiner\AssetCombinerController',
            'assetManager' => [
                'basePath' => '@webroot/assets',
                'baseUrl' => '@web/assets',
            ],
            'filterJs' => [
                'class' => 'AssetCombiner\filters\UglifyJsFilter',
                'sourceMap' => false,
                'compress' => true,
                'mangle' => true,
            ],
            'filterCss' => 'AssetCombiner\filters\UglifyCssFilter',
        ],
    ],
],
```

And then concatenate already compressed files for each page on the fly

```php
$config = [
    ...
    'components' => [
        ...
        'view' => [
            'class' => 'yii\web\View',
            'as assetCombiner' => [
                'class' => 'AssetCombiner\AssetCombinerBehavior',
            ],
        ],
        'assetManager' => [
            'bundles' => require(__DIR__ . '/assets.php'),
        ],
    ],
],
```

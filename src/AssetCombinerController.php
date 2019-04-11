<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 20.03.2016
 * Time: 20:25
 */

namespace AssetCombiner;

use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use yii\web\AssetBundle;

/**
 * Allows you to combine and compress your JavaScript and CSS files.
 *
 * @package AssetCombiner
 */
class AssetCombinerController extends Controller {
    use AssetCombinerTrait {
        init as traitInit;
    }

    /** @var string controller default action ID. */
    public $defaultAction = 'combine';

    /** @var array list of aliases that need to add before bundles (@webroot etc.) */
    public $aliases = [];

    /** @var string directory where all assets stored */
    public $assetsDir = '@app/assets';

    /** @var string assets namespace */
    public $assetsNamespace = 'app\assets';

    /** @var string process dependent assets */
    public $processDependent = false;

    /** @var boolean recursive search for assets */
    public $recursive = true;

    /** @var string[] additional asset bundles to process */
    public $bundles = [];

    /**
     * @var bool precompile monolith bundles
     * This option should be true only if you use AssetCombinerBehavior, otherwise you may get duplicated bundles
     */
    public $precompileMonolith = false;

    /**
     * @inheritdoc
     */
    public function init() {
        foreach ($this->aliases as $alias => $path) {
            Yii::setAlias($alias, Yii::getAlias($path));
        }

        $this->assetsNamespace = trim($this->assetsNamespace, '\\') . '\\';
        $this->traitInit();
    }

    /**
     * Combines and compresses all files into one for each AssetBundle.
     * @param string $configFile Output config file with processed bundles
     * @throws \yii\base\Exception
     * @throws InvalidConfigException
     */
    public function actionCombine($configFile) {
        // Remove existing config
        if (file_exists($configFile)) {
            unlink($configFile);
        }

        $path = Yii::getAlias($this->assetsDir);
        $files = FileHelper::findFiles($path, [
            'recursive' => $this->recursive,
        ]);

        foreach ($files as $file) {
            $namespace = $this->assetsNamespace . ltrim(str_replace('/', '\\', substr(dirname($file), strlen($path))), '\\');
            $this->bundles[] = rtrim($namespace, '\\') . '\\' . pathinfo($file, PATHINFO_FILENAME);
        }

        $this->bundles = array_unique($this->bundles);

        // Add dependency bundles
        if ($this->processDependent) {
            $bundles = [];
            foreach ($this->bundles as $name) {
                $this->collectDependentBundles($name, $bundles, false);
            }
            $this->bundles = array_unique(array_merge($this->bundles, array_keys($bundles)));
        }

        $bundles = [];
        $hasErrors = false;
        foreach ($this->bundles as $name) {
            try {
                $changed = false;
                $bundles[$name] = $this->assembleBundle($name, $changed);
                if ($changed) {
                    $this->stdout("Creating output bundle ");
                    $this->stdout("'{$name}'", Console::FG_YELLOW);
                    $this->stdout(": ");
                    $this->stdout("OK\n", Console::FG_GREEN);
                }
            } catch (\Exception $e) {
                Yii::error($e, __METHOD__);
                $this->stdout("Creating output bundle ");
                $this->stdout("'{$name}'", Console::FG_YELLOW);
                $this->stdout(": ");
                $this->stdout("FAIL\n", Console::FG_RED);
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $this->stdout("Errors occurred during generation. Configuration file was not created.\n", Console::FG_RED);
            return;
        }

        $array = VarDumper::export($bundles);
        $version = date('Y-m-d H:i:s');
        $configFileContent = <<<EOD
<?php
/**
 * This file is generated by the "yii {$this->id}" command.
 * DO NOT MODIFY THIS FILE DIRECTLY.
 * @version {$version}
 */
return {$array};
EOD;
        if (!file_put_contents($configFile, $configFileContent)) {
            throw new Exception("Unable to write output bundle configuration at '{$configFile}'.");
        }
        $this->stdout("Output bundle configuration created at '{$configFile}'.\n", Console::FG_GREEN);
        $this->stdout("Executuion time: ");
        $this->stdout(round(Yii::getLogger()->getElapsedTime(), 3) . " s.\n", Console::FG_CYAN);
    }

    /**
     * @param string $name
     * @param $changed
     * @return array
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function assembleBundle($name, &$changed) {
        $files = [
            'js' => [],
            'css' => [],
            'jsHash' => '',
            'cssHash' => '',
        ];

        $js = $css = [];
        $manager = $this->getAssetManager();
        $bundle = $manager->getBundle($name);
        $monolith = ArrayHelper::getValue($bundle->publishOptions, 'monolith', false) && $this->precompileMonolith;
        if ($monolith) {
            $bundles = [];
            $this->collectDependentBundles($name, $bundles);
            $included = array_keys($bundles);
            $this->collectAllFiles($name, $files, $bundles);
        } else {
            $this->collectAssetFiles($bundle, $files);
        }

        if (!empty($files['externalJs'])) {
            $js = $files['externalJs'];
        }
        if (!empty($files['js']) && !empty($files['jsHash'])) {
            $js[] = $this->writeFiles($files, 'js', $changed);
        }

        if (!empty($files['externalCss'])) {
            $css = $files['externalCss'];
        }
        if (!empty($files['css']) && !empty($files['cssHash'])) {
            $css[] = $this->writeFiles($files, 'css', $changed);
        }

        return [
            'class' => $bundle->className(),
            'basePath' => $this->outputPath,
            'baseUrl' => $this->outputUrl,
            'js' => $js,
            'css' => $css,
            'jsOptions' => $bundle->jsOptions,
            'cssOptions' => $bundle->cssOptions,
            'publishOptions' => array_merge($bundle->publishOptions, ['accProcessed' => true],
                ($monolith && !empty($included) ? ['accIncluded' => $included] : [])),
            'depends' => $monolith ? [] : $bundle->depends,
        ];
    }

    /**
     * @param string $name
     * @param string[] $files
     * @param AssetBundle[] $bundles
     * @throws \yii\base\Exception
     */
    protected function collectAllFiles($name, &$files, &$bundles) {
        if (!isset($bundles[$name])) {
            return;
        }
        $bundle = $bundles[$name];
        if ($bundle) {
            foreach ($bundle->depends as $dep) {
                $this->collectAllFiles($dep, $files, $bundles);
            }
            $this->collectAssetFiles($bundle, $files);
        }
        unset($bundles[$name]);
    }

    /**
     * Collect the named asset bundle and all its dependent asset bundles.
     * @param string $name the class name of the asset bundle (without the leading backslash)
     * @param AssetBundle[] $bundles
     * @param integer|null $position if set, this forces a minimum position for javascript files.
     * This will adjust depending assets javascript file position or fail if requirement can not be met.
     * If this is null, asset bundles position settings will not be changed.
     * If this is false, position options will not taken into account.
     * @throws InvalidConfigException if the asset bundle does not exist or a circular dependency is detected
     * @throws \yii\base\Exception
     */
    public function collectDependentBundles($name, &$bundles, $position = null) {
        if (!isset($bundles[$name])) {
            $am = $this->getAssetManager();
            $bundle = $am->getBundle($name);
            $bundles[$name] = false;
            // register dependencies
            $pos = isset($bundle->jsOptions['position']) ? $bundle->jsOptions['position'] : null;
            foreach ($bundle->depends as $dep) {
                $this->collectDependentBundles($dep, $bundles, $position === false ? false : $pos);
            }
            $bundles[$name] = $bundle;
        } elseif ($bundles[$name] === false) {
            throw new InvalidConfigException("A circular dependency is detected for bundle '$name'.");
        } else {
            $bundle = $bundles[$name];
        }

        if ($position !== null && $position !== false) {
            $pos = isset($bundle->jsOptions['position']) ? $bundle->jsOptions['position'] : null;
            if ($pos === null) {
                $bundle->jsOptions['position'] = $pos = $position;
            } elseif ($pos > $position) {
                throw new InvalidConfigException("An asset bundle that depends on '$name' has a higher javascript file position configured than '$name'.");
            }
            // update position for all dependencies
            foreach ($bundle->depends as $dep) {
                $this->collectDependentBundles($dep, $bundles, $pos);
            }
        }
    }
}
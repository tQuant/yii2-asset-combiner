<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 19.03.2016
 * Time: 16:56
 */

namespace AssetCombiner;

use yii\base\Behavior;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Class AssetCombinerBehavior
 * @package AssetCombiner
 *
 * @property View $owner
 */
class AssetCombinerBehavior extends Behavior {
    use AssetCombinerTrait;

    /** @var AssetBundle[] */
    protected $bundles = [];

    /**
     * @inheritdoc
     */
    public function events() {
        return [
            View::EVENT_END_BODY => 'combineBundles',
        ];
    }

    /**
     * @param Event $event
     */
    public function combineBundles(Event $event) {
        $token = 'Combine bundles for page';
        \Yii::beginProfile($token, __METHOD__);

        $this->bundles = $this->owner->assetBundles;
        $this->setAssetManager($this->owner->getAssetManager());

        // Assemble monolith assets
        foreach ($this->bundles as $name => $bundle) {
            // If this is monolith bundle
            if (ArrayHelper::getValue($bundle->publishOptions, 'monolith', false)) {
                // If it already processed and have no dependency
                if (empty($bundle->depends) && ArrayHelper::getValue($bundle->publishOptions, 'accProcessed', false)) {
                    $this->registerMonolith($bundle);
                }
                // Otherwise process it and assemble
                else {
                    $this->assembleMonolith([$name => $bundle], $bundle->jsOptions, $bundle->cssOptions);
                }
            }
        }
        // Assemble rest of the assets
        $this->assembleMonolith($this->bundles);

        $this->owner->assetBundles = [];

        \Yii::endProfile($token, __METHOD__);
    }

    /**
     * @param AssetBundle $bundle
     */
    public function registerMonolith($bundle) {
        // Remove bundle and its dependencies from list
        unset($this->bundles[$bundle->className()]);

        if (!empty($bundle->publishOptions['accIncluded'])) {
            foreach ($bundle->publishOptions['accIncluded'] as $name) {
                if (isset($this->bundles[$name])) {
                    unset($this->bundles[$name]);
                }
            }
        }

        // Register files
        foreach ($bundle->js as $filename) {
            $this->owner->registerJsFile($bundle->baseUrl . '/' . $filename, $bundle->jsOptions);
        }

        foreach ($bundle->css as $filename) {
            $this->owner->registerCssFile($bundle->baseUrl . '/' . $filename, $bundle->cssOptions);
        }
    }

    /**
     * @param AssetBundle[] $bundles
     * @param array $jsOptions
     * @param array $cssOptions
     */
    public function assembleMonolith($bundles, $jsOptions = [], $cssOptions = []) {
        $files = [
            'js' => [],
            'css' => [],
            'jsHash' => '',
            'cssHash' => '',
        ];

        foreach ($bundles as $name => $bundle) {
            $this->collectFiles($name, $files);
        }

        if (!empty($files['js']) && !empty($files['jsHash'])) {
            $filename = $this->writeFiles($files, 'js');
            $this->owner->registerJsFile($this->outputUrl . '/' . $filename, $jsOptions);
        }

        if (!empty($files['css']) && !empty($files['cssHash'])) {
            $filename = $this->writeFiles($files, 'css');
            $this->owner->registerCssFile($this->outputUrl . '/' . $filename, $cssOptions);
        }
    }

    /**
     * @param $name
     * @param $files
     */
    protected function collectFiles($name, &$files) {
        if (!isset($this->bundles[$name])) {
            return;
        }
        $bundle = $this->bundles[$name];
        if ($bundle) {
            foreach ($bundle->depends as $dep) {
                $this->collectFiles($dep, $files);
            }
            $this->collectAssetFiles($bundle, $files);
        }
        unset($this->bundles[$name]);
    }

    /**
     * Remove bundle and its dependencies from list
     * @param $name
     */
    protected function removeBundle($name) {
        if (!isset($this->bundles[$name])) {
            return;
        }

        $bundle = $this->bundles[$name];
        unset($this->bundles[$name]);

        foreach ($bundle->depends as $depend) {
            $this->removeBundle($depend);
        }

        if (!empty($bundle->publishOptions['accIncluded'])) {
            foreach ($bundle->publishOptions['accIncluded'] as $name) {
                $this->removeBundle($name);
            }
        }
    }
}

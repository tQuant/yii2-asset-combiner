<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 20.03.2016
 * Time: 21:56
 */

namespace AssetCombiner;

use AssetCombiner\filters\BaseFilter;
use AssetCombiner\filters\SimpleCssFilter;
use AssetCombiner\filters\SimpleJsFilter;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\helpers\FileHelper;
use yii\web\AssetBundle;
use yii\web\AssetManager;

/**
 * Class AssetCombinerTrait
 * @package AssetCombiner
 */
trait AssetCombinerTrait {
    /** @var string|array JS filter class name or configuration array */
    public $filterJs;
    /** @var string|array CSS filter class name or configuration array */
    public $filterCss;
    /** @var string Output path for combined files */
    public $outputPath = '@webroot/assets/ac';
    /** @var string Output url for combined files */
    public $outputUrl = '@web/assets/ac';
    /** @var string Use file path to generate first part of the name. If FALSE only name will be used */
    public $useFilePathForHash = true;
    /** @var string File hash function to generate second part of the name */
    public $fileHashFunction = 'filemtime';

    /** @var BaseFilter */
    protected $_filterJs;
    /** @var BaseFilter */
    protected $_filterCss;
    /** @var AssetManager */
    protected $_assetManager = [];

    /**
     * Resolve aliases
     */
    public function init() {
        $this->outputUrl = Yii::getAlias($this->outputUrl);
        $this->outputPath = Yii::getAlias($this->outputPath);
        if (!FileHelper::createDirectory($this->outputPath, 0777)) {
            throw new InvalidConfigException("Failed to create directory: {$this->outputPath}");
        } elseif (!is_writable($this->outputPath)) {
            throw new InvalidConfigException("The directory is not writable by the Web process: {$this->outputPath}");
        } else {
            $this->outputPath = realpath($this->outputPath);
        }
    }

    /**
     * Returns the asset manager instance.
     * @throws \yii\base\Exception on invalid configuration.
     * @return \yii\web\AssetManager asset manager instance.
     */
    public function getAssetManager() {
        if (!is_object($this->_assetManager)) {
            $options = $this->_assetManager;
            if (!isset($options['class'])) {
                $options['class'] = 'yii\\web\\AssetManager';
            }
            if (!isset($options['basePath'])) {
                throw new Exception("Please specify 'basePath' for the 'assetManager' option.");
            }
            if (!isset($options['baseUrl'])) {
                throw new Exception("Please specify 'baseUrl' for the 'assetManager' option.");
            }
            $this->_assetManager = Yii::createObject($options);
        }

        return $this->_assetManager;
    }

    /**
     * Sets asset manager instance or configuration.
     * @param \yii\web\AssetManager|array $assetManager asset manager instance or its array configuration.
     * @throws \yii\base\Exception on invalid argument type.
     */
    public function setAssetManager($assetManager) {
        if (is_scalar($assetManager)) {
            throw new Exception('"' . get_class($this) . '::assetManager" should be either object or array - "' . gettype($assetManager) . '" given.');
        }
        $this->_assetManager = $assetManager;
    }

    /**
     * @return BaseFilter
     */
    public function getFilter($type) {
        if ($type == 'js') {
            return $this->getJsFilter();
        } elseif ($type == 'css') {
            return $this->getCssFilter();
        } else {
            throw new InvalidParamException('Invalid filter type: ' . $type);
        }
    }

    /**
     * @return BaseFilter
     */
    public function getJsFilter() {
        if ($this->_filterJs === null) {
            if (!empty($this->filterJs)) {
                $this->_filterJs = \Yii::createObject($this->filterJs);
            }
            if (!$this->_filterJs) {
                $this->_filterJs = \Yii::createObject(SimpleJsFilter::className());
            }
        }
        return $this->_filterJs;
    }

    /**
     * @return BaseFilter
     */
    public function getCssFilter() {
        if ($this->_filterCss === null) {
            if (!empty($this->filterCss)) {
                $this->_filterCss = \Yii::createObject($this->filterCss);
            }
            if (!$this->_filterCss) {
                $this->_filterCss = \Yii::createObject(SimpleCssFilter::className());
            }
        }
        return $this->_filterCss;
    }

    /**
     * @param AssetBundle $bundle
     * @param array $files
     */
    protected function collectAssetFiles($bundle, &$files) {
        $manager = $this->getAssetManager();
        foreach ($bundle->js as $js) {
            $file = is_array($js) ? array_shift($js) : $js;
            $path = $manager->getAssetPath($bundle, $file);
            if ($path) {
                $files['js'][] = $path;
                $files['jsHash'] .= '|' . call_user_func($this->fileHashFunction, $path);
            } else {
                $files['externalJs'][] = $file;
            }
        }
        foreach ($bundle->css as $css) {
            $file = is_array($css) ? array_shift($css) : $css;
            $path = $manager->getAssetPath($bundle, $file);
            if ($path) {
                $files['css'][] = $path;
                $files['cssHash'] .= '|' . call_user_func($this->fileHashFunction, $path);
            } else {
                $files['externalCss'][] = $file;
            }
        }
    }

    /**
     * @param array $files
     * @param string $type
     * @return string
     * @throws Exception
     */
    protected function writeFiles($files, $type) {
        $names = implode('|', $this->useFilePathForHash ? $files[$type] : array_map('basename', $files[$type]));
        $hash = sprintf('%x', crc32($names . \Yii::getVersion()))
            . '-' . sprintf('%x', crc32($files[$type . 'Hash'] . \Yii::getVersion()));
        $output = $this->outputPath . DIRECTORY_SEPARATOR . $hash . '.' . $type;

        if (!file_exists($output)) {
            $token = 'Write combined files to disk: ' . $output;
            \Yii::beginProfile($token, __METHOD__);

            $filter = $this->getFilter($type);

            if (!$filter->process($files[$type], $output)) {
                throw new Exception("Failed to process files with filter '" . $filter->className() . "'");
            }

            \Yii::endProfile($token, __METHOD__);
        }

        return $hash . '.' . $type;
    }
}
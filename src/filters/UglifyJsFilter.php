<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 20.03.2016
 * Time: 18:04
 */

namespace AssetCombiner\filters;

/**
 * Class UglifyJsFilter
 * @package AssetCombiner
 */
class UglifyJsFilter extends BaseFilter {
    /** @var string Path to UglifyJs */
    public $libPath = 'uglifyjs';

    /** @var bool Add source map generation */
    public $sourceMap = false;

    /** @var bool Add --compress flag */
    public $compress = false;

    /** @var bool Add --mangle flag */
    public $mangle = false;

    /** @var string Add other options to command */
    public $options = false;

    /**
     * @var boolean Skip minification for files with ".min." in the name.
     * If enabled and min file is found - sourceMap settings will be ignored
     */
    public $skipMinFiles = false;

    /**
     * @inheritdoc
     */
    public function process($files, $output) {
        if ($this->skipMinFiles) {
            $content = '';
            $group = [];
            foreach ($files as $i => $file) {
                // Check if file is already minified
                if (strpos($file, '.min.') !== false) {
                    if ($group && !$this->minifyGroup($group, $content)) {
                        return false;
                    }
                    $group = [];
                    $content .= file_get_contents($file) . "\n";
                } else {
                    $group[] = $file;
                }
            }
            // If there is no minified files
            if ($group && !$content) {
                return $this->minify($files, $output, false);
            }
            if ($group && !$this->minifyGroup($group, $content)) {
                return false;
            }
            file_put_contents($output, $content);
            return true;
        } else {
            return $this->minify($files, $output, false);
        }
    }

    /**
     * @param $files
     * @param $content
     * @return bool
     */
    protected function minifyGroup($files, &$content) {
        $tmp = tempnam(\Yii::getAlias('@runtime'), 'ac-');
        if (!$this->minify($files, $tmp, true)) {
            return false;
        }
        $content .= file_get_contents($tmp) . "\n";
        unlink($tmp);
        return true;
    }

    /**
     * @param $files
     * @param $output
     * @param $ignoreSourceMap
     * @return bool
     */
    protected function minify($files, $output, $ignoreSourceMap) {
        foreach ($files as $i => $file) {
            $files[$i] = escapeshellarg($file);
        }

        $cmd = $this->libPath . ' ' . implode(' ', $files) . ' -o ' . escapeshellarg($output);

        if ($this->sourceMap && !$ignoreSourceMap) {
            $prefix = (int)substr_count(\Yii::getAlias('@webroot'), '/');
            $mapFile = escapeshellarg($output . '.map');
            $mapRoot = escapeshellarg(rtrim(\Yii::getAlias('@web'), '/') . '/');
            $mapUrl = escapeshellarg(basename($output) . '.map');
            $cmd .= " -p $prefix --source-map $mapFile --source-map-root $mapRoot --source-map-url $mapUrl";
        }

        if ($this->compress) {
            $cmd .= ' --compress';
        }

        if ($this->mangle) {
            $cmd .= ' --mangle';
        }

        if ($this->options) {
            $cmd .= ' ' . $this->options;
        }

        shell_exec($cmd);

        if (!file_exists($output)) {
            \Yii::error("Failed to process JS files by UglifyJs with command: $cmd", __METHOD__);
            return false;
        }

        return true;
    }
}
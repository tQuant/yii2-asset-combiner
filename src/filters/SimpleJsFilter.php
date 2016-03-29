<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 20.03.2016
 * Time: 20:03
 */

namespace AssetCombiner\filters;

use AssetCombiner\utils\CssHelper;

/**
 * Class SimpleJsFilter
 * @package AssetCombiner
 */
class SimpleJsFilter extends BaseFilter {
    /**
     * @inheritdoc
     */
    public function process($files, $output) {
        $path = \Yii::getAlias('@webroot');
        $content = '';
        foreach ($files as $file) {
            $content .= '// File: ' . str_replace($path, '', $file) . "\n";
            $content .= file_get_contents($file) . "\n";
        }
        return file_put_contents($output, $content);
    }
}
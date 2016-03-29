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
 * Class SimpleCssFilter
 * @package AssetCombiner
 */
class SimpleCssFilter extends BaseFilter {
    /**
     * @inheritdoc
     */
    public function process($files, $output) {
        return CssHelper::combineFiles($files, $output);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 20.03.2016
 * Time: 18:03
 */

namespace AssetCombiner\filters;

use yii\base\Object;

/**
 * Class BaseFilter
 * @package AssetCombiner
 */
abstract class BaseFilter extends Object {
    /**
     * @param string[] $files
     * @param string $output
     * @return boolean
     */
    abstract public function process($files, $output);
}
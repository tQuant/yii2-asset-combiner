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
 * Class UglifyCssFilter
 * @package AssetCombiner
 */
class UglifyCssFilter extends BaseFilter {
    /** @var string Path to UglifyCss */
    public $libPath = 'uglifycss';

    /**
     * @inheritdoc
     */
    public function process($files, $output) {
        $tmpFile = tempnam("/tmp", "yac");
        $content = CssHelper::combineFiles($files, $output, true);
        file_put_contents($tmpFile, $content);

        $cmd = $this->libPath . ' ' . escapeshellarg($tmpFile) . ' > ' . escapeshellarg($output);
        shell_exec($cmd);

        unlink($tmpFile);

        if (!file_exists($output)) {
            \Yii::error("Failed to process CSS files by UglifyCss with command: $cmd", __METHOD__);
            return false;
        }

        return true;
    }
}
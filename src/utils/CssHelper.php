<?php
/**
 * Created by PhpStorm.
 * User: Mikhail
 * Date: 20.03.2016
 * Time: 17:40
 */

namespace AssetCombiner\utils;

use yii\helpers\FileHelper;
use yii\helpers\Url;

/**
 * Class CssHelper
 * @package AssetCombiner
 */
abstract class CssHelper {
    /**
     * Find common root in files
     * @param array $files
     * @return string
     */
    public static function findRoot(array $files) {
        if (!$files) {
            return '';
        }
        $first = array_shift($files);
        $commonParts = explode('/', $first);
        foreach ($files as $file) {
            $stop = false;
            $fileParts = explode('/', $file);
            foreach ($commonParts as $i => $part) {
                if ($stop || count($fileParts) < $i) {
                    unset($commonParts[$i]);
                } elseif ($part !== $fileParts[$i]) {
                    $stop = true;
                    unset($commonParts[$i]);
                }
            }
        }
        return implode('/', $commonParts);
    }

    /**
     * @param string[] $files
     * @param string $output
     * @param boolean $return Return content or save to $output
     * @return string|bool
     */
    public static function combineFiles($files, $output, $return = false) {
        $path = \Yii::getAlias('@webroot', false) ?: '';
        $imports = [];
        $content = '';
        $importsStr = '';
        foreach ($files as $file) {
            $content .= '/* File: ' . str_replace($path, '', $file) . " */\n";
            $content .= self::rewrite($file, $output, $imports) . "\n";
        }
        foreach ($imports as $import) {
            $importsStr .= "@import url($import);\n";
        }
        if ($return) {
            return $importsStr . $content;
        } else {
            return file_put_contents($output, $importsStr . $content) !== false;
        }
    }

    /**
     * @param $from
     * @param $to
     * @param $imports
     * @return string
     */
    public static function rewrite($from, $to, &$imports) {
        $dirFrom = FileHelper::normalizePath(dirname($from));
        $dirTo = FileHelper::normalizePath(dirname($to));
        $content = file_get_contents($from);

        if ($dirFrom == $dirTo) {
            return $content;
        }

        // Относительный путь
        $path = '';
        while (0 !== strpos($dirFrom . '/', $dirTo . '/')) {
            $path .= '../';
            if (false !== ($pos = strrpos($dirTo, '/'))) {
                $dirTo = substr($dirTo, 0, $pos);
            } else {
                $dirTo = '';
                break;
            }
        }
        $path .= ltrim(substr($dirFrom . '/', strlen($dirTo)), '/');

        // Заменяем адреса
        $content = CssUtils::filterUrls($content, function ($matches) use ($path) {
            if (!Url::isRelative($matches['url']) || 0 === strpos($matches['url'], 'data:')
                || isset($matches['url'][0]) && '/' == $matches['url'][0]
            ) {
                // Абсолютный путь или data uri bили путь относительно корня
                return $matches[0];
            }
            // Пусть относительно файла
            $url = FileHelper::normalizePath($path . $matches['url'], '/');

            return str_replace($matches['url'], $url, $matches[0]);
        });

        // Собираем и корректируем импорты
        $newImports = CssUtils::extractImports($content);
        if (!empty($newImports)) {
            foreach ($newImports as $i => $import) {
                if (!Url::isRelative($import) || 0 === strpos($import, 'data:')
                    || isset($import[0]) && '/' == $import[0]
                ) {
                    // Абсолютный путь или data uri bили путь относительно корня
                    continue;
                }
                // Пусть относительно файла
                $newImports[$i] = FileHelper::normalizePath($path . $import, '/');
            }
            $imports = array_merge($imports, $newImports);
        }

        return $content;
    }
}
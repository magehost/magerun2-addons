<?php

namespace MageHost\CheckPerformanceRows;

use Composer\Autoload\ClassLoader;
use Magento\Config\Model\Config;

/**
 * Class ComposerAutoloaderRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class ComposerAutoloaderRow extends AbstractRow
{

    /**
     * @return (string|void)[] 
     */
    public function getRow()
    {
        $title = 'Composer autoloader';
        $recommended = 'Optimized autoloader (composer dump-autoload -o --apcu)';
        $status = $this->formatStatus('STATUS_OK');
        $current = 'Composer\'s autoloader is optimized';
        $classLoader = null;
        foreach (spl_autoload_functions() as $function) {
            if (
                is_array($function)
                && $function[0] instanceof ClassLoader
            ) {
                $classLoader = $function[0];
                break;
            }
        }

        if (empty($classLoader)) {
            $current = 'Could not find Composer AutoLoader';
            $status = $this->formatStatus('STATUS_UNKNOWN');
        }

        if (!array_key_exists(
            Config::class,
            $classLoader->getClassMap()
        )) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $current = 'Composer\'s autoloader is not optimized.';
        }

        return array($title, $status, $current, $recommended);
    }
}

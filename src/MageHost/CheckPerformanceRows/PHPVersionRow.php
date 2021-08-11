<?php

namespace MageHost\CheckPerformanceRows;

use MageHost\CheckPerformanceRows\AbstractRow;

/**
 * Class PHPVersionRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class PHPVersionRow extends AbstractRow
{
    /**
     * @return array 
     */
    public function getRow()
    {
        $phpVersionSplit = explode('-', PHP_VERSION, 2);
        $versionCompare = version_compare(PHP_VERSION, '7.0.0', '>=');
        $showVersion = reset($phpVersionSplit);
        return array(
            'PHP version',
            $versionCompare
                ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus(
                    'STATUS_PROBLEM'
                ),
            $showVersion,
            '>= 7.0.0',
        );
    }
}

<?php

namespace MageHost\CheckPerformanceRows;

/**
 * Class PHPConfigRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class PHPConfigRow extends AbstractRow
{

    /**
     * @return array 
     */
    public function getRow()
    {
        $values = array(
            'opcache.enable_cli'            => 1,
            'opcache.save_comments'         => 1,
            'opcache.consistency_checks'    => 0,
            'opcache.memory_consumption'    => 512,
            'opcache.max_accelerated_files' => 100000,
        );

        $minimalValues = array('opcache.memory_consumption', 'opcache.max_accelerated_files');

        $problems = '';
        $current = '';
        $status = $this->formatStatus('STATUS_OK');

        foreach ($values as $key => $value) {
            $curValue = ini_get($key);
            if (false === $curValue) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            if (!in_array($key, $minimalValues) && ini_get($key) != $value) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            if (in_array($key, $minimalValues) && ini_get($key) < $value) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            $current .= $key . ' = ' . $curValue . "\n";
        }

        $recommended = '';
        foreach ($values as $key => $value) {
            $recommendedRow = $key . ' > ' . $value . "\n";
            if (!in_array($key, $minimalValues)) {
                $recommendedRow = $key . ' = ' . $value . "\n";
            }

            $recommended .= $recommendedRow;
        }

        return array(
            'PHP configuration',
            $status,
            trim($current),
            trim($recommended),
        );
    }
}

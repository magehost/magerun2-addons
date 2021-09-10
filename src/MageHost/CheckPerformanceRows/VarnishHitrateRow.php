<?php

# varnishstat -j -f MAIN.cache_hit -f MAIN.cache_miss

namespace MageHost\CheckPerformanceRows;

use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * Class VarnishHitrateRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class VarnishHitrateRow extends AbstractRow
{
    /**
     * 
     * @return (string|void)[] 
     * @throws LogicException 
     * @throws RuntimeException 
     * @throws ProcessTimedOutException 
     * @throws ProcessSignaledException 
     */
    public function getRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        exec('/bin/bash -li -c "varnishstat -1 -f MAIN.cache_hit -f MAIN.cache_miss" 2>/dev/null', $output, $resultCode);
        if ($resultCode) {
            return array(
                'Varnish Hitrate',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Could not connect to Varnishstat',
                '> 80%',
            );
        }


        $parsedValues = array();
        foreach ($output as $line) {
            if (strpos($line, 'MAIN.cache_hit') !== false || strpos($line, 'MAIN.cache_miss') !== false) {
                $parts = preg_split('/\s+/', $line);
                $parsedValues[$parts[0]] = $parts[1];
            }
        }

        $hitDivision = ($parsedValues['MAIN.cache_hit'] + $parsedValues['MAIN.cache_miss']);

        if ($hitDivision == 0) {
            return array(
                'Average Varnish Hitrate',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Not enough data to crunch (yet)',
                '>= 80%',
            );
        }

        $hitrate = $parsedValues['MAIN.cache_hit'] / $hitDivision;
        if ($hitrate < '0.8') {
            $status = $this->formatStatus('STATUS_PROBLEM');
        }
        return array(
            'Average Varnish Hitrate',
            $status,
            round($hitrate * 100, 0) . '%',
            '>= 80%',
        );
    }
}

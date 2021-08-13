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
        $process = Process::fromShellCommandline('/bin/bash -li -c "varnishstat -j -f MAIN.cache_hit -f MAIN.cache_miss"');
        $process->start();
        $process->wait();
        if (!$process->isSuccessful()) {
            return array(
                'Varnish Hitrate',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Could not connect to Varnishstat',
                '> 80%',
            );
        }

        $varnishStats = json_decode($process->getOutput(), true);
        if (json_last_error()) {
            return array(
                'Varnish Hitrate',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Could not parse Varnishstat',
                '> 80%',
            );
        }

        $counters = $varnishStats['counters'];
        if (!isset($counters) || count($counters) == 0) {
            return array(
                'Varnish Hitrate',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Could not find counters info',
                '> 80%',
            );
        }

        $hitrate = $counters['MAIN.cache_hit']['value'] / ($counters['MAIN.cache_hit']['value'] + $counters['MAIN.cache_miss']['value']);
        if ($hitrate < '0.8') {
            $status = $this->formatStatus('STATUS_PROBLEM');
        }
        return array(
            'Varnish Hitrate',
            $status,
            round($hitrate * 100, 0) . '%',
            '> 80%',
        );
    }
}

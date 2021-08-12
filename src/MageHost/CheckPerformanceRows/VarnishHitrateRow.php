<?php

# varnishstat -j -f MAIN.cache_hit -f MAIN.cache_miss

namespace MageHost\CheckPerformanceRows;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Process\Process;

/**
 * Class VarnishHitrateRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class VarnishHitrateRow extends AbstractRow
{
    public function __construct()
    {
    }

    public function getRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $process = new Process(['varnishstat', '-j', '-f', 'MAIN.cache_hit', '-f', 'MAIN.cache_miss']);
        $process->start();
        $process->wait();
        var_dump($process);
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

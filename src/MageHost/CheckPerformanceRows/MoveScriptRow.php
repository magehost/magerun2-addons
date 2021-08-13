<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;

class MoveScriptRow extends AbstractRow
{
    /**
     * @param ConfigCollection $configCollection 
     * 
     * @return void 
     */
    public function __construct(ConfigCollection $configCollection)
    {
        $this->configCollection = $configCollection;
    }

    /**
     * @return (string|void)[] 
     */
    public function getRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $message = 'Enabled';
        $moveScriptToBottom = $this->getConfigValuesByPath('dev/js/move_script_to_bottom');
        if (!$moveScriptToBottom || !in_array(true, $moveScriptToBottom)) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $message = 'Disabled';
        }

        return array(
            'Move script to bottom',
            $status,
            $message,
            'Enabled'
        );
    }
}

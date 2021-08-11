<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Framework\App\State;

/**
 * Class AppModeRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class AppModeRow extends AbstractRow
{

    protected $appState;

    /**
     * @param State $appState 
     * 
     * @return void 
     */
    public function __construct(State $appState)
    {
        $this->appState = $appState;
    }

    /**
     * @return array
     */
    public function getRow()
    {
        $appMode = $this->appState->getMode();

        return array(
            'Magento mode',
            $appMode == State::MODE_PRODUCTION ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus('STATUS_PROBLEM'),
            $appMode,
            State::MODE_PRODUCTION,
        );
    }
}

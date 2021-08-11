<?php

namespace MageHost\CheckPerformanceRows;

/**
 * Class AbstractRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
abstract class AbstractRow
{

    protected $format;

    protected $configCollection;

    /**
     * @param mixed $format 
     * 
     * @return $this 
     */
    public function setInputFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @param mixed $status 
     * 
     * @return string|void 
     */
    protected function formatStatus($status)
    {
        if ($status === 'STATUS_OK') {
            if ($this->format !== null) {
                return 'ok';
            }

            return '<info>ok</info>';
        }

        if ($status === 'STATUS_PROBLEM') {
            if ($this->format !== null) {
                return 'problem';
            }

            return '<error>problem</error>';
        }

        if ($status === 'STATUS_UNKNOWN') {
            if ($this->format !== null) {
                return 'unknown';
            }

            return '<warning>unknown</warning>';
        }
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    protected function getConfigValuesByPath($path)
    {
        $this->configCollection->clear()->getSelect()->reset(\Zend_Db_Select::WHERE);

        return $this->configCollection->addFieldToFilter('path', $path)->addFieldToSelect('value')
            ->getColumnValues('value');
    }
}

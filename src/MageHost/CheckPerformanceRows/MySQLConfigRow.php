<?php

namespace MageHost\CheckPerformanceRows;

use DomainException;
use Magento\Framework\App\ResourceConnection;

/**
 * Class MySQLConfigRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class MySQLConfigRow extends AbstractRow
{
    protected $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection 
     * 
     * @return void 
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }


    /**
     * @return (string|void)[] 
     * @throws DomainException 
     */
    public function getRow()
    {
        $result = array();
        $connection = $this->resourceConnection->getConnection();
        $ok = true;

        $defaultValues = array(
            'innodb_buffer_pool_size' => 134217728,
            'max_connections' => 150,
            'innodb_thread_concurrency' => 0
        );

        foreach ($defaultValues as $variable => $defaultValue) {
            $currentValue = $connection->fetchRow('SHOW VARIABLES LIKE \'' . $variable . '\'');
            if ($currentValue['Value'] == $defaultValue) {
                $ok = false;
            }
            $result[$currentValue['Variable_name']] = $currentValue['Value'];
        }

        array_walk($result, function (&$value, $key) {
            if ($key == 'innodb_buffer_pool_size') {
                $value = $this->formatBytes($value);
            }
            $value = $key . ' = ' . $value;
        });

        array_walk($defaultValues, function (&$value, $key) {
            if ($key == 'innodb_buffer_pool_size') {
                $value = $this->formatBytes($value);
            }
            $value = $key . ' > ' . $value;
        });

        return array(
            'MySQL configuration',
            $ok ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'),
            implode("\n", $result),
            implode("\n", $defaultValues)
        );
    }
}

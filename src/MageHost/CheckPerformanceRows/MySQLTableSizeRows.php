<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Framework\App\ResourceConnection;

/**
 * Class MySQLTableSizeRows 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class MySQLTableSizeRows extends AbstractRow
{
    protected $resourceConnection;

    protected $tablesMaxRecords = array(
        'search_query' => 50 * 1000,
        'customer_visitor' => 50 * 1000 * 1000,
        'report_event' => 50 * 1000 * 1000,
        'report_viewed_product_index' => 50 * 1000 * 1000,
        'url_rewrite' => 10 * 1000 * 1000,
    );

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
     */
    public function getRow()
    {
        $result = array();
        $connection = $this->resourceConnection->getConnection();
        foreach ($this->tablesMaxRecords as $table => $maxRecords) {
            $tableName = $connection->getTableName($table);
            if (!$connection->isTableExists($tableName)) {
                array_push($result, array(
                    'Rowcount (' . $tableName . ')',
                    $this->formatStatus('STATUS_UNKNOWN'),
                    'Could not find the table',
                    '< ' . $maxRecords
                ));
                continue;
            }
            $rowCount = $connection->fetchRow('SELECT COUNT(*) AS count FROM `' . $tableName . '`;');
            array_push($result, array(
                'MySQL Rowcount (' . $tableName . ')',
                $rowCount['count'] < $maxRecords ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'),
                $rowCount['count'],
                '< ' . $maxRecords
            ));
        }

        return $result;
    }
}

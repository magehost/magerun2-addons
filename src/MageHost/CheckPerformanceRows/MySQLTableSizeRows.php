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
        'catalogsearch_query' => 500 * 1000,
        'log_visitor' => 50 * 1000 * 1000,
        'log_visitor_info' => 50 * 1000 * 1000,
        'report_event' => 50 * 1000 * 1000,
        'report_viewed_product_index' => 50 * 1000 * 1000,
        'core_url_rewrite' => 10 * 1000 * 1000,
    );

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
            $connection->query('SELECT SQL_CALC_FOUND_ROWS * FROM `' . $tableName . '`;');
            $rowCount = $connection->fetchRow('SELECT FOUND_ROWS();');
            array_push($result, array(
                'Rowcount (' . $tableName . ')',
                $rowCount['FOUND_ROWS()'] < $maxRecords ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'),
                $rowCount['FOUND_ROWS()'],
                '< ' . $maxRecords
            ));
        }

        return $result;
    }
}

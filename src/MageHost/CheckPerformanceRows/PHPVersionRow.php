<?php

namespace MageHost\CheckPerformanceRows;

use MageHost\CheckPerformanceRows\AbstractRow;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class PHPVersionRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class PHPVersionRow extends AbstractRow
{
    protected $recommendedVersions = array(
        '2.3.0' => '7.2',
        '2.3.1' => '7.2',
        '2.3.2' => '7.2',
        '2.3.3' => '7.3',
        '2.3.4' => '7.3',
        '2.3.5' => '7.3',
        '2.3.6' => '7.3',
        '2.3.7' => '7.4',
        '2.4.0' => '7.4',
        '2.4.1' => '7.4',
        '2.4.2' => '7.4',
        '2.4.3' => '7.4',
        '2.4.4' => '7.4',
        '2.4.5' => '8.1'
    );

    protected $productMetadata;

    /**
     * @param ProductMetadataInterface $productMetadata 
     * 
     * @return void 
     */
    public function __construct(ProductMetadataInterface $productMetadata)
    {
        $this->productMetadata = $productMetadata;
    }

    /**
     * @return array 
     */
    public function getRow()
    {
        // cut patch version
        $magentoVersion = explode('-', $this->productMetadata->getVersion())[0];

        $versionCompare = version_compare(PHP_VERSION, '7.0.0', '>=');
        $recommendedVersion = '>= 7.0.0';
        if (array_key_exists($magentoVersion, $this->recommendedVersions)) {
            $versionCompare = version_compare(PHP_VERSION, $this->recommendedVersions[$magentoVersion], '>=');
            $recommendedVersion = '>= ' . $this->recommendedVersions[$magentoVersion];
        }


        $phpVersionSplit = explode('-', PHP_VERSION, 2);
        $showVersion = reset($phpVersionSplit);
        return array(
            'PHP version',
            $versionCompare
                ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus(
                    'STATUS_PROBLEM'
                ),
            $showVersion,
            $recommendedVersion,
        );
    }
}

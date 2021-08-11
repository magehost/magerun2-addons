<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class HttpVersionRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class HttpVersionRow extends AbstractRow
{
    protected $request;
    protected $storeManager;

    /**
     * @param RequestInterface $request 
     * @param StoreManagerInterface $storeManager 
     * 
     * @return void 
     */
    public function __construct(RequestInterface $request, StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->storeManager = $storeManager;
    }


    /**
     * @return (string|void)[] 
     * @throws NoSuchEntityException 
     */
    public function getRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $finalVersion = null;
        $serverProtocol = $this->request->getServerValue('SERVER_PROTOCOL');
        if (!empty($serverProtocol)) {
            $versionSplit = explode('/', $serverProtocol);
            $version = $versionSplit[1];
            if (floatval($version) >= 2) {
                $finalVersion = $version;
            }
        }

        if (!$finalVersion) {
            $frontUrl = $this->storeManager->getStore()->getBaseUrl();

            try {
                if (!defined('CURL_HTTP_VERSION_2_0')) {
                    define('CURL_HTTP_VERSION_2_0', 3);
                }

                $curl = curl_init();
                curl_setopt_array(
                    $curl,
                    [
                        CURLOPT_URL            => $frontUrl,
                        CURLOPT_NOBODY         => true,
                        CURLOPT_HEADER         => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_CONNECTTIMEOUT => 2,
                        CURLOPT_TIMEOUT        => 10,
                    ]
                );
                $httpResponse = curl_exec($curl);
                curl_close($curl);
            } catch (Exception $e) {
                $finalVersion = sprintf(
                    "%s: Error fetching '%s': %s",
                    __CLASS__,
                    $frontUrl,
                    $e->getMessage()
                );
                $status = $this->formatStatus('STATUS_UNKNOWN');
            }

            if (!empty($httpResponse)) {
                $responseHeaders = explode("\r\n", $httpResponse);
                foreach ($responseHeaders as $header) {
                    if (preg_match('|^HTTP/([\d\.]+)|', $header, $matches)) {
                        $finalVersion = $matches[1];
                        break;
                    }
                }
                if (empty($finalVersion) || floatval($finalVersion) < 2) {
                    foreach ($responseHeaders as $header) {
                        if (preg_match('|^Upgrade: h([\d\.]+)|', $header, $matches)) {
                            $finalVersion = $matches[1];
                            break;
                        }
                    }
                }
            }

            if ($finalVersion < 2) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            if (!$finalVersion) {
                $status = $this->formatStatus('STATUS_UNKNOWN');
            }
        }

        return array('HTTP version', $status, $finalVersion, '> 2');
    }
}

<?php

/**
 * Class Magoffice_Exaprint_Model_Integration_Prices
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Integration_Prices extends Mage_Core_Model_Abstract
{
    const EXAPRINT_PRICES_LOG_FILE_NAME = 'exaprint_prices_integration';
    const EXAPRINT_PRICES_FILE_NAME = 'exaprint_prices';
    const EXAPRINT_PRICES_LOG_TYPE_INFO = '[INFO]';
    const EXAPRINT_PRICES_LOG_TYPE_ERROR = '[ERROR]';
    const EXAPRINT_PRICES_LOG_TYPE_WARNING = '[WARNING]';
    const EXAPRINT_PRICES_LOG_TYPE_DEBUG = '[DEBUG]';

    protected $_debug = 0;

    protected $_createCnt = 0;
    protected $_updateCnt = 0;
    protected $_deleteCnt = 0;

    protected $_delimiter = ',';
    protected $_enclosure = '"';

    /**
     * Function _getFileName
     *
     * @return string
     */
    protected function _getFileName()
    {
        $fileName = self::EXAPRINT_PRICES_FILE_NAME . '.csv';

        return $this->_getFtpPath() . 'in' . DS . $fileName;
    }

    /**
     * Function _getFtpPath
     *
     * @return string
     * @throws Exception
     */
    protected function _getFtpPath()
    {
        return Afg_Path::getFTPPath();
    }

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_INFO, 'Start of Exaprint prices integration.');

        $this->_exaprintPricesIntegration();

        $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_INFO, 'End of Exaprint prices integration.');
    }

    /**
     * Function _log
     *
     * @param $type
     * @param $message
     */
    protected function _log($type, $message)
    {
        $logFile = $this->_getLogFileName();

        if (!file_exists($logFile)) {
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL);
            chmod($logFile, 0777);
        } else {
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND);
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . self::EXAPRINT_PRICES_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
    }

    /**
     * Function _getLogTimestamp
     *
     * @return string
     */
    protected function _getLogTimestamp()
    {
        return date('Ymd') . ' / ' . date('His') . ' : ';
    }

    /**
     * Function _exaprintPricesIntegration
     *
     * @return bool
     */
    protected function _exaprintPricesIntegration()
    {
        $file = $this->_getFileName();

        //load file
        $handle = fopen($file, 'r');

        if ($handle) {
            $header = fgetcsv($handle, 4096, $this->_delimiter);

            if ($header === false || count($header) < 7) {
                fclose($handle);
                $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_ERROR, "Products file does'nt have columns enough");
            }

            $productsUpdatedCnt = 0;
            while (($data = fgetcsv($handle, 4096, $this->_delimiter, $this->_enclosure)) !== false) {
                $productsPrices = array();

                $price = array();
                $price[3] = $data[3];
                $price[7] = $data[7];
                $price[8] = $data[8];
                $productsPrices[$data[4]][] = $price;

                for ($inc = 0; $inc <= 1000; $inc++) {
                    if (($data = fgetcsv($handle, 4096, $this->_delimiter, $this->_enclosure)) !== false) {
                        $price = array();
                        $price[3] = $data[3];
                        $price[7] = $data[7];
                        $price[8] = $data[8];
                        $productsPrices[$data[4]][] = $price;
                    } else {
                        break;
                    }
                }

                foreach ($productsPrices as $key => $productPrices) {
                    /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
                    $product = Mage::getModel('catalog/product');
                    $sku = 'EXA' . $key;

                    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

                    $productId = $product->getIdBySku($sku);
                    if ($productId) {
                        try {
                            $this->_setPricesData($productId, $productPrices);
                            $productsUpdatedCnt++;

                            unset($product);
                            unset($sku);
                        } catch (Exception $exception) {
                            $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_ERROR,
                                "There is an error during the product prices update: " . $exception->getMessage());
                        }
                    }
                }
                unset($productsPrices);
            }

            fclose($handle);
            $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_INFO, "$productsUpdatedCnt product(s) got prices updated");
            $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_INFO, "$this->_createCnt prices created");
            $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_INFO, "$this->_updateCnt prices updated");
            $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_INFO, "$this->_deleteCnt prices deleted");
        } else {
            $this->_log($this::EXAPRINT_PRICES_LOG_TYPE_ERROR, "Prices file $file not found");
            return false;
        }

        return true;
    }

    /**
     * Function setDebug
     *
     * @param $debug
     */
    public function setDebug($debug)
    {
        $this->_debug = $debug;
    }

    /**
     * Function _isDebug
     *
     * @return int
     */
    protected function _isDebug()
    {
        return $this->_debug;
    }

    /**
     * Function _setPricesData
     *
     * @param $productId
     * @param $prices
     * @return bool
     */
    protected function _setPricesData($productId, $prices)
    {
        /** @var Magpleasure_Tierprices_Model_List $tierPriceList */
        $tierPriceList = Mage::getModel('tierprices/list');

        $pricesData = array();
        foreach ($prices as $price) {
            $data = array();
            $data['entity_id'] = $productId;
            $data['website_id'] = 0;
            $data['cust_group'] = 32000;
            $data['price_qty'] = $price[7];
            $data['price_cost'] = $price[8];
            $data['tier_type'] = Magpleasure_Tierprices_Model_List::TIER_PRICE_TYPE_FIXED;

            $pricesData[$price[7]] = $data;
            $this->_createCnt++;
        }

        $existingPrices = $tierPriceList->getCollection()
                                        ->addFieldToFilter('entity_id', array('eq' => $productId));

        foreach ($existingPrices->getData() as $existingPrice) {
            $qty = intval($existingPrice['price_qty']);

            if (array_key_exists($qty, $pricesData)) {
                $this->_createCnt--;
                $this->_updateCnt++;
            } elseif (!array_key_exists($qty, $pricesData)) {
                $this->_deleteCnt++;
            }
        }

        if (!$this->_isDebug()) {
            $tierPriceList->saveTierPrice($productId, $pricesData);
        }

        return true;
    }
}

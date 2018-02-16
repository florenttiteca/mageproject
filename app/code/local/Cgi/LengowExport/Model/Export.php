<?php

/**
 * Class Cgi_LengowExport_Model_Mytopproduct
 *
 * @category     Cgi
 * @package      Cgi_LengowExport
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowExport_Model_Export extends Mage_Core_Model_Abstract
{
    const LENGOWEXPORT_LOG_FILE_NAME = 'lengow_export';
    const LENGOWEXPORT_LOG_TYPE_INFO = '[INFO]';
    const LENGOWEXPORT_LOG_TYPE_ERROR = '[ERROR]';
    const LENGOWEXPORT_LOG_TYPE_WARNING = '[WARNING]';
    const LENGOWEXPORT_LOG_TYPE_DEBUG = '[DEBUG]';

    protected $_dirDestination = 'marketplace';

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::LENGOWEXPORT_LOG_TYPE_INFO, 'Start of Lengow export.');

        $this->_launchProductsExport();

        $this->_log($this::LENGOWEXPORT_LOG_TYPE_INFO, 'End of Lengow export.');
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
            file_put_contents(
                $logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . $this::LENGOWEXPORT_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
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
     * Function _getHistoryFileName
     *
     * @return string
     */
    protected function _getHistoryFileName()
    {
        $fileName = self::LENGOWEXPORT_LOG_FILE_NAME . '_' . date('Ymd') . '.csv';

        return $this->_getHistoryFilePath() . DS . $fileName;
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
     * Function _getHistoryFilePath
     *
     * @return string
     */
    protected function _getHistoryFilePath()
    {
        $path = $this->_getFtpPath() . 'save' . DS . $this->_dirDestination;

        if (!is_dir($path)) {
            if (!mkdir($path)) {
                $this->_log($this::LENGOWEXPORT_LOG_TYPE_ERROR, "Unable to create ftp/save/$this->_dirDestination");
            } else {
                chmod($path, 777);
            }
        }

        return $path;
    }

    /**
     * Function _launchProductsExport
     *
     * @return int
     */
    protected function _launchProductsExport()
    {
        // clean old log (20 days)
        Mage::helper('lensync/data')->cleanLog();
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        Mage::helper('lensync/data')->log('## Start cron export ##');
        /** @var Lengow_Export_Model_Config $configModel */
        $configModel = Mage::getSingleton('lenexport/config');

        // get store
        $idStore = 1;

        // config store
        $configModel->setStore($idStore);
        Mage::app()->getStore()->setCurrentStore($idStore);

        $saveFile = null;

        // check if store is enable for export
        if (Mage::getStoreConfig('lenexport/global/active_store', Mage::app()->getStore($idStore))) {
            /** @var Cgi_LengowExport_Model_Generate $generate */
            $generate = Mage::getSingleton('lenexport/generate');

            $saveFile = $generate->getFilePath();

            $generate->setCurrentStore($idStore);
            $generate->setOriginalCurrency(Mage::app()->getStore($idStore)->getCurrentCurrencyCode());

            // other params
            $types            = null;
            $exportChild      = null;
            $status           = null;
            $outOfStock       = null;
            $selectedProducts = null;
            $limit            = null;
            $offset           = null;
            $idsProduct       = null;
            $debug            = null;

            Mage::helper('lensync/data')->log(
                'Start cron export in store ' .
                Mage::app()->getStore($idStore)->getName() . '(' . $idStore . ')'
            );

            try {
                if (Mage::getStoreConfig('lenexport/performances/optimizeexport')) {
                    $generate->execCron(
                        $idStore,
                        array(
                            'types'            => $types,
                            'status'           => $status,
                            'exportChild'      => $exportChild,
                            'outOfStock'       => $outOfStock,
                            'selectedProducts' => $selectedProducts,
                            'limit'            => $limit,
                            'offset'           => $offset,
                            'productIds'       => $idsProduct,
                            'debug'            => $debug,
                        )
                    );
                } else {
                    $generate->execCron(
                        $idStore, $types, $status, $exportChild, $outOfStock,
                        $selectedProducts, $limit, $offset, $idsProduct
                    );
                }
            } catch (Exception $e) {
                $this->_log(
                    $this::LENGOWEXPORT_LOG_TYPE_INFO,
                    'Stop cron export - Store ' . Mage::app()->getStore($idStore)->getName() . '(' . $idStore .
                    ') - Error: ' . $e->getMessage()
                );
                Mage::helper('lensync/data')->log(
                    'Stop cron export - Store ' .
                    Mage::app()->getStore($idStore)->getName() . '(' . $idStore .
                    ') - Error: ' . $e->getMessage()
                );
            }
        } else {
            Mage::helper('lensync/data')->log(
                'Stop cron export - Store ' .
                Mage::app()->getStore($idStore)->getName() . '(' . $idStore .
                ') is disabled'
            );
            $this->_log(
                $this::LENGOWEXPORT_LOG_TYPE_INFO,
                'Stop cron export - Store ' . Mage::app()->getStore($idStore)->getName() . '(' . $idStore .
                ') is disabled'
            );
        }
        Mage::helper('lensync/data')->log('## End cron export ##');

        $historyFile = $this->_getHistoryFileName();

        $this->_log($this::LENGOWEXPORT_LOG_TYPE_INFO, "Copy of the file in the history directory $historyFile");
        if(file_exists($historyFile)){
            unlink($historyFile);
        }
        if (!copy($saveFile, $historyFile)) {
            $this->_log($this::LENGOWEXPORT_LOG_TYPE_ERROR, "Failed to copy the history file $historyFile");
        }

        return true;
    }
}

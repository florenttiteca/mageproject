<?php

/**
 * Class Cgi_LengowSync_Model_Import
 *
 * @category     Cgi
 * @package      Cgi_LengowSync
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowSync_Model_Importorders extends Mage_Core_Model_Abstract
{
    const LENGOWSYNC_LOG_DIRECTORY = 'lengow';
    const LENGOWSYNC_LOG_FILE_NAME = 'ws';
    const LENGOWSYNC_LOG_TYPE_INFO = '[INFO]';
    const LENGOWSYNC_LOG_TYPE_ERROR = '[ERROR]';
    const LENGOWSYNC_LOG_TYPE_WARNING = '[WARNING]';
    const LENGOWSYNC_LOG_TYPE_DEBUG = '[DEBUG]';

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, 'Start of Lengow orders import.');

        $this->_launchOrdersImport();

        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, 'End of Lengow orders import.');
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
     * @return string
     */
    protected function _getLogFileName()
    {
        $path = Mage::getBaseDir('log') . DS . $this::LENGOWSYNC_LOG_DIRECTORY;

        if (!is_dir($path)) {
            if (!mkdir($path)) {
                $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR,
                    "Unable to create log/" . $this::LENGOWSYNC_LOG_DIRECTORY);
            } else {
                chmod($path, 777);
            }
        }

        return $path . DS . date('Ymd') . '_' . $this::LENGOWSYNC_LOG_FILE_NAME . '.log';
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
     * Function _launchOrdersImport
     *
     */
    protected function _launchOrdersImport()
    {
        $isModeTest = Mage::getStoreConfig('lensync/test_mode_magoffice/mode_test');

        /** @var Lengow_Sync_Helper_Data $lensyncHelper */
        $lensyncHelper = Mage::helper('lensync/data');

        if (!$isModeTest) {
            // update marketplace.xml file
            $lensyncHelper->updateMarketplaceXML();
        } else {
            $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, "Test mode products import");
        }

        // clean old log (20 days)
        $lensyncHelper->cleanLog();

        // check if import is not already in process
        if (!Mage::getSingleton('lensync/config')->importCanStart()) {
            $lensyncHelper->log('## Error manuel import : import is already started ##');
            $this->_getSession()->addError(Mage::helper('lensync')->__('Import is already started'));
            return false;
        } else {
            $lensyncHelper->log('## Start manual import ##');

            if (Mage::getStoreConfig('lensync/performances/debug')) {
                $lensyncHelper->log('WARNING ! Debug mode is activated');
            }

            $storeCount = 0;
            $storeDisabled = 0;
            $resultNew = 0;
            $resultUpdate = 0;
            $lengowGroups = array();

            $storeCollection = Mage::getResourceModel('core/store_collection')
                                   ->addFieldToFilter('is_active', 1);

            $store = Mage::getModel('core/store')->load(1);

            try {
                if (!$store->getId()) {
                    $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, "No store found");
                    return false;
                }

                $storeCount++;
                $lensyncHelper->log('Start manual import in store ' . $store->getName() . ' (' . $store->getId() . ')');

                $lensyncConfig = Mage::getModel('lensync/config', array('store' => $store));
                // if store is enabled -> stop import
                if (!$lensyncConfig->get('orders/active_store')) {
                    $lensyncHelper->log('Stop manual import - Store ' . $store->getName() . '(' . $store->getId() .
                                        ') is disabled');
                    $storeDisabled++;

                    $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, "store is disabled");
                    return false;
                }
                // get login informations
                $errorImport = false;
                $lentrackerConfig = Mage::getModel('lentracker/config', array('store' => $store));
                $idLengowCustomer = $lentrackerConfig->get('general/login');
                $idLengowGroup = $this->_cleanGroup($lentrackerConfig->get('general/group'));
                $apiTokenLengow = $lentrackerConfig->get('general/api_key');

                if (!$isModeTest) {

                    // if ID Customer or token API are empty -> stop import
                    if (empty($idLengowCustomer) || !is_numeric($idLengowCustomer) || empty($apiTokenLengow)) {
                        $message = 'Please checks your plugin configuration. ID customer or token API is empty';
                        $this->_getSession()->addError(Mage::helper('lensync')->__($message));
                        $lensyncHelper->log($message);
                    }
                    // if ID group is empty -> stop import for current store
                    if (empty($idLengowGroup)) {
                        $message = 'ID group is empty. Please make sure it is saved in your plugin configuration';
                        $lensyncHelper->log('Stop manual import in store ' . $store->getName() . '(' . $store->getId() .
                                            ') : ' . $message);
                        $errorImport = true;
                    }

                }

                // check if group was already imported
                $newIdLengowGroup = false;
                $idGroups = explode(',', $idLengowGroup);
                foreach ($idGroups as $idGroup) {
                    if (is_numeric($idGroup) && !in_array($idGroup, $lengowGroups)) {
                        $lengowGroups[] = $idGroup;
                        $newIdLengowGroup .= !$newIdLengowGroup ? $idGroup : ',' . $idGroup;
                    }
                }
                // start import for current store
                if ((!$errorImport && $newIdLengowGroup) || $isModeTest) {
                    $days = $lensyncConfig->get('orders/period');
                    $days = 8000;
                    $args = array(
                        'dateFrom'   => date('Y-m-d', strtotime(date('Y-m-d') . '-' . $days . 'days')),
                        'dateTo'     => date('Y-m-d'),
                        'config'     => $lensyncConfig,
                        'idCustomer' => $idLengowCustomer,
                        'idGroup'    => $newIdLengowGroup,
                        'apiToken'   => $apiTokenLengow,
                    );
                    /** @var Cgi_LengowSync_Model_Import $import */
                    $import = Mage::getModel('lensync/import', $args);
                    $result = $import->exec();
                    $resultNew += $result['new'];
                    $resultUpdate += $result['update'];
                }
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $lensyncHelper->log('Error ' . $e->getMessage() . '');
            }
        }
        if ($resultNew > 0) {
            $this->_getSession()->addSuccess(Mage::helper('lensync')->__('%d orders are imported', $resultNew));
            $lensyncHelper->log($resultNew . ' orders are imported');
        }
        if ($resultUpdate > 0) {
            $this->_getSession()->addSuccess(Mage::helper('lensync')->__('%d orders are updated', $resultUpdate));
            $lensyncHelper->log($resultUpdate . ' orders are updated');
        }
        if ($resultNew == 0 && $resultUpdate == 0) {
            $this->_getSession()->addSuccess(Mage::helper('lensync')->__('No order available to import'));
            $lensyncHelper->log('No order available to import');
        }
        if ($storeCount == $storeDisabled) {
            $this->_getSession()->addError(
                Mage::helper('lensync')
                    ->__('Please checks your plugin configuration. No store enabled to import')
            );
            $lensyncHelper->log('Please checks your plugin configuration. No store enabled to import');
        }
        $lensyncHelper->log('## End manual import ##');
        Mage::getSingleton('lensync/config')->importSetEnd();
        return true;
    }

    /**
     * Retrieve adminhtml session model object
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Function _cleanGroup
     *
     * @param $data
     * @return string
     */
    private function _cleanGroup($data)
    {
        return trim(str_replace(array("\r\n", ';', '-', '|', ' '), ',', $data), ',');
    }

    /**
     * Function addLog
     *
     * @param $type
     * @param $message
     */
    public function addLog($type, $message)
    {
        $this->_log($type, $message);
    }
}

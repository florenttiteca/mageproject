<?php

/**
 * Class Magoffice_Exaprint_Model_Connect_Adapter_Soap
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Connect_Adapter_Soap implements Magoffice_Exaprint_Model_Connect_Interface
{
    /** @var SoapClient _client */
    private $_client;
    private $_username = '';
    private $_password = '';

    private $_token;

    const EXAPRINT_SOAP_CONF_URL_WEBSERVICE = 'exaprint/adapter_soap/url_webservice';
    const EXAPRINT_SOAP_CONF_USERNAME = 'exaprint/adapter_soap/username';
    const EXAPRINT_SOAP_CONF_PASSWORD = 'exaprint/adapter_soap/password';
    const EXAPRINT_SOAP_CONF_PROXY_HOST = 'exaprint/adapter_soap/proxy_host';
    const EXAPRINT_SOAP_CONF_PROXY_PORT = 'exaprint/adapter_soap/proxy_port';
    const EXAPRINT_FTP_HOST = 'exaprint/ftp/host';
    const EXAPRINT_FTP_PORT = 'exaprint/ftp/port';
    const EXAPRINT_FTP_USERNAME = 'exaprint/ftp/username';
    const EXAPRINT_FTP_PASSWORD = 'exaprint/ftp/password';

    /**
     * Magoffice_Exaprint_Model_Connect_Adapter_Soap constructor.
     */
    public function __construct()
    {
        $client    = Mage::getStoreConfig(self::EXAPRINT_SOAP_CONF_URL_WEBSERVICE);
        $proxyHost = Mage::getStoreConfig(self::EXAPRINT_SOAP_CONF_PROXY_HOST);
        $proxyPort = Mage::getStoreConfig(self::EXAPRINT_SOAP_CONF_PROXY_PORT);

        $this->initConnection($client, $proxyHost, $proxyPort);
    }

    /**
     * Function initConnection
     *
     * @param      $client
     * @param null $proxyHost
     * @param null $proxyPort
     */
    private function initConnection($client, $proxyHost = null, $proxyPort = null)
    {
        try {
            if ($proxyHost && $proxyPort) {

                $this->_client = new SoapClient(
                    $client, array(
                        'connection_timeout' => 5,
                        'proxy_host'         => $proxyHost,
                        'proxy_port'         => $proxyPort
                    )
                );
            } else {
                $this->_client = new SoapClient($client);
            }

            $this->_username = Mage::getStoreConfig(self::EXAPRINT_SOAP_CONF_USERNAME);
            $this->_password = Mage::getStoreConfig(self::EXAPRINT_SOAP_CONF_PASSWORD);

            $credentials = array(
                'username' => $this->_username,
                'password' => $this->_password
            );

            $this->_token = $this->wsGetToken($credentials);

        } catch (SoapFault $fault) {
            Mage::log($fault->getMessage());
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    /**
     * Function wsGetToken
     *
     * @param array $credentials
     *
     * @return mixed
     */
    public function wsGetToken($credentials = array())
    {
        try {
            $result = $this->_client->__soapCall('getToken', array($credentials));

            /** @var Magoffice_Exaprint_Helper_Data $exaprintHelper */
            $exaprintHelper = Mage::helper('magoffice_exaprint');
            $tokenResult    = $exaprintHelper->transformWsResult($result);

            if (array_key_exists('code', $tokenResult)) {
                if ($tokenResult['code'] == 0) {
                    $error = 'Internal error';
                } elseif ($tokenResult['code'] == -1) {
                    $error = 'Access denied';
                } else {
                    $error = 'Unknown error code (' . $tokenResult['code'] . ')';
                }

                Mage::log('Error obtaining Exaprint token : ' . $error);

                return null;
            }

            return $tokenResult['token'];
        } catch (SoapFault $fault) {
            Mage::log($fault->getMessage());

            return null;
        }

    }

    /**
     * getToken
     *
     * @return mixed
     */
    public function getToken()
    {
        return $this->_token;
    }

    /**
     * Function wsCreateOrder
     *
     * @param array $orderParameter
     *
     * @return mixed
     */
    public function wsCreateOrder($orderParameter = array())
    {
        try {
            $response = $this->_client->__soapCall('createOrder', array($this->_token, $orderParameter));

            /** @var Magoffice_Exaprint_Helper_Data $exaprintHelper */
            $exaprintHelper = Mage::helper('magoffice_exaprint');
            $wsResponse     = $exaprintHelper->transformWsResult($response);

            return $wsResponse;
        } catch (SoapFault $fault) {
            return false;
        }
    }

    /**
     * Function wsCancelOrder
     *
     * @param array $orderToCancel
     *
     * @return mixed
     */
    public function wsCancelOrder($orderToCancel = array())
    {
        try {
            $response = $this->_client->__soapCall('cancelOrder', array($this->_token, $orderToCancel));

            return $response;
        } catch (SoapFault $fault) {
            return false;
        }
    }

    /**
     * wsOrderStatus
     *
     * @param array $orderList
     *
     * @return array|Exception|SoapFault
     */
    public function wsOrderStatus($orderList = array())
    {
        try {
            $response = $this->_client->__soapCall('orderStatus', array($this->_token, $orderList));

            /** @var Magoffice_Exaprint_Helper_Data $exaprintHelper */
            $exaprintHelper = Mage::helper('magoffice_exaprint');
            $wsResponse     = $exaprintHelper->transformWsResult($response);

            return $wsResponse;
        } catch (SoapFault $fault) {
            return $fault;
        }
    }

    /**
     * wsOrderTracking
     *
     * @param array $orderList
     *
     * @return array|Exception|SoapFault
     */
    public function wsOrderTracking($orderList = array())
    {
        try {
            $response = $this->_client->__soapCall('getTracking', array($this->_token, $orderList));

            /** @var Magoffice_Exaprint_Helper_Data $exaprintHelper */
            $exaprintHelper = Mage::helper('magoffice_exaprint');
            $wsResponse     = $exaprintHelper->transformWsResult($response);

            return $wsResponse;
        } catch (SoapFault $fault) {
            return $fault;
        }
    }

    /**
     * Function wsTransferFile
     *
     * @param       $file
     * @param       $remoteFile
     * @param array $orderList
     *
     * @return bool
     */
    public function wsTransferFile($file, $remoteFile, $orderList = array())
    {
        $ftpHost     = Mage::getStoreConfig(self::EXAPRINT_FTP_HOST);
        $ftpPort     = Mage::getStoreConfig(self::EXAPRINT_FTP_PORT);
        $ftpUsername = Mage::getStoreConfig(self::EXAPRINT_FTP_USERNAME);
        $ftpPassword = Mage::getStoreConfig(self::EXAPRINT_FTP_PASSWORD);

        try {
            $ftpConnection    = ftp_connect($ftpHost, $ftpPort);
            $connectionResult = ftp_login($ftpConnection, $ftpUsername, $ftpPassword);

            if (!$connectionResult) {
                Mage::log('Invalid username or password to connect to Exaprint FTP');
            }

            ftp_pasv($ftpConnection, true);

            if (!ftp_put($ftpConnection, $remoteFile, $file, FTP_ASCII)) {
                Mage::log('Attempt to send file to Exaprint FTP failed');
            }

            ftp_close($ftpConnection);

            $response = $this->_client->__soapCall('orderFichierTransfere', array($this->_token, $orderList));

            return $response;
        } catch (SoapFault $fault) {
            return false;
        }
    }

    /**
     * orderFichierTransfere
     *
     * @param array $orderList
     *
     * @return array|Exception|SoapFault
     */
    public function orderFichierTransfere($orderList = array())
    {
        try {
            $response = $this->_client->__soapCall('orderFichierTransfere', array($this->_token, $orderList));

            /** @var Magoffice_Exaprint_Helper_Data $exaprintHelper */
            $exaprintHelper = Mage::helper('magoffice_exaprint');
            $wsResponse     = $exaprintHelper->transformWsResult($response);

            return $wsResponse;
        } catch (SoapFault $fault) {
            return $fault;
        }
    }
}

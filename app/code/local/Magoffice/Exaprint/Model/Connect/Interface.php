<?php

/**
 * Interface Magoffice_Exaprint_Model_Connect_Interface
 */
interface Magoffice_Exaprint_Model_Connect_Interface
{
    /**
     * Function getToken
     *
     * @return mixed
     */
    public function wsGetToken();

    /**
     * Function wsCancelOrder
     *
     * @param array $orderParameter
     * @return mixed
     */
    public function wsCancelOrder($orderParameter = array());

    /**
     * Function wsCreateOrder
     *
     * @param array $orderParameter
     * @return mixed
     */
    public function wsCreateOrder($orderParameter = array());

    /**
     * Function wsOrderStatus
     *
     * @param array $orderParameter
     * @return mixed
     */
    public function wsOrderStatus($orderParameter = array());

    /**
     * Function wsTransferFile
     *
     * @param $file
     * @param $remoteFile
     * @param array $orderList
     * @return mixed
     */
    public function wsTransferFile($file, $remoteFile, $orderList = array());
}

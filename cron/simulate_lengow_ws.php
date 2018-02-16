<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

$code = $_GET["httpcode"];
$filename = $_GET["filename"];

if ($filename) {
    if ($filename == 'new_orders.xml') {
        /** @var Cgi_LengowSync_Model_Importorders $logger */
        $logger = Mage::getSingleton('cgi_lensync/importorders');
    } else {
        /** @var Cgi_LengowSync_Model_Update_Status $logger */
        $logger = Mage::getSingleton('cgi_lensync/update_status');
    }
} else {
    Mage::log($logger::LENGOWSYNC_LOG_TYPE_ERROR, "missing filename parameter");
    return false;
}

if (!$code || $code != 200) {
    $logger->addLog($logger::LENGOWSYNC_LOG_TYPE_ERROR, "Web Service unreachable (simulate)");
    return false;
}

if ($filename) {
    $ftpPath = Afg_Path::getFTPPath();

    switch ($filename) {
        case 'new_orders.xml':
            echo $ftpPath . 'in/lengow/tests/' . $filename;
            $logger->addLog($logger::LENGOWSYNC_LOG_TYPE_INFO,
                "Call simulate Web Service OK");
            break;
        case 'accept.xml':
        case 'acceptOrder.xml':
        case 'refuseOrder.xml':
            if (!isset($_GET["order_mp_number"])) {
                $logger->addLog($logger::LENGOWSYNC_LOG_TYPE_ERROR,
                    "missing order_id parameter");
                break;
            }

            $orderMpId = $_GET["order_mp_number"];

            $response = file_get_contents($ftpPath . 'in/lengow/tests/' . $filename);
            $response = str_replace('order_mp_number', $orderMpId, $response);

            echo $response;
            $logger->addLog($logger::LENGOWSYNC_LOG_TYPE_INFO,
                "Call simulate Web Service OK");
            break;
        default:
            $logger->addLog($logger::LENGOWSYNC_LOG_TYPE_ERROR, "filename parameter unknown");
            break;
    }
} else {
    $logger->addLog($logger::LENGOWSYNC_LOG_TYPE_ERROR, "missing filename parameter");
}
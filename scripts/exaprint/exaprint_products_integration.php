<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Exaprint_Model_Integration_Products $flux */
$flux = Mage::getModel('magoffice_exaprint/integration_products');

if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    $flux->setDebug($_GET['debug']);
}

$flux->call();
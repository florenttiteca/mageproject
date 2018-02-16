<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Exaprint_Model_Integration_Zipcodes $flux */
$flux = Mage::getModel('magoffice_exaprint/integration_zipcodes');

if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    $flux->setDebug($_GET['debug']);
}

if (isset($_GET['ID_Pays'])) {
    $flux->setCountryId($_GET['ID_Pays']);
}

$flux->call();
<?php
set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Exaprint_Model_Export_Orderstatus $flux */
$flux = Mage::getModel('magoffice_exaprint/export_orderstatus');
$flux->call();
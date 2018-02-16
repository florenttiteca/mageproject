<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Sales_Model_Cron_Retourdropshipment $flux */
$flux = Mage::getModel('magoffice_sales/cron_retourdropshipment');
$flux->call();
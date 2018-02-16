<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Multiwishlist_Model_Mytopproduct $flux */
$flux = Mage::getModel('magoffice_multiwishlist/mytopproduct');
$flux->call();
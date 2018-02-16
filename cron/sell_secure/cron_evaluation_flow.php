<?php

set_time_limit(0);
ini_set('memory_limit', '1000M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Sellsecure_Model_Evaluation_Flow $flux */
$flux = Mage::getModel('magoffice_sellsecure/evaluation_flow');
$flux->call();
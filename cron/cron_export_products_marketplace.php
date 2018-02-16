<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Cgi_LengowExport_Model_Export $flux */
$flux = Mage::getModel('cgi_lenexport/export');
$flux->call();
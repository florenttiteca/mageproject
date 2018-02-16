<?php

set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Cgi_DonneesUfp_Model_Links_Integration $flux */
$flux = Mage::getModel('cgi_donneesUfp/links_integration');

if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    $flux->setDebug($_GET['debug']);
}

$flux->call();
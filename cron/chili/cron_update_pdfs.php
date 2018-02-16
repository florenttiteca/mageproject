<?php
set_time_limit(0);
ini_set('memory_limit', '1500M');
require_once '../../app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);

/** @var Magoffice_Chili_Model_Pdf $pdfModel */
$pdfModel = Mage::getModel('web2print/pdf');
$pdfModel->updatePdfs();
<?php
$installer = $this;
$installer->startSetup();

/** @var C2bi_Email_Model_Email_Template $orderEmail */
$orderEmail = Mage::getModel('core/email_template');
$orderEmail->loadByCode('Facture');

$newOrderEmail = Mage::getModel('core/email_template');
$newOrderEmail->setData($orderEmail->getData());
$newOrderEmail->setData('template_id', null);
$newOrderEmail->setData('template_code', 'Facture Marketplace');

$newOrderEmail->save();

$installer->endSetup();

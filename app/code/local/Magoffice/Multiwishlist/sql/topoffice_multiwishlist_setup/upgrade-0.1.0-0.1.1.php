<?php

$installer = $this;
$installer->startSetup();

$content = '<p>Pour pouvoir ajouter un article à une liste d\'achats, vous devez être connecter au site.</p>';

$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist add not connected');
$block->setIdentifier('wishlist-add-not-connected');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent($content);
$block->save();

$installer->endSetup();

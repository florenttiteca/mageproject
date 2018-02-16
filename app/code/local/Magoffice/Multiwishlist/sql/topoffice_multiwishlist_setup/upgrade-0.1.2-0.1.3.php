<?php

$installer = $this;
$installer->startSetup();

$block = Mage::getModel('cms/block')->load('wishlist-add-not-connected', 'identifier');

if ($block) {
    $content = '<p>Pour pouvoir ajouter un article à une liste d\'achats, vous devez être connecté au site.</p>';

    $block->setContent($content);
    $block->save();
}

$installer->endSetup();
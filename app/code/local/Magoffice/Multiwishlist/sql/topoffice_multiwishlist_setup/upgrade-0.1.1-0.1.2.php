<?php

$installer = $this;
$installer->startSetup();

// Création block CMS wishlist-listing-haut
$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist listing haut');
$block->setIdentifier('wishlist-listing-haut');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent('');
$block->save();

// Création block CMS wishlist-listing-bas
$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist listing bas');
$block->setIdentifier('wishlist-listing-bas');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent('');
$block->save();

// Création block CMS wishlist-explain-bubble-MY_TOP_PRODUCT
$content = 'Nous vous proposons les produits que vous commandez le plus';

$block = Mage::getModel('cms/block');
$block->setTitle('wishlist explain bubble MY_TOP_PRODUCT');
$block->setIdentifier('wishlist-explain-bubble-MY_TOP_PRODUCT');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent($content);
$block->save();

// Création block CMS wishlist-detail-haut
$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist detail haut');
$block->setIdentifier('wishlist-detail-haut');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent('');
$block->save();

// Création block CMS wishlist-detail-bas
$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist detail bas');
$block->setIdentifier('wishlist-detail-bas');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent('');
$block->save();

// Création block CMS wishlist-detail-haut-top-productt
$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist detail haut  MY_TOP_PRODUCT');
$block->setIdentifier('wishlist-detail-haut-top-product');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent('');
$block->save();

// Création block CMS wishlist-detail-bas-top-product
$block = Mage::getModel('cms/block');
$block->setTitle('Wishlist detail bas  MY_TOP_PRODUCT');
$block->setIdentifier('wishlist-detail-bas-top-product');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent('');
$block->save();

$installer->endSetup();

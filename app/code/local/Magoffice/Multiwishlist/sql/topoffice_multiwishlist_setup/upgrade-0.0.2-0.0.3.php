<?php
$installer = $this;
$installer->startSetup();

$installer->run("
    ALTER TABLE {$this->getTable('am_list_item')}
    ADD `prices_when_adding` decimal(10,2) NOT NULL AFTER `descr`,
    ADD `origin` varchar(255) NOT NULL AFTER `prices_when_adding`,
    ADD `created_at` date NOT NULL AFTER `origin`,
    ADD `updated_at` date NULL AFTER `created_at`;
");

$installer->endSetup();
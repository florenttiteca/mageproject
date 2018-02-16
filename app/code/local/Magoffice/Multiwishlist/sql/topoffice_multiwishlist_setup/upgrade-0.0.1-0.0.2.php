<?php
$installer = $this;
$installer->startSetup();

$installer->run("
    ALTER TABLE {$this->getTable('am_list')}
    ADD `updated_at` date NULL AFTER `created_at`,
    ADD `last_used_at` date NULL AFTER `updated_at`,
    ADD	`list_type` varchar(255) NOT NULL AFTER `last_used_at`,
    ADD	`technic_title` varchar(255) NOT NULL AFTER `list_type`;
");

$installer->endSetup();
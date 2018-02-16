<?php
$installer = $this;
$installer->startSetup();

$installer->run("
	ALTER TABLE `{$this->getTable('lengow_status_update')}`
	MODIFY COLUMN `mp_id` text;
");

$installer->endSetup();

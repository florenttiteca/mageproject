<?php
$installer = $this;
$installer->startSetup();
$installer->run("
	CREATE TABLE IF NOT EXISTS `{$this->getTable('lengow_status_update')}` (
		`entity_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`order_id` int(11) NOT NULL,
		`mp_id` int(11) NOT NULL,
		`increment_id` int(11) DEFAULT NULL,
		`created_at` datetime DEFAULT NULL,
		`updated_at` datetime DEFAULT NULL,
		`ws` varchar(32) DEFAULT NULL,
		`state` varchar(32) DEFAULT NULL,
		`error_msg` varchar(255) NOT NULL,
		`url` varchar(255) NOT NULL,
        PRIMARY KEY (`entity_id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
");
$installer->endSetup();

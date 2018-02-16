<?php

$installer = $this;
$installer->startSetup();

$installer->run(
    "INSERT INTO `{$installer->getTable('productshippingrules/rule')}` (`in_attribute`, `carrier_code`, `method_code`)
    VALUES('9', 'express', '*');"
);

$installer->endSetup();
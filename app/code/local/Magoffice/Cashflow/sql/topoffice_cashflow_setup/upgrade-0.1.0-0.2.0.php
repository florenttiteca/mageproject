<?php

$installer = $this;

$installer->startSetup();

$installer->run("
    DROP TABLE IF EXISTS V_DQM_I_WEB;
    CREATE TABLE V_DQM_I_WEB (
      `id` int(10) NOT NULL auto_increment,
      `id_dat` varchar(20) NOT NULL,
      `id_mag` smallint(5) NOT NULL,
      `type_piece` varchar(20) NOT NULL,
      `i_nb_piece` decimal(20,2) NULL,
      `i_qte_art` decimal(20,2) NULL,
      `i_ca_ttc` decimal(20,2) NULL,
      `i_ca_ht` decimal(20,2) NULL,
      `i_mrg` decimal(20,2) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();
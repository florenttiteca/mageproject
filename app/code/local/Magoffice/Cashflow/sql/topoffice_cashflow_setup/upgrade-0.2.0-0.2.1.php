<?php

$installer = $this;

$installer->startSetup();

$installer->run("
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `id_dat` `ID_DAT` varchar(20) NOT NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `id_mag` `ID_MAG` smallint(5) NOT NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `type_piece` `TYPE_PIECE` varchar(20) NOT NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `i_nb_piece` `I_NB_PIECE` decimal(20,2) NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `i_qte_art` `I_QTE_ART` decimal(20,2) NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `i_ca_ttc` `I_CA_TTC` decimal(20,2) NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `i_ca_ht` `I_CA_HT` decimal(20,2) NULL;
    ALTER TABLE V_DQM_I_WEB CHANGE COLUMN `i_mrg` `I_MRG` decimal(20,2) NULL;

");

$installer->endSetup();
<?php
/**
 * Created by PhpStorm.
 * User: cgi
 * Date: 31/03/16
 * Time: 16:48
 */

$this->startSetup();

// First email
$email = Mage::getModel('core/email_template');
$email->setTemplateCode('Commande express avant 10h');
$email->setTemplateText('La commande vient d\'être passé pour un livraison express pour 14h.<br />
<br />
Client : {{var customer.getPrefix()}} {{var customer.getFirstname()}} {{var customer.lastname()}}<br />
Email du client : {{var customer.getEmail()}}
Telephone du client : {{var customer.getMobileTelephone()}} {{var customer.getTelephone()}}

Montant de la commande: {{var order.total_paid}} EUR TTC.');

$email->setTemplateType(2);
$email->setTemplateSubject('Nouvelle commande express #{{var order.increment_id}} de {{var order.total_paid}} EUR TTC');
$email->save();

// Second email
$email = Mage::getModel('core/email_template');
$email->setTemplateCode('Commande express après 10h');
$email->setTemplateText('La commande vient d\'être passé pour un livraison express pour 14h.<br />
<br />
Client : {{var customer.getPrefix()}} {{var customer.getFirstname()}} {{var customer.lastname()}}<br />
Email du client : {{var customer.getEmail()}}
Telephone du client : {{var customer.getMobileTelephone()}} {{var customer.getTelephone()}}

Montant de la commande: {{var order.total_paid}} EUR TTC.');
$email->setTemplateType(2);
$email->setTemplateSubject('Nouvelle commande express #{{var order.increment_id}} de {{var order.total_paid}} EUR TTC');
$email->save();
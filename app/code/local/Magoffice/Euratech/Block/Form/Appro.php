<?php

/**
 * Class Magoffice_Euratech_Block_Form_Appro
 *
 * @category     Magoffice
 * @package      Magoffice_Euratech
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Euratech_Block_Form_Appro extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payment/form/appro.phtml');
    }
}

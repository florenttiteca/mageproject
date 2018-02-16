<?php

/**
 * Class Magoffice_Sellsecure_Model_Source_Executionmode
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Source_Executionmode
{
    /**
     * Provide available options as a value/label array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label' => 'OFF : Sell-Secure n’est pas actif'),
            array('value' => 1, 'label' => 'TEST : Mode test'),
            array('value' => 2, 'label' => 'PRODUCTION : Mode production')
        );
    }
}

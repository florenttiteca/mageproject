<?php

/**
 * Class Magoffice_Login_LoginController
 *
 * @category     Magoffice
 * @package      Magoffice_Login
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2015
 * @version      v1.0
 */
class Magoffice_Login_LoginController extends Mage_Core_Controller_Front_Action
{
    /**
     * Function ajaxLoginMissigStock
     *
     */
    public function ajaxLoginMissingStockAction()
    {
        $params = Mage::app()->getRequest()->getParams();

        $user = $params['user'];
        $password = $params['key'];
        $query = $params['query'];

        $url = Mage::getBaseUrl() . 'scripts' . DS . 'loginMissingStock.php';

        $fields = array(
            'user'     => urlencode($user),
            'key'      => urlencode($password),
            'xmlQuery' => urlencode($query)
        );

        $fieldsString = null;

        foreach ($fields as $key => $value) {
            $fieldsString .= $key . '=' . $value . '&';
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, count($fields));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fieldsString);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        curl_close($curl);

        echo $result;
    }
}

<?php

/**
 * Class Magoffice_Chili_Helper_Data
 *
 * @category   Magoffice
 * @package    Magoffice_Chili
 * @author     Florent TITECA <florent.titeca@cgi.com>
 * @copyright  CGI 2017
 * @version    1.0
 */
class Magoffice_Chili_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CHILI_ORIENTATION_PORTRAIT_LABEL = 'portrait';
    const CHILI_ORIENTATION_PAYSAGE_LABEL = 'paysage';
    const CHILI_RECTO_LABEL = 'recto';
    const CHILI_VERSO_LABEL = 'verso';

    /**
     * Function getPreviewBackgroundUrl
     *
     * @param string $face
     *
     * @return string
     */
    public function getPreviewBackgroundUrl($face = 'recto')
    {
        $supplierProductId = Mage::app()->getRequest()->getParam('exa_ref');

        $resourceModel = Mage::getResourceModel('catalog/product');
        $largeur       = $resourceModel->getAttributeRawValue($supplierProductId, 'largeur_gnx');
        $hauteur       = $resourceModel->getAttributeRawValue($supplierProductId, 'hauteur_gnx');

        $chiliOrientation = Mage::getSingleton('customer/session')->getData('chili_orientation');

        if ($chiliOrientation == Magoffice_Chili_Helper_Data::CHILI_ORIENTATION_PORTRAIT_LABEL) {
            $orientation = 'vertical';
        } else {
            $orientation = 'horizontal';
        }

        $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) .
            'frontend/mag_office/default/images/cartes-personnalisabes/validation/mockup_desk_' . $largeur . 'x' .
            $hauteur . '_' . $face . '_' . $orientation . '.jpg';

        return $url;
    }

    /**
     * Function getPreviewClass
     *
     * @return string
     */
    public function getPreviewClass()
    {
        $class             = 'container_img';
        $supplierProductId = Mage::app()->getRequest()->getParam('exa_ref');

        $resourceModel = Mage::getResourceModel('catalog/product');
        $largeur       = $resourceModel->getAttributeRawValue($supplierProductId, 'largeur_gnx');
        $hauteur       = $resourceModel->getAttributeRawValue($supplierProductId, 'hauteur_gnx');

        if ($largeur == "8,5" && $hauteur == "5,4") {
            $class .= '_1';
        } elseif ($largeur == "10" && $hauteur == "21") {
            $class .= '_3';
        }

        $chiliOrientation = Mage::getSingleton('customer/session')->getData('chili_orientation');
        if ($chiliOrientation == 'portrait') {
            $class .= '_vertical';
        }

        return $class;
    }

    /**
     * Function isRectoVerso
     *
     * @return bool
     */
    public function isRectoVerso()
    {
        $chiliParams = Mage::getSingleton('customer/session')->getData('chili_params');
        foreach ($chiliParams as $chiliParam) {
            if (count($chiliParam)) {
                foreach ($chiliParam as $value) {
                    if ($value == 'RECTO_VERSO') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * convertCmykToRgb
     *
     * @param $cyan
     * @param $magenta
     * @param $yellow
     * @param $key
     *
     * @return array
     */
    function convertCmykToRgb($cyan, $magenta, $yellow, $key)
    {
        $result = array();

        $red   = 255 - round(2.55 * ($cyan + $key));
        $green = 255 - round(2.55 * ($magenta + $key));
        $blue  = 255 - round(2.55 * ($yellow + $key));

        if ($red < 0) {
            $red = 0;
        }
        if ($green < 0) {
            $green = 0;
        }
        if ($blue < 0) {
            $blue = 0;
        }

        $result['red']  = $red;
        $result['red']  = $green;
        $result['blue'] = $blue;

        return $result;
    }
}

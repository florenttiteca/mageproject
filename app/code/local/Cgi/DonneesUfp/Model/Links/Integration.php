<?php

/**
 * Class Cgi_DonneesUfp_Model_Links_Integration
 *
 * @category     Cgi
 * @package      Cgi_DonneesUfp
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2017
 * @version      v1.0
 */
class Cgi_DonneesUfp_Model_Links_Integration extends Mage_Core_Model_Abstract
{
    const DONNEES_UFP_LOG_FILE_NAME = 'UFP_model_links_integration';
    const DONNEES_UFP_FILE_NAME = 'UFP_model_links';
    const DONNEES_UFP_LOG_TYPE_INFO = '[INFO]';
    const DONNEES_UFP_LOG_TYPE_ERROR = '[ERROR]';
    const DONNEES_UFP_LOG_TYPE_WARNING = '[WARNING]';
    const DONNEES_UFP_LOG_TYPE_DEBUG = '[DEBUG]';

    protected $_debug = 0;

    protected $_delimiter = ';';

    protected $_dirDestination = 'ufp';

    /**
     * Function _getFileName
     *
     * @return string
     */
    protected function _getFileName()
    {
        $fileName = self::DONNEES_UFP_FILE_NAME . '.csv';

        return $this->_getFtpPath() . 'in' . DS . $fileName;
    }

    /**
     * Function _getHistoryFileName
     *
     * @return string
     */
    protected function _getHistoryFileName()
    {
        $fileName = self::DONNEES_UFP_FILE_NAME . '_' . date('Ymd') . '.csv';

        return $this->_getHistoryFilePath() . DS . $fileName;
    }

    /**
     * Function _getFtpPath
     *
     * @return string
     * @throws Exception
     */
    protected function _getFtpPath()
    {
        return Afg_Path::getFTPPath();
    }

    /**
     * Function _getHistoryFilePath
     *
     * @return string
     */
    protected function _getHistoryFilePath()
    {
        $path = $this->_getFtpPath() . 'save' . DS . $this->_dirDestination;

        if (!is_dir($path)) {
            if (!mkdir($path)) {
                $this->_log($this::DONNEES_UFP_LOG_TYPE_ERROR, "Unable to create ftp/save/$this->_dirDestination");
            } else {
                chmod($path, 777);
            }
        }

        return $path;
    }

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, 'Start of ufp model links integration.');

        $this->_ufpLinksIntegration();

        $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, 'End of ufp model links integration.');
    }

    /**
     * Function _log
     *
     * @param $type
     * @param $message
     */
    protected function _log($type, $message)
    {
        $logFile = $this->_getLogFileName();

        if (!file_exists($logFile)) {
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL);
            chmod($logFile, 0777);
        } else {
            file_put_contents(
                $logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . self::DONNEES_UFP_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
    }

    /**
     * Function _getLogTimestamp
     *
     * @return string
     */
    protected function _getLogTimestamp()
    {
        return date('Ymd') . ' / ' . date('His') . ' : ';
    }

    /**
     * Function _ufpLinksIntegration
     *
     */
    protected function _ufpLinksIntegration()
    {
        $file = $this->_getFileName();

        //load file
        $handle = fopen($file, 'r');

        if ($handle) {
            $header   = fgetcsv($handle, 4096, $this->_delimiter);
            $refLinks = array();

            if ($header === false || count($header) < 5) {
                fclose($handle);
                $this->_log($this::DONNEES_UFP_LOG_TYPE_ERROR, "File does'nt have columns enough");
            } else {
                while (($data = fgetcsv($handle, 4096, $this->_delimiter)) !== false) {
                    $ref       = $data[0];
                    $serieName = str_replace("-", " ", str_replace("/", " ", $data[2]));
                    $modelName = str_replace("-", " ", str_replace("/", " ", $data[3]));

                    $serieModel       = trim($serieName) . "/" . trim($modelName);
                    $refLinks[$ref][] = $serieModel;
                }

                fclose($handle);
            }

            $entityTypeId     = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
            $attributeSetName = 'Cartouches & Toners';

            $attributeSetId = Mage::getModel('eav/entity_attribute_set')
                ->getCollection()
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', $attributeSetName)
                ->getFirstItem()
                ->getAttributeSetId();

            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "Start of step one");
            $this->_stepOne($refLinks, $attributeSetId);
            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "End of step one");

            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "Start of step two");
            $this->_stepTwo();
            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "End of step two");

            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "Start of step three");
            $this->_stepThree($attributeSetId);
            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "End of step three");

            $historyFile = $this->_getHistoryFileName();

            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "Copy of the file in the history directory : $historyFile");

            if (!copy($file, $historyFile)) {
                $this->_log($this::DONNEES_UFP_LOG_TYPE_ERROR, "Failed to copy the history file");

                return false;
            }
        } else {
            $this->_log($this::DONNEES_UFP_LOG_TYPE_ERROR, "File $file not found");

            return false;
        }

        return true;
    }

    /**
     * Function setDebug
     *
     * @param $debug
     */
    public function setDebug($debug)
    {
        $this->_debug = $debug;
    }

    /**
     * Function _isDebug
     *
     * @return int
     */
    protected function _isDebug()
    {
        return $this->_debug;
    }

    /**
     * _stepOne : step 1 : found ref - erase links - add new and create if unexist link
     *
     * @param $refLinks
     * @param $attributeSetId
     *
     * @return bool
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    protected function _stepOne($refLinks, $attributeSetId)
    {
        $referencesFound = 0;

        $attributeName = 'modele_imprimante_compatible';

        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attributeModel */
        $attributeModel = Mage::getModel('catalog/resource_eav_attribute');
        $attribute      = $attributeModel->loadByCode('catalog_product', $attributeName);
        $attributeId    = $attribute->getAttributeId();

        foreach ($refLinks as $reference => $links) {
            // search products which are in "Cartouches & Toners" attribute set
            /** @var  $products Mage_Catalog_Model_Resource_Product_Collection */
            $products = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('reference_chez_fournisseur', array('eq' => $reference))
                ->addAttributeToFilter('attribute_set_id', array('eq' => $attributeSetId));

            if (!$products->count()) {
                continue;
            }

            /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
            $product = $products->getFirstItem();

            if (!$product) {
                continue;
            }

            // if reference found
            $referencesFound++;

            $compatibleLinks = array();

            foreach ($links as $link) {
                $link     = strtoupper($link);
                $optionId = $attribute->getSource()->getOptionId($link);

                if (!$optionId) {
                    $createOption                                = array();
                    $createOption['attribute_id']                = $attributeId;
                    $createOption['value']['any_option_name'][0] = $link;

                    //if unknown link, add it to attribute options
                    if (!$this->_isDebug()) {
                        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
                        $setup->addAttributeOption($createOption);

                        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
                        $optionId  = $attribute->getSource()->getOptionId($link);

                        $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "$link option added");
                    }
                }

                if ($optionId) {
                    // add each link
                    $compatibleLinks[] = $optionId;
                } else {
                    $this->_log($this::DONNEES_UFP_LOG_TYPE_ERROR, "The option $link was not saved correctly");
                }
            }

            $compatibleLinks = array_unique($compatibleLinks);
            $compatibilities = implode(',', $compatibleLinks);

            if (!$this->_isDebug()) {
                $product->setData('modele_imprimante_compatible', $compatibilities);
                $product->save();
            }
            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "Product with id " . $product->getId() . " updated");
        }

        $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, "$referencesFound references updated");

        unset($refLinks);
        unset($compatibleLinks);
        unset($product);

        return true;
    }

    /**
     * Function _stepTwo
     * step 2 : same traitment with white/ Top Office brands
     *
     * @return bool
     * @throws Exception
     */
    protected function _stepTwo()
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $whiteBrandProducts */
        $whiteBrandProducts = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('consommable_compatible_ref', array('notnull' => true));

        $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, $whiteBrandProducts->count() . " white products to update");

        /** @var C2bi_Catalogc2bi_Model_Productc2bi $whiteBrandProduct */
        foreach ($whiteBrandProducts as $whiteBrandProduct) {
            $matchingSkusArray = array();
            $matchingSkus      = str_replace(' ', '', $whiteBrandProduct->getData('consommable_compatible_ref'));

            if (!$matchingSkus) {
                continue;
            }

            /** @var C2bi_Catalogc2bi_Model_Productc2bi $productModel */
            $productModel = Mage::getModel('catalog/product');

            // explode skus
            if (strpos($matchingSkus, ',')) {
                $matchingSkusArray = explode(',', $matchingSkus);
            } elseif (strpos($matchingSkus, ';')) {
                $matchingSkusArray = explode(';', $matchingSkus);
            } else {
                $matchingSkusArray[] = $matchingSkus;
            }

            if (!is_array($matchingSkusArray)) {
                $this->_log(
                    $this::DONNEES_UFP_LOG_TYPE_ERROR,
                    "Impossible to split skus in attribute 'consommable_compatible_ref' by ',' or ';' " .
                    "for the product with id " . $whiteBrandProduct->getId()
                );
                continue;
            }

            $matchingRefs   = array();
            $compatibleRefs = null;

            foreach ($matchingSkusArray as $matchingSku) {
                // load product with "consommable_compatible_ref" as sku
                /** @var C2bi_Catalogc2bi_Model_Productc2bi $compatibleModelPdt */
                $compatibleModelPdt = $productModel->loadByAttribute('sku', $matchingSku);

                // if product is not found
                if (!$compatibleModelPdt) {
                    $this->_log(
                        $this::DONNEES_UFP_LOG_TYPE_ERROR,
                        "The product " . $whiteBrandProduct->getId() .
                        " indicates a sku as reference $matchingSku which does'nt exist"
                    );
                    continue;
                }

                $printerRefs = $compatibleModelPdt->getData('modele_imprimante_compatible');
                $printerRefs = explode(',', $printerRefs);

                $matchingRefs = array_merge($matchingRefs, $printerRefs);
            }

            if (!$this->_isDebug()) {
                $compatibleRefs = implode(',', $matchingRefs);

                $whiteBrandProduct->setData('modele_imprimante_compatible', $compatibleRefs);
                $whiteBrandProduct->save();
            }

            $this->_log($this::DONNEES_UFP_LOG_TYPE_INFO, $whiteBrandProduct->getSku() . " references updated");
        }

        unset($compatibleModelPdt);
        unset($whiteBrandProducts);

        return true;
    }

    /**
     * Function _stepThree
     * step 3 : update long description with CMS template
     *
     * @param $attributeSetId
     *
     * @return bool
     * @throws Exception
     */
    protected function _stepThree($attributeSetId)
    {
        /** @var  $cartridgeTonerPdts Mage_Catalog_Model_Resource_Product_Collection */
        $cartridgeTonerPdts = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('attribute_set_id', array('eq' => $attributeSetId));

        /** @var Mage_Cms_Model_Block $cmsTemplate */
        $cmsTemplate = Mage::getModel('cms/block')->load('template_description_consommable');

        if (!$cmsTemplate) {
            $this->_log(
                $this::DONNEES_UFP_LOG_TYPE_ERROR,
                "The cms block with identifier 'template_description_consommable' does'nt exist"
            );

            return false;
        }

        $template = $cmsTemplate->getContent();

        if (strpos($template, '${TYPE}') === false || strpos($template, '${MARQUE}') === false
            || strpos($template, '${COMPATIBLES}') === false
        ) {
            $this->_log(
                $this::DONNEES_UFP_LOG_TYPE_ERROR,
                "The cms block does'nt contain one of the following parameters " .
                "\${TYPE}, \${MARQUE} or \${COMPATIBLES}"
            );

            return false;
        }

        $updatedDescription = 0;

        /** @var C2bi_Catalogc2bi_Model_Productc2bi $cartridgeTonerPdt */
        foreach ($cartridgeTonerPdts as $cartridgeTonerPdt) {
            $type               = $cartridgeTonerPdt->getAttributeText('consommable_type');
            $marque             = $cartridgeTonerPdt->getAttributeText('marque_imprimante_compatible');
            $compatiblePrinters = $cartridgeTonerPdt->getAttributeText('modele_imprimante_compatible');

            if (count($compatiblePrinters)) {
                if (count($compatiblePrinters) == 1) {
                    $compatiblePrinters = str_replace('/', ' ', $compatiblePrinters);
                } else {
                    $compatiblePrinters = str_replace('/', ' ', implode(', ', $compatiblePrinters));
                }

                $longDescription
                    = str_replace(
                    '${TYPE}', $type,
                    str_replace(
                        '${MARQUE}', $marque,
                        str_replace('${COMPATIBLES}', $compatiblePrinters, $template)
                    )
                );

                if (!$this->_isDebug()) {
                    $cartridgeTonerPdt->setDescription($longDescription);
                    $cartridgeTonerPdt->save();
                }

                $updatedDescription++;
            } else {
                $this->_log(
                    $this::DONNEES_UFP_LOG_TYPE_ERROR,
                    "The product with ID " . $cartridgeTonerPdt->getId() . " has no compatibilities"
                );
                continue;
            }
        }

        $this->_log(
            $this::DONNEES_UFP_LOG_TYPE_INFO,
            "Long description of " . $updatedDescription . " products updated"
        );

        return true;
    }
}

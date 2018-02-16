<?php

require_once(Mage::getModuleDir('controllers', 'Chili_Web2print') . DS . 'AjaxController.php');

/**
 * Class Magoffice_Chili_AjaxController
 *
 * @category     Magoffice
 * @package      Magoffice_Chili
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2017
 * @version      v1.0
 */
class Magoffice_Chili_AjaxController extends Chili_Web2print_AjaxController
{

    /**
     * Load new Docuement on modele change
     */
    public function loadModelAction()
    {
        try {
            $paramLoadType = 'product';
            $paramId = $this->getRequest()->getParam('id');

            $editor = Mage::getSingleton('web2print/editor');

            $productId = $paramId;
            $editor->setMode('product');

            /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
            $product = Mage::getModel('catalog/product')->load($productId);

            /** @var Chili_Web2print_Helper_Data $helper */
            $helper = Mage::helper('web2print/data');

            if (!$helper->isProductAllowed($product)) {
                Mage::throwException($this->__('You are not allowed to edit this product'));
            }

            Mage::register('product', $product);
            Mage::register('current_product', $product);

            $editor->setProduct($product);
            $editor->requestChiliEditorData($paramLoadType);

            $productDocument = $product->getData('web2print_document_id');

            $data = Array();
            $data['ChiliEditorUrl'] = $editor->getChiliEditorUrl();
            $data['idProduct'] = $paramId;
            $data['documentName'] = explode("|", $productDocument)[0];
            $data['documentId'] = $editor->getData('_chili_document_id');
            $data['orientation'] = $product->getData('chili_orientation');

            $this->getResponse()->setBody(Zend_Json::encode($data));

        } catch (Exception $e) {
            //Show error if configured
            if (Mage::getStoreConfig('web2print/connection/redirect_exception')) {
                Mage::getSingleton('core/session')->addError($e->getMessage());
            }

            //redirect to page
            $this->getResponse()->setBody(Zend_Json::encode('You are not allowed to edit this product'));
        }
    }

    /**
     * Load list models slider with color parameter
     *
     * @throws Mage_Core_Exception
     */
    public function loadModelsListAction()
    {
        $paramColor = $this->getRequest()->getParam('color');
        $categoryId = $this->getRequest()->getParam('category');
        $category = Mage::getModel('catalog/category')->load($categoryId);

        $sliderProductCollection = $category->getProductCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', 4);

        $productList = Array();
        foreach ($sliderProductCollection as $product) {
            $galleryImage = $product->load('media_gallery')->getMediaGalleryImages();
            $image = $galleryImage->getItemByColumnValue('label', $paramColor);
            if (!$image) {
                continue;
            } else {
                $image = $image->getUrl();
            }

            $productList[$product->getId()] = Array(
                'id'       => $product->getId(),
                'imageUrl' => $image,
                'name'     => $product->getName()
            );
        }

        $this->getResponse()->setBody(Zend_Json::encode($productList));
    }

    /**
     * Load preview document by id
     *
     * @throws Mage_Core_Exception
     */
    public function loadPreviewDocumentAction()
    {
        $paramId = $this->getRequest()->getParam('id');

        $previewDocument = Array();
        $previewDocument[] = Mage::getModel('web2print/api')
            ->getResourceImageUrl($paramId,
                Mage::helper('catalog/image')->getImageConversionProfile('product'), 'Documents',
                '1');
        $previewDocument[] = Mage::getModel('web2print/api')
            ->getResourceImageUrl($paramId,
                Mage::helper('catalog/image')->getImageConversionProfile('product'), 'Documents',
                '2');

        $this->getResponse()->setBody(Zend_Json::encode($previewDocument));
    }

    /**
     * Create an epxort PDF task in chili.
     */
    public function exportValidationPdfAction()
    {
        // get params:
        $documentId = $this->getRequest()->getParam('id');
        // generate pdf
        try {
            $exportProfile = Mage::helper('web2print')->getPdfExportProfile("frontend", 0);
            $pdfTaskId = Mage::getModel('web2print/api')->createPdfTaskForAjax($documentId, $exportProfile);
        } catch (Exception $e) {
            $pdfTaskId = false;
        }

        // set output:
        if ($pdfTaskId) {
            $ajaxResult = array(
                'status'    => 'success',
                'content'   => '<br /><p class="a-center">' .
                    $this->__('The PDF is requested. Please wait while we prepare your download.') . '</p>',
                'pdfTaskId' => $pdfTaskId
            );
        } else {
            $ajaxResult = array(
                'status'    => 'error',
                'content'   => '<br /><p class="a-center error">' .
                    $this->__('The PDF could not be generated. Please try again or contact us.') . '</p>',
                'pdfTaskId' => 0
            );
        }

        $this->getResponse()->setBody(Zend_Json::encode($ajaxResult));
    }

    /**
     * Function resourceItemAddAction
     *
     */
    public function resourceItemAddAction()
    {
        $chiliId = $this->getRequest()->getParam('chiliId');

        if ($chiliId) {
            $result = Mage::getModel('web2print/api')->getResourceItemAdd(current($_FILES), $chiliId);
            $this->getResponse()->setBody(Zend_Json::encode($result->ResourceItemAddResult));
        }
    }

    /**
     * Function updateFormAction
     *
     */
    public function updateFormAction()
    {
        $documentId = $this->getRequest()->getParam('id');

        $editor = Mage::getSingleton('web2print/editor');
        $editor->setChiliDocumentId($documentId);

        /** @var Magoffice_Chili_Block_Editor $block */
        $block = $this->getLayout()->createBlock('magoffice_chili/editor');

        $rectoFormHtml = $block->getRectoFramesHtml();
        $rectoFormHtml .= $block->getRectoFormHtml();

        $versoFormHtml = $block->getVersoFramesHtml();
        $versoFormHtml .= $block->getVersoFormHtml();
        $this->getResponse()->setBody(
            Zend_Json::encode(
                array(
                    'rectoForm' => $rectoFormHtml,
                    'versoForm' => $versoFormHtml
                )
            )
        );
    }

    /**
     * Function updateColorAction
     *
     */
    public function updateColorAction()
    {
        $documentId = $this->getRequest()->getParam('id');

        $editor = Mage::getSingleton('web2print/editor');
        $editor->setChiliDocumentId($documentId);

        /** @var Magoffice_Chili_Block_Editor $block */
        $block = $this->getLayout()->createBlock('magoffice_chili/editor');

        $colorFormHtml = $block->getColorFormHtml();

        $this->getResponse()->setBody(
            Zend_Json::encode($colorFormHtml)
        );
    }

    /**
     * inputTagToSessionAction
     *
     */
    public function inputTagToSessionAction()
    {
        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getModel('customer/session');

        $tag = Mage::app()->getRequest()->getParam('tag');
        $value = Mage::app()->getRequest()->getParam('value');

        $session->setData($tag, $value);
    }
}

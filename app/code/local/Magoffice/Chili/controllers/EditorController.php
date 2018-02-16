<?php

require_once(Mage::getModuleDir('controllers', 'Chili_Web2print') . DS . 'EditorController.php');

/**
 * Class Magoffice_Chili_EditorController
 *
 * @category     Magoffice
 * @package      Magoffice_Chili
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2017
 * @version      v1.0
 */
class Magoffice_Chili_EditorController extends Chili_Web2print_EditorController
{

    /**
     * Load the CHILI Editor and decide the mode (create/edit concept or quote item)
     */
    public function loadAction()
    {
        try {
            $paramLoadType = $this->getRequest()->getParam('type');
            $paramId = $this->getRequest()->getParam('id');
            $sessionCustomerId = Mage::getSingleton('customer/session')->getCustomer()->getId();

            $editor = Mage::getSingleton('web2print/editor');

            switch ($paramLoadType) {
                // Default create mode
                case 'product':
                    Mage::getSingleton('customer/session')->setData('chili_params', array());
                    Mage::getSingleton('customer/session')->setData('exa_params', array());
                    $productId = $paramId;
                    $editor->setMode('product');
                    break;

                // Edit quote item mode (product in shoppingcart)
                case 'quoteitem':
                    $quoteItem = Mage::getModel('sales/quote_item')->load($paramId);
                    $quoteCustomerId = Mage::getModel('sales/quote')->load($quoteItem->getQuoteId())->getCustomerId();

                    if ($sessionCustomerId !== $quoteCustomerId) {
                        Mage::throwException($this->__('Unable to load quote item'));
                    }

                    $editor->setMode('quoteitem');
                    $editor->setQuoteItem($quoteItem);
                    $editor->setChiliDocumentId(Mage::helper('web2print')
                        ->getDocumentIdByQuoteItemId($quoteItem->getId()));
                    $productId = $quoteItem->getProductId();
                    break;

                // Edit concept item
                case 'concept':
                    // @todo add check for customer specific concepts
                    $concept = Mage::getModel('web2print/concept')->load($paramId);

                    if ($sessionCustomerId !== $concept->getCustomerId()) {
                        Mage::throwException($this->__('Unable to load concept'));
                    }

                    $editor->setMode('concept');
                    $editor->setConcept($concept);
                    $editor->setChiliDocumentId($concept->getChiliId());
                    $productId = $concept->getProductId();
                    break;

                case 'edit':
                    $chiliId = $this->getRequest()->getParam('chili_id');
                    $editor->setChiliDocumentId($chiliId);

                    if ($this->getRequest()->getParam('update')) {
                        $quoteItemId = $this->getRequest()->getParam('update');
                        Mage::getSingleton('customer/session')->setData('update_item_id', $quoteItemId);
                    }

                    $productId = $paramId;
                    $editor->setMode('product');
                    break;
            }

            /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
            $product = Mage::getModel('catalog/product')->load($productId);

            Mage::getSingleton('customer/session')->setData('chili_template_ref', $paramId);
            Mage::getSingleton('customer/session')->setData('chili_template_price', $product->getPrice());

            $categoryIds = $product->getCategoryIds();
            $categoryId = end($categoryIds);

            if ($categoryId) {
                $category = Mage::getModel('catalog/category')->load($categoryId);

                $chiliParams = Mage::getSingleton('customer/session')->getData('chili_params');

                if (!$chiliParams) {
                    Mage::getSingleton('customer/session')->setData('chili_params', array());
                    $chiliParams = Mage::getSingleton('customer/session')->getData('chili_params');
                }

                $categoryChiliParams = json_decode(
                    $category->getCriteresInjectes(),
                    true
                );

                foreach ($categoryChiliParams as $key => $categoryChiliParam) {
                    if (count($categoryChiliParam)) {
                        $values = array();
                        foreach ($categoryChiliParam as $value) {
                            $values[] = $value;
                        }

                        $chiliParams[$key] = $values;
                    }
                }

                Mage::getSingleton('customer/session')->setData('chili_params', $chiliParams);
            }

            if (!Mage::helper('web2print/data')->isProductAllowed($product)) {
                Mage::throwException($this->__('You are not allowed to edit this product'));
            }

            Mage::register('product', $product);
            Mage::register('current_product', $product);

            $editor->setProduct($product);
            $editor->requestChiliEditorData($paramLoadType);
            
            $this->loadLayout();

            /** @var C2bi_Pagec2bi_Block_Html_Breadcrumbs $breadcrumbs */
            $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');

            $currentCatId = $this->getRequest()->getParam('category');
            $currentCat = Mage::getModel('catalog/category')->load($currentCatId);
            $catLevel3 = $currentCat->getParentCategory();
            $catLevel2 = $catLevel3->getParentCategory();
            $catLevel1 = $catLevel2->getParentCategory();
            $pageType = 'Configurateur';

            $breadcrumbs->addCrumb(
                'catLevel1',
                array(
                    'label' => $this->__($catLevel1->getName()),
                    'title' => $this->__($catLevel1->getName()),
                    'link' => $catLevel1->getUrl(),
                    'first' => true
                ));

            $breadcrumbs->addCrumb(
                'catLevel2',
                array(
                    'label' => $this->__($catLevel2->getName()),
                    'title' => $this->__($catLevel2->getName()),
                    'link' => $catLevel2->getUrl()
                ));

            $breadcrumbs->addCrumb(
                'catLevel3',
                array(
                    'label' => $this->__($catLevel3->getName()),
                    'title' => $this->__($catLevel3->getName()),
                    'link' => $catLevel3->getUrl()
                ));

            $breadcrumbs->addCrumb(
                'currentCat',
                array(
                    'label' => $this->__($currentCat->getName()),
                    'title' => $this->__($currentCat->getName()),
                    'link' => $currentCat->getUrl()
                ));

            $breadcrumbs->addCrumb(
                $pageType,
                array(
                    'label' => $this->__($pageType),
                    'title' => $this->__($pageType),
                    'last' => true
                ));

            $breadcrumbs->removeCrumb('product');

            $this->renderLayout();

        } catch (Exception $e) {
            //Show error if configured
            if (Mage::getStoreConfig('web2print/connection/redirect_exception')) {
                Mage::getSingleton('core/session')->addError($e->getMessage());
            }

            //redirect to page
            $this->_redirect(Mage::getStoreConfig('web2print/connection/exception_cms'));
        }
    }

}

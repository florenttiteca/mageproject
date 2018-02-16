<?php

require_once 'Amasty/List/controllers/ListController.php';

/**
 * Class Magoffice_Multiwishlist_ListController
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2015
 * @version      v1.0
 */
class Magoffice_Multiwishlist_ListController extends Amasty_List_ListController
{
    /**
     * Function preDispatch
     *
     */
    public function preDispatch()
    {
        Mage_Core_Controller_Front_Action::preDispatch();

        if (!Mage::getStoreConfigFlag('amlist/general/active')) {
            $this->norouteAction();
            return;
        }

        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getSingleton('customer/session');

        if (!$session->isLoggedIn()) {
            if (!$session->getBeforeAmlistUrl()) {
                $session->setBeforeAmlistUrl($this->_getRefererUrl());
            }
            if ($this->getRequest()->isPost()) {
                //store custom options
                $productId = $this->getRequest()->getParam('product');
                if ($productId) {
                    $params[$productId] = $this->getRequest()->getParams();
                    $session->setAmlistParams($params);
                }
            }
        }

        if ($session->getCustomer()) {
            $this->_customerId = $session->getCustomer()->getId();
        }
    }

    /**
     * Function addAjaxBySkuAction
     *
     */
    public function addListAjaxBySkuAction()
    {
        $params = $this->getRequest()->getParams();

        $productId = Mage::getModel('catalog/product')->getIdBySku($params['sku']);
        $params['product'] = $productId;
        $params['id'] = $productId;

        if ($productId) {
            $this->_forward('addlistajax', null, null, $params);
        }
    }

    /**
     * Function addlistajaxAction
     *
     */
    public function addlistajaxAction()
    {
        $productId = $this->getRequest()->getParam('product');
        $qty = $this->getRequest()->getParam('qty');
        $origin = $this->getRequest()->getParam('origin');

        $this->loadLayout();

        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');

        $customerSession->setBeforeAmlistUrl($this->_getRefererUrl());
        $customerSession->setBeforeAmlistProduct($productId);

        if ($customerSession->isLoggedIn()) {
            $blockAdd = $this->getLayout()->getBlock('addtomultiwishlist');

            if ($productId) {
                $blockAdd->setProduct($productId);
            }

            if ($qty) {
                $blockAdd->setQty($qty);
            }
            if ($origin) {
                $blockAdd->setOrigin($origin);
            }

            /** @var Amasty_List_Model_Mysql4_List_Collection $lists */
            $lists = Mage::getResourceModel('amlist/list_collection')
                         ->addCustomerFilter(Mage::getSingleton('customer/session')->getCustomerId())
                         ->addFieldToFilter('list_type',
                             array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_MANUAL))
                         ->setOrder('title', 'ASC')
                         ->load();

            if ($lists->count()) {
                $blockAdd->setLists($lists);
            }
        }

        $this->renderLayout();
    }

    /**
     * Function addcarttolistajaxAction
     *
     */
    public function addcarttolistajaxAction()
    {
        $this->loadLayout();

        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');

        $customerSession->setBeforeAmlistUrl($this->_getRefererUrl());
        $customerSession->setBeforeAmlistProduct(1);

        if ($customerSession->isLoggedIn()) {
            $blockAdd = $this->getLayout()->getBlock('addcarttomultiwishlist');

            /** @var Amasty_List_Model_Mysql4_List_Collection $lists */
            $lists = Mage::getResourceModel('amlist/list_collection')
                         ->addCustomerFilter(Mage::getSingleton('customer/session')->getCustomerId())
                         ->addFieldToFilter('list_type',
                             array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_MANUAL))
                         ->setOrder('title', 'ASC')
                         ->load();

            if ($lists->count()) {
                $blockAdd->setLists($lists);
            }
        }

        $this->renderLayout();
    }

    /**
     * Function askdeletelistAction
     *
     */
    public function askdeletelistAction()
    {
        $listTitle = $this->getRequest()->getParam('name');
        $listId = $this->getRequest()->getParam('id');

        $this->loadLayout();

        $blockAskDelete = $this->getLayout()->getBlock('askdeletelist');

        if ($blockAskDelete) {
            $blockAskDelete->setListName(base64_decode($listTitle));
            $blockAskDelete->setListId($listId);
        }

        $this->renderLayout();
    }

    /**
     * Function createListAction
     *
     */
    public function createListAction()
    {
        /** @var Amasty_List_Model_List $list */
        $list = Mage::getModel('amlist/list');

        /** @var Mage_Customer_Model_Session $customer */
        $customer = Mage::getSingleton('customer/session');

        $fromCart = $this->getRequest()->getParam('from_cart');
        $fromCreation = $this->getRequest()->getParam('create');
        $productId = $this->getRequest()->getParam('product');
        $wishlistName = $this->getRequest()->getParam('wishlist_name');

        if (!$fromCreation) {
            $wishlistName = base64_decode($wishlistName);
        }

        $qty = $this->getRequest()->getParam('qty');
        $origin = $this->getRequest()->getParam('origin');

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');

        /** @var Magoffice_Multiwishlist_Helper_Data $helper */
        $helper = Mage::helper('magoffice_multiwishlist');

        $addUrl = null;

        if ($productId) {
            $addUrl = $helper->getAddUrl($productId);
        }

        if (((!$fromCreation && !$fromCart) && !$productId) || !$wishlistName || !$customer->isLoggedIn()) {
            return false;
        }

        try {
            $list->setTitle($wishlistName);
            $list->setCustomerId($customer->getId());
            $list->setListType(Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_MANUAL);
            $list->setCreatedAt($dateModel->date('Y-m-d'));
            $list->save();

            $list->setTechnicTitle(Magoffice_Multiwishlist_Helper_Data::LIST_MANUAL_TECHNIC_TITLE . $list->getListId());
            $list->save();

            if ($productId) {
                $addUrl = $addUrl . "list/" . $list->getListId();

                if ($qty) {
                    $addUrl = $addUrl . "/qty/" . $qty;
                }
                if ($origin) {
                    $addUrl = $addUrl . "/origin/" . $origin;
                }

                $this->_redirectUrl($addUrl);
            }

            if ($fromCart) {
                $this->_redirect('*/*/addCartToList',
                    array('origin' => Magoffice_Multiwishlist_Helper_Data::ORIGIN_ADDING_FROM_CART_PAGE));
            }

            if ($fromCreation) {
                Mage::getSingleton('customer/session')
                    ->addSuccess($this->__('La liste d\'achats %s a bien été créée', $wishlistName));
                $this->_redirect('amlist/list');
            }

        } catch (Exception $e) {

        }

        return true;
    }

    /**
     * Add product(s) to the list
     */
    public function addItemAction()
    {
        $session = Mage::getSingleton('customer/session');

        $productId = $this->getRequest()->getParam('product');

        $list = Mage::getModel('amlist/list');
        $listId = $this->getRequest()->getParam('list');

        if (!$listId) { //get default - last
            $listId = $list->getLastListId($this->_customerId);
        }

        $list->load($listId);
        if ($list->getCustomerId() == $this->_customerId) {
            try {
                $product = Mage::getModel('catalog/product')
                               ->setStoreId(Mage::app()->getStore()->getId())
                               ->load($productId);
                $request = $this->_getProductRequest();

                if ($product->getTypeId() == 'grouped') {
                    $cnt = 0; //subproduct count
                    if ($request && !empty($request['super_group'])) {
                        foreach ($request['super_group'] as $subProductId => $qty) {
                            if (!$qty)
                                continue;

                            $request = new Varien_Object();
                            $request->setProduct($subProductId);
                            $request->setQty($qty);

                            $subProduct = Mage::getModel('catalog/product')
                                              ->setStoreId(Mage::app()->getStore()->getId())
                                              ->load($subProductId);

                            // check if params are valid
                            $customOptions = $subProduct->getTypeInstance()->prepareForCart($request, $subProduct);

                            // string == error during prepare cycle
                            if (is_string($customOptions)) {
                                $session->setRedirectUrl($product->getProductUrl());
                                Mage::throwException($customOptions);
                            }

                            $list->addItem($subProductId, $customOptions);

                            $cnt++;
                        }
                    }
                } else { //if product is not grouped
                    // check if params are valid
                    $customOptions = $product->getTypeInstance()->prepareForCart($request, $product);

                    // string == error during prepare cycle
                    if (is_string($customOptions)) {
                        $session->setRedirectUrl($product->getProductUrl());
                        Mage::throwException($customOptions);
                    }

                    $list->addItem($productId, $customOptions);
                }
            } catch (Exception $e) {
                $url = $session->getRedirectUrl(true);
                if ($url) {
                    Mage::getSingleton('checkout/session')->addNotice($e->getMessage());
                    $this->getResponse()->setRedirect($url);
                } else {
                    $session->addError($this->__('There was an error while adding item to the list: %s',
                        $e->getMessage()));
                }
            }
        }

        $this->loadLayout();

        $blockConfirm = $this->getLayout()->getBlock('confirmaddingwishlist');

        if ($productId) {
            $blockConfirm->setListName($list->getTitle());
            $blockConfirm->setListId($list->getListId());
        }

        $this->renderLayout();
    }

    /**
     * Function addCartToList
     *
     */
    public function addCartToListAction()
    {
        $session = Mage::getSingleton('customer/session');

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/cart')->getQuote();

        $list = Mage::getModel('amlist/list');
        $listId = $this->getRequest()->getParam('list');

        if (!$listId) { //get default - last
            $listId = $list->getLastListId($this->_customerId);
        }

        $list->load($listId);
        if ($list->getCustomerId() == $this->_customerId) {
            try {

                /** @var Mage_Sales_Model_Quote_Item $quoteItem */
                foreach ($quote->getAllItems() as $quoteItem) {
                    $product = $quoteItem->getProduct();

                    /** @var Magoffice_Exaprint_Model_Cardconfig $cardModel */
                    $cardModel = Mage::getModel('magoffice_exaprint/cardconfig');
                    $configuration = $cardModel->getCardConfigsQuoteItem($quoteItem->getId());


                    if ($configuration->getData('conf_id')) {
                        $productId = $configuration->getData('template_entity_id');
                    } else {
                        $productId = $product->getId();
                    }

                    $request = $this->_getProductRequest();

                    $request->setQty($quoteItem->getQty());

                    if ($product->getTypeId() == 'grouped') {
                        $cnt = 0; //subproduct count
                        if ($request && !empty($request['super_group'])) {
                            foreach ($request['super_group'] as $subProductId => $qty) {
                                if (!$qty)
                                    continue;

                                $request = new Varien_Object();
                                $request->setProduct($subProductId);
                                $request->setQty($qty);

                                $subProduct = Mage::getModel('catalog/product')
                                                  ->setStoreId(Mage::app()->getStore()->getId())
                                                  ->load($subProductId);

                                // check if params are valid
                                $customOptions = $subProduct->getTypeInstance()->prepareForCart($request, $subProduct);

                                // string == error during prepare cycle
                                if (is_string($customOptions)) {
                                    $session->setRedirectUrl($product->getProductUrl());
                                    Mage::throwException($customOptions);
                                }

                                $list->addItem($subProductId, $customOptions);

                                $cnt++;
                            }
                        }
                    } else { //if product is not grouped
                        // check if params are valid
                        $customOptions = $product->getTypeInstance()->prepareForCart($request, $product);

                        // string == error during prepare cycle
                        if (is_string($customOptions)) {
                            $session->setRedirectUrl($product->getProductUrl());
                            Mage::throwException($customOptions);
                        }

                        $list->addItem($productId, $customOptions);
                    }
                }
            } catch (Exception $e) {
                $url = $session->getRedirectUrl(true);
                if ($url) {
                    Mage::getSingleton('checkout/session')->addNotice($e->getMessage());
                    $this->getResponse()->setRedirect($url);
                } else {
                    $session->addError($this->__('There was an error while adding items to the list: %s',
                        $e->getMessage()));
                }
            }
        }

        $this->loadLayout();

        $blockConfirm = $this->getLayout()->getBlock('confirmaddingcarttowishlist');

        $blockConfirm->setOrigin('wishlist');
        $blockConfirm->setListName($list->getTitle());
        $blockConfirm->setListId($list->getListId());

        $this->renderLayout();
    }

    /**
     * Delete list
     */
    public function removeAction()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $list = Mage::getModel('amlist/list')->load($id);

        if ($list->getCustomerId() == $this->_customerId) {
            try {
                $listTitle = $list->getTitle();

                $list->delete();
                Mage::getSingleton('customer/session')
                    ->addSuccess($this->__('La suppression de la liste %s a bien été réalisée.', $listTitle));
            } catch (Exception $e) {
                Mage::getSingleton('customer/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('amlist/list');
    }

    /**
     * Delete a product from a list
     */
    public function removeItemAction()
    {
        $id = (int)$this->getRequest()->getParam('id');

        $item = Mage::getModel('amlist/item');
        $item->load($id);
        if (!$item->getId()) {
            $this->_redirect('*/*/');
        }

        $list = Mage::getModel('amlist/list');
        $list->load($item->getListId());
        if ($list->getCustomerId() != $this->_customerId) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $item->delete();
            Mage::getSingleton('customer/session')
                ->addSuccess($this->__('L\'article a bien été supprimé de la liste d\'achats'));
        } catch (Exception $e) {
            Mage::getSingleton('customer/session')
                ->addError($this->__('There was an error while removing item from the folder: %s', $e->getMessage()));
        }
        $this->_redirect('*/*/edit', array('id' => $list->getId()));

    }

    /**
     * Function createlistajaxAction
     *
     */
    public function createlistajaxAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Function renamelistajaxAction
     *
     */
    public function renamelistajaxAction()
    {
        $listId = (int)$this->getRequest()->getParam('id');
        $list = Mage::getModel('amlist/list')->load($listId);

        $this->loadLayout();

        $blockRename = $this->getLayout()->getBlock('renamewishlist');

        if ($list) {
            $blockRename->setList($list);
        }

        $this->renderLayout();
    }

    /**
     * Function checkDataAjaxAction
     *
     * @return bool
     */
    public function checkDataAjaxAction()
    {
        $wishlistName = $this->getRequest()->getParam('wishlist_name');
        $oldName = $this->getRequest()->getParam('old_name');
        $renameAction = $this->getRequest()->getParam('rename');

        $oldName = base64_decode($oldName);
        $wishlistName = base64_decode($wishlistName);

        $nbChars = strlen($wishlistName);

        // check count of chars of new name
        if (!$wishlistName || $nbChars < 1 || $nbChars > 64) {
            echo $this->__('Le nom de votre liste doit impérativement comporter entre 1 et 64 caractères maximum');
            return false;
        }

        // max count of wishlist reached
        $configMaxList = Mage::getStoreConfig('magoffice_multiwishlist/wishlist_configuration/max_customer_wishlist');

        /** @var Amasty_List_Model_Mysql4_List_Collection $lists */
        $lists = Mage::getResourceModel('amlist/list_collection')
                     ->addCustomerFilter(Mage::getSingleton('customer/session')->getCustomerId())
                     ->addFieldToFilter('list_type',
                         array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_MANUAL))
                     ->setOrder('title', 'ASC')
                     ->load();

        if (!$renameAction) {
            if ($lists->count() >= $configMaxList) {
                echo $this->__('Vous avez atteint le nombre maximum de listes');
                return false;
            }
        }

        // a list with this name already exist
        if ($wishlistName != $oldName) {
            if ($lists) {
                foreach ($lists as $list) {
                    if ($list->getTitle() == $wishlistName) {
                        echo $this->__('Une liste portant ce nom existe déjà');
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Save list details
     */
    public function saveAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*/');
            return;
        }
        $id = $this->getRequest()->getParam('id');
        $list = Mage::getModel('amlist/list');
        if ($id) {
            $list->load($id);
            if ($list->getCustomerId() != $this->_customerId
                || $list->getListType() == Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_AUTO
            ) {
                $this->_redirect('*/*/');
                return;
            }
        }

        $data = $this->getRequest()->getPost();

        if ($data) {
            $list->setData($data)->setId($id);
            try {
                $list->setCustomerId($this->_customerId);
                $list->setUpdatedAt(date('Y-m-d'));
                $list->save();
                Mage::getSingleton('customer/session')->addSuccess(Mage::helper('amlist')
                                                                       ->__('La liste d\'achats à bien été sauvegardée.'));
                Mage::getSingleton('customer/session')->setListFormData(false);

                $productId = Mage::getSingleton('amlist/session')->getAddProductId();
                Mage::getSingleton('amlist/session')->setAddProductId(null);
                if ($productId) {
                    $this->_redirect('*/*/addItem', array('product' => $productId, 'list' => $list->getId()));
                    return;
                }
                $this->_redirect('amlist/list/edit', array('id' => $list->getId()));
                return;

            } catch (Exception $e) {
                Mage::getSingleton('customer/session')->addError($e->getMessage());
                Mage::getSingleton('customer/session')->setListFormData($data);
                $this->_redirect('*/*/', array('id' => $this->getRequest()->getParam('id')));
                return;
            }
        }
        Mage::getSingleton('customer/session')->addError(Mage::helper('amlist')
                                                             ->__('Unable to find folder for saving'));
        $this->_redirect('*/*/');
    }

    /**
     * Function myTopProductAction
     *
     * @return bool
     */
    public function myTopProductAction()
    {
        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');

        if (!$customerSession->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return false;
        }

        $customerId = $customerSession->getCustomerId();

        /** @var Amasty_List_Model_Mysql4_List_Collection $lists */
        $lists = Mage::getResourceModel('amlist/list_collection')
                     ->addCustomerFilter($customerId)
                     ->addFieldToFilter('list_type',
                         array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_AUTO))
                     ->addFieldToFilter('technic_title',
                         array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_AUTO_TECHNIC_TITLE))
                     ->load();

        $count = $lists->count();

        if ($count) {
            $list = $lists->getFirstItem();

            $this->_redirect('amlist/list/edit', array('id' => $list->getListId(), 'mytopproduct' => true));
        } else {
            $this->_redirect('amlist/list/edit', array('id' => null, 'mytopproduct' => true));
        }

        return true;
    }

    /**
     * Function updateQtyAjaxAction
     *
     */
    public function updateQtyAjaxAction()
    {
        $itemId = $this->getRequest()->getParam('item_id');
        $qty = $this->getRequest()->getParam('qty');

        if (!$itemId || !$qty) {
            echo "error while quantity update";
            return false;
        }

        /** @var Amasty_List_Model_Item $itemModel */
        $itemModel = Mage::getModel('amlist/item');

        $item = $itemModel->load($itemId);

        if (!$item) {
            echo "no item found";
            return false;
        }

        $item->setQty($qty);
        $item->setUpdatedAt(date('Y-m-d'));
        $item->save();

        echo $item->getQty();
        return true;
    }

    /**
     * Function cartAction
     *
     * @throws Exception
     */
    public function cartAction()
    {
        $messages = array();

        $listId = $this->getRequest()->getParam('list_id');
        $origin = $this->getRequest()->getParam('origin');

        $list = Mage::getModel('amlist/list')->load($listId);
        if (!$list->getId()) {
            $this->_redirect('*/*');
            return;
        }

        $isPost = $this->getRequest()->isPost();
        $selectedIds = $this->getRequest()->getParam('cb');
        if ($isPost && (!$selectedIds || !is_array($selectedIds))) {
            Mage::getSingleton('customer/session')
                ->addNotice(
                    Mage::helper('amlist')->__('Veuillez sélectionner des produits avant d\'ajouter au panier.')
                );
            $this->_redirect('*/*/edit', array('id' => $list->getId()));
            return;
        }

        if ($this->getRequest()->getParam('stock_id')) {
            $request = $this->getRequest();
            $whId = (int)$request->getParam('stock_id');
            $wh = Mage::getModel('warehouse/warehouse')->load($whId);

            if ($wh) {
                Mage::getSingleton('core/session')->setSelectedStockId($wh->getStockId());
            }
        }

        $isDrive = $this->getRequest()->getParam('is_drive');
        $stockId = Mage::helper('warehouse/afgwarehouse')->getSelectedStockId();

        $stockWeb = false;
        $qtyWeb = 0;
        $stockDrive = false;
        $qtyDrive = 0;

        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $cart->getQuote();

        foreach ($list->getItems() as $item) {
            if ($isPost && !in_array($item->getId(), $selectedIds)) {
                continue;
            }

            try {
                $product = $item->getProduct();

                $categoryName = "Accueil";
                $categoryLink = Mage::getBaseUrl();

                $categoryIds = $product->getCategoryIds();

                /** @var Mage_Catalog_Model_Category $category */
                $category = Mage::getResourceModel('catalog/category_collection')
                                ->addAttributeToSelect('*')
                                ->addAttributeToFilter('level', array('in' => array('4', '3')))
                                ->addAttributeToFilter('entity_id', array('in' => $categoryIds))
                                ->addIsActiveFilter()
                                ->setOrder('level', 'DESC')
                                ->getFirstItem();

                if ($category) {
                    $categoryName = $category->getName();
                    $categoryLink .= $category->getUrlPath();
                }

                /** @var Innoexts_Warehouse_Helper_Afgwarehouse $warehouseHelper */
                $warehouseHelper = Mage::helper('warehouse/afgwarehouse');
                $stockItem = null;
                $stockItemWeb = null;

                if ($product->isConfigurable()) {
                    $configurable = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
                    $simpleCollection = $configurable->getUsedProductCollection()->addAttributeToSelect('*')
                                                     ->addFilterByRequiredOptions();

                    foreach ($simpleCollection as $simpleProduct) {
                        /** @var Innoexts_Warehouse_Model_Cataloginventory_Stock_Item $stockItem */
                        $stockItem = Mage::helper('warehouse/cataloginventory')
                                         ->getStockItemCached($simpleProduct->getId(), $stockId);

                        if ($isDrive && $stockId) {
                            if ($warehouseHelper->getisQtyToStock($stockId, $simpleProduct) && $stockItem) {
                                $stockDrive = true;
                                $qtyDrive = $stockItem->getQty();
                            } else {
                                $stockDrive = false;
                                $qtyDrive = 0;
                            }
                        } else {
                            $stockDrive = false;
                            $qtyDrive = 0;
                        }

                        /** @var Innoexts_Warehouse_Model_Cataloginventory_Stock_Item $stockItemWeb */
                        $stockItemWeb =
                            Mage::helper('warehouse/cataloginventory')->getStockItemCached($simpleProduct->getId(), 1);

                        if ($warehouseHelper->getisQtyToStock(1, $simpleProduct) && $stockItemWeb) {
                            $stockWeb = true;
                            $qtyWeb = $stockItemWeb->getQty();
                        }
                    }
                } else {
                    if ($isDrive && $stockId) {
                        /** @var Innoexts_Warehouse_Model_Cataloginventory_Stock_Item $stockItem */
                        $stockItem =
                            Mage::helper('warehouse/cataloginventory')->getStockItemCached($product->getId(), $stockId);

                        if ($warehouseHelper->getisQtyToStock($stockId, $product) && $stockItem) {
                            $stockDrive = true;
                            $qtyDrive = $stockItem->getQty();
                        } else {
                            $stockDrive = false;
                            $qtyDrive = 0;
                        }
                    } else {
                        $stockDrive = false;
                        $qtyDrive = 0;
                    }

                    /** @var Innoexts_Warehouse_Model_Cataloginventory_Stock_Item $stockItemWeb */
                    $stockItemWeb =
                        Mage::helper('warehouse/cataloginventory')->getStockItemCached($product->getId(), 1);

                    if ($warehouseHelper->getisQtyToStock(1, $product) && $stockItemWeb) {
                        $stockWeb = true;
                        $qtyWeb = $stockItemWeb->getQty();
                    }
                }

                $qty = $item->getQty();
                $req = unserialize($item->getBuyRequest());

                if ($origin) {
                    $req['origin'] = $origin;
                }

                /** @var Mage_Checkout_Model_Session $checkoutSession */
                $checkoutSession = Mage::getSingleton('checkout/session');

                /** @var Mage_Sales_Model_Quote_Item $quoteItem */
                $quoteItem = $quote->getItemByProduct($product);

                $cartItemQty = 0;
                if ($quoteItem) {
                    $cartItemQty = $quoteItem->getQty();

                    $qtyDrive -= $cartItemQty;
                    $qtyWeb -= $cartItemQty;
                }

                $requestedQty = $item->getQty() + $cartItemQty;
                $allowedQty = $req['qty'] + $cartItemQty;

                if ($isDrive) {
                    if ($stockDrive) {
                        if ($qtyDrive > $qty) {
                            // quantity requested available in Drive
                            $req['qty'] = $qty;
                        } else {
                            // not enough quantity in stock, max drive stock available allowed
                            if ($qtyDrive > 0) {
                                $req['qty'] = $qtyDrive;
                                $requestedQty = $item->getQty() + $cartItemQty;
                                $allowedQty = $req['qty'] + $cartItemQty;

                                $checkoutSession->addNotice($this->__('key.message_not_enough_qty',
                                    '"' . $product->getName() . '"', $requestedQty, $allowedQty, $categoryLink,
                                    $categoryName));

                            } else {
                                // drive stock unavailable
                                $req['qty'] = 0;
                                if (!$cartItemQty) {
                                    $checkoutSession->addNotice($this->__('key.message_no_qty',
                                        '"' . $product->getName() . '"', $categoryLink, $categoryName));
                                } else {
                                    $checkoutSession->addNotice($this->__('key.message_not_enough_qty',
                                        '"' . $product->getName() . '"', $requestedQty, $allowedQty, $categoryLink,
                                        $categoryName));
                                }
                            }
                        }
                    } else {
                        // drive stock unavailable
                        $checkoutSession->addNotice($this->__('key.message_no_qty',
                            '"' . $product->getName() . '"', $categoryLink, $categoryName));
                    }
                } else {
                    if ($stockWeb) {
                        if (($stockItemWeb && $stockItemWeb->getBackorders()) || $qtyWeb > $qty) {
                            // quantity requested available in Web
                            $req['qty'] = $qty;
                        } else {
                            // not enough quantity in stock, max web stock available allowed
                            if ($qtyWeb > 0) {
                                $req['qty'] = $qtyWeb;
                                $requestedQty = $item->getQty() + $cartItemQty;
                                $allowedQty = $req['qty'] + $cartItemQty;

                                $checkoutSession->addNotice($this->__('key.message_not_enough_qty',
                                    '"' . $product->getName() . '"', $requestedQty, $allowedQty, $categoryLink,
                                    $categoryName));
                            } else {
                                // web stock unavailable
                                $req['qty'] = 0;
                                if (!$cartItemQty) {
                                    $checkoutSession->addNotice($this->__('key.message_no_qty',
                                        '"' . $product->getName() . '"', $categoryLink, $categoryName));
                                } else {
                                    $checkoutSession->addNotice($this->__('key.message_not_enough_qty',
                                        '"' . $product->getName() . '"', $requestedQty, $allowedQty, $categoryLink,
                                        $categoryName));
                                }
                            }
                        }
                    } else {
                        // web stock unavailable
                        $checkoutSession->addNotice($this->__('key.message_no_qty',
                            '"' . $product->getName() . '"', $categoryLink, $categoryName));
                    }
                }

                if ($isDrive) {
                    $warehouse = Mage::getSingleton('warehouse/warehouse')->getCurrentWarehouseData();
                    if ($warehouse) {
                        $cart->setIdWarehouse($warehouse->getId());
                    }

                    $req['is_drive'] = 1;

                    $cart->setIsDrive(1);
                } else {
                    $cart->setIsWeb(1);
                }

                if ($req['qty'] > 0) {
                    $cart->addProduct($product, $req);
                }
            } catch (Exception $e) {
               // Mage::getSingleton('customer/session')->addNotice($e->getMessage());
            }
        }

        $cart->save();

        $this->_redirect('checkout/cart');
    }
}
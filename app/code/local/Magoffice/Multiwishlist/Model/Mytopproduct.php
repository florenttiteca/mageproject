<?php

/**
 * Class Magoffice_Multiwishlist_Model_Mytopproduct
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Multiwishlist_Model_Mytopproduct extends Mage_Core_Model_Abstract
{
    const MULTIWISHLIST_LOG_FILE_NAME = 'wishlist_favorite_products';
    const MULTIWISHLIST_LOG_TYPE_INFO = '[INFO]';
    const MULTIWISHLIST_LOG_TYPE_ERROR = '[ERROR]';
    const MULTIWISHLIST_LOG_TYPE_WARNING = '[WARNING]';
    const MULTIWISHLIST_LOG_TYPE_DEBUG = '[DEBUG]';

    /** @var Mage_Core_Model_Resource $_resource */
    protected $_resource;

    /** @var Varien_Db_Adapter_Interface $_write */
    protected $_write;

    /** @var Varien_Db_Adapter_Interface $_read */
    protected $_read;

    /**
     * Function _construct
     *
     */
    public function _construct()
    {
        /** @var Mage_Core_Model_Resource $resource */
        $this->_resource = Mage::getSingleton('core/resource');

        $this->_write = $this->_resource->getConnection('core_write');
        $this->_read = $this->_resource->getConnection('core_read');
    }

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::MULTIWISHLIST_LOG_TYPE_INFO, 'Start of My Favorite Products wishlist generation.');

        $result = $this->_wishlistFavoriteProductsGeneration();

        $this->_log($this::MULTIWISHLIST_LOG_TYPE_INFO, $result['updated_counter'] . ' wishlist(s) updated.');
        $this->_log($this::MULTIWISHLIST_LOG_TYPE_INFO, $result['created_counter'] . '  wishlist(s) created.');

        $this->_log($this::MULTIWISHLIST_LOG_TYPE_INFO, 'End of My Favorite Products wishlist generation.');
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
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND);
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . $this::MULTIWISHLIST_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
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
     * Function _wishlistFavoriteProductsGeneration
     *
     * @return array|bool
     * @throws Exception
     */
    protected function _wishlistFavoriteProductsGeneration()
    {
        /** @var Mage_Sales_Model_Order $orderModel */
        $orderModel = Mage::getModel('sales/order');

        $minCountOrder =
            Mage::getStoreConfig('magoffice_multiwishlist/wishlist_configuration/wishlist_favorite_minimum_orders');

        $periodToMinOrder =
            Mage::getStoreConfig(
                'magoffice_multiwishlist/wishlist_configuration/wishlist_favorite_days_for_minimum_order'
            );

        $daysFromLastOrder =
            Mage::getStoreConfig(
                'magoffice_multiwishlist/wishlist_configuration/wishlist_favorite_days_from_last_order'
            );

        $date = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        $fromDate = date("Y-m-d H:i:s", strtotime($date . "-$periodToMinOrder days"));

        if (!$fromDate) {
            $this->_log($this::MULTIWISHLIST_LOG_TYPE_ERROR,
                'An error occured when calculating the date to minimum orders');
            return false;
        }

        $lastOrderDateConfig = date("Y-m-d H:i:s", strtotime($date . "-$daysFromLastOrder days"));

        if (!$lastOrderDateConfig) {
            $this->_log($this::MULTIWISHLIST_LOG_TYPE_ERROR,
                'An error occured when calculating the date from last order');
            return false;
        }

        $updatedCounter = 0;
        $createdCounter = 0;

        $customerTable = $this->_resource->getTableName('customer_entity');

        // Get All Customer data
        $query =
            "
            SELECT `entity_id`, `email`
            FROM {$customerTable}
            ";

        $customerCollection = $this->_read->query($query);
        while ($customerData = $customerCollection->fetch(PDO::FETCH_NUM)) {
            list($customerId, $email) = $customerData;

            if (!$customerId) {
                continue;
            }

            $created = false;
            $topProductsList = null;

            /** @var Amasty_List_Model_Mysql4_List_Collection $lists */
            $lists = Mage::getResourceModel('amlist/list_collection')
                         ->addCustomerFilter($customerId)
                         ->addFieldToFilter('list_type',
                             array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_AUTO))
                         ->addFieldToFilter('technic_title',
                             array('eq' => Magoffice_Multiwishlist_Helper_Data::LIST_AUTO_TECHNIC_TITLE))
                         ->setOrder('title', 'ASC')
                         ->load();

            if ($lists->count()) {
                /** @var Amasty_List_Model_List $topProductsList */
                $topProductsList = $lists->getFirstItem();
            } else {
                $topProductsList = $this->_createFavoriteList($customerId, $email);
                $createdCounter++;
                $created = true;
            }

            /** @var Mage_Sales_Model_Entity_Order_Collection $customerOrders */
            $customerOrders = $orderModel->getCollection()
                                         ->addAttributeToSelect('*')
                                         ->addAttributeToFilter('customer_id',
                                             array('eq' => $customerId))
                                         ->addAttributeToFilter('status', array('nlike' => '%canceled%'))
                                         ->addAttributeToFilter('created_at', array('from' => $fromDate));

            $recentOrder = false;
            if ($customerOrders->count()) {
                /** @var Mage_Sales_Model_Order $order */
                foreach ($customerOrders as $order) {
                    if (!$recentOrder && $order->getCreatedAt() > $lastOrderDateConfig) {
                        $recentOrder = true;
                    }
                }
            }

            if (($customerOrders->count() >= $minCountOrder) || $recentOrder) {
                try {
                    if (!$created && $topProductsList) {
                        $existItems = $topProductsList->getItems();

                        // empty items if favorite products wishlist exist
                        if (count($existItems)) {
                            /** @var Amasty_List_Model_Item $existItem */
                            foreach ($existItems as $existItem) {
                                $existItem->delete();
                            }
                        }
                    }

                    $topProducts = $this->_getFavoritesProducts($customerOrders);

                    if ($topProductsList && count($topProducts)) {
                        $this->_fillFavoriteList($topProductsList, $topProducts);

                        if (!$created) {
                            $updatedCounter++;
                        }
                    }
                } catch (Exception $e) {
                    $this->_log(
                        self::MULTIWISHLIST_LOG_TYPE_ERROR,
                        "An error is occured during wishlist favorite products generation of customer {$customerId}"
                    );
                    Mage::logException($e);
                }
            }
        }

        $result = array(
            'updated_counter' => $updatedCounter,
            'created_counter' => $createdCounter
        );

        return $result;
    }

    /**
     * Function _createFavoriteList
     *
     * @param $customerId
     * @param $email
     * @return Amasty_List_Model_List
     * @throws Exception
     */
    protected function _createFavoriteList($customerId, $email)
    {
        $listAutoName =
            Mage::getStoreConfig('magoffice_multiwishlist/wishlist_configuration/wishlist_favorite_products_name');

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');

        // automatic wishlist creation to customers who haven't got one
        /** @var Amasty_List_Model_List $newList */
        $newList = Mage::getModel('amlist/list')
                       ->setTitle($listAutoName)
                       ->setCustomerId($customerId)
                       ->setCreatedAt($dateModel->date('Y-m-d'))
                       ->setListType(Magoffice_Multiwishlist_Helper_Data::LIST_TYPE_AUTO)
                       ->setTechnicTitle(Magoffice_Multiwishlist_Helper_Data::LIST_AUTO_TECHNIC_TITLE);

        $newList->save();

        $this->_log($this::MULTIWISHLIST_LOG_TYPE_INFO,
            'New wishlist has been created for ' . $email);

        return $newList;
    }

    /**
     * Function _fillFavoriteList
     *
     * @param $topProductsList
     * @param $topProducts
     * @return bool
     * @throws Exception
     */
    protected function _fillFavoriteList($topProductsList, $topProducts)
    {
        if (!$topProductsList || !count($topProducts)) {
            return false;
        }

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');

        foreach ($topProducts as $productId => $occurenceCount) {
            $resourceModel = Mage::getResourceModel('catalog/product');

            $name = $resourceModel->getAttributeRawValue($productId, 'name', 1);
            $finalPrice = $resourceModel->getAttributeRawValue($productId, 'price', 1);

            if ($name && $finalPrice) {
                /** @var Amasty_List_Model_Item $listItemModel */
                $listItemModel = Mage::getModel('amlist/item')
                                     ->setListId($topProductsList->getId())
                                     ->setProductId($productId)
                                     ->setQty(1)
                                     ->setDescr($name)
                                     ->setPricesWhenAdding($finalPrice)
                                     ->setCreatedAt($dateModel->date('Y-m-d'));

                $listItemModel->save();
            }
        }

        $topProductsList->setUpdatedAt($dateModel->date('Y-m-d'));
        $topProductsList->save();

        return true;
    }

    /**
     * Function _getFavoritesProducts
     *
     * @param $customerOrders
     * @return array
     */
    protected function _getFavoritesProducts($customerOrders)
    {
        $topProducts = array();

        /** @var Mage_Sales_Model_Order $customerOrder */
        foreach ($customerOrders as $customerOrder) {
            /** @var Mage_Sales_Model_Order_Item $item */
            foreach ($customerOrder->getAllItems() as $item) {
                $itemProductId = $item->getProductId();

                if (array_key_exists($itemProductId, $topProducts)) {
                    $topProducts[$itemProductId] = $topProducts[$itemProductId] + 1;
                } else {
                    $topProducts[$itemProductId] = 1;
                }
            }
        }

        $maxCountProducts =
            Mage::getStoreConfig('magoffice_multiwishlist/wishlist_configuration/wishlist_favorite_maximum_products');

        arsort($topProducts);
        $topProducts = array_slice($topProducts, 0, $maxCountProducts, true);

        return $topProducts;
    }
}
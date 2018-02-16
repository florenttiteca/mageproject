<?php

/**
 * Class Cgi_LengowExport_Model_Generate
 *
 * @category     Cgi
 * @package      Cgi_LengowExport
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowExport_Model_Generate extends Lengow_Export_Model_Generate
{

    /**
     * Function execCron
     *
     * @param      $idStore
     * @param null $types
     * @param null $status
     * @param null $exportChild
     * @param null $outOfSstock
     * @param null $selectedProducts
     * @param null $limit
     * @param null $offset
     * @param null $idsProduct
     *
     * @return bool
     */
    public function execCron(
        $idStore, $types = null, $status = null, $exportChild = null, $outOfSstock = null, $selectedProducts = null,
        $limit = null, $offset = null, $idsProduct = null
    ) {

        //store start time export
        $timeStart = $this->microtime_float();

        $this->_id_store = $idStore;

        $this->_config['directory_path'] = Afg_Path::getFTPPath() . 'out' . DS . 'lengow' . DS . 'export' . DS;

        $this->_fileFormat = $this->_format;

        if ($this->_isAlreadyLaunch()) {
            Mage::helper('lensync/data')->log('Feed already launch');
            $this->_logExportCron(Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO, 'FEED ALREADY LAUNCH');

            return false;
        }

        // Get products list to export
        $products = $this->_getProductsCollection(
            $types, $status, $exportChild, $outOfSstock, $selectedProducts, $limit,
            $offset, $idsProduct
        );

        $this->_logExportCron(
            Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
            'Start export in store ' . Mage::app()->getStore($this->_id_store)->getName() . '(' . $this->_id_store .
            ')'
        );

        // Gestion des attributs à exporter
        $attributesToExport = $this->_config_model->getMappingAllAttributes($this->_id_store);
        $this->_attrs       = array();
        $feed               = Mage::getModel('Lengow_Export_Model_Feed_' . ucfirst($this->_format));
        $first              = true;
        $last               = false;
        $totalProduct       = count($products);
        $productInc         = 1;
        Mage::helper('lensync/data')->log('Find ' . $totalProduct . ' product' . ($totalProduct > 1 ? 's ' : ' '));

        $this->_logExportCron(
            Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
            'Find ' . $totalProduct . ' product' . ($totalProduct > 1 ? 's ' : ' ')
        );

        // Product counter
        $countSimple         = 0;
        $countSimpleDisabled = 0;
        $countConfigurable   = 0;
        $countBundle         = 0;
        $countGrouped        = 0;
        $countVirtual        = 0;
        // Generate data
        foreach ($products as $p) {
            $arrayData = array();
            $parent    = false;
            $productInc++;
            if ($totalProduct < $productInc) {
                $last = true;
            }
            /** @var Lengow_Export_Model_Catalog_Product $product */
            $product = Mage::getModel('lenexport/catalog_product')
                ->setStoreId($this->_id_store)
                ->setOriginalCurrency($this->getOriginalCurrency())
                ->setCurrentCurrencyCode($this->getCurrentCurrencyCode())
                ->load($p['entity_id']);
            $data    = $product->getData();

            // Load first parent if exist
            $parents              = null;
            $parentInstance       = null;
            $configurableInstance = null;
            $parentId             = null;
            $productType          = 'simple';
            $variationName        = '';
            if ($product->getTypeId() == 'configurable') {
                $countConfigurable++;
                $productType = 'parent';
                $productTemp = $product;
                $variations  = $productTemp
                    ->setOriginalCurrency($this->getOriginalCurrency())
                    ->setCurrentCurrencyCode($this->getCurrentCurrencyCode())
                    ->setStoreId($this->_id_store)
                    ->getTypeInstance(true)
                    ->getConfigurableAttributesAsArray($product);
                if ($variations) {
                    foreach ($variations as $variation) {
                        $variationName .= $variation['frontend_label'] . ',';
                    }
                    $variationName = rtrim($variationName, ',');
                }
            }
            if ($product->getTypeId() == 'virtual') {
                $countVirtual++;
                $productType = 'virtual';
            }
            if ($product->getTypeId() == 'grouped' || $product->getTypeId() == 'bundle') {
                if ($product->getTypeId() == 'bundle') {
                    $countBundle++;
                    $productType = 'bundle';
                } else {
                    $countGrouped++;
                    $productType = 'grouped';
                }
                // get quantity for bundle or grouped products
                $qtys        = array();
                $childrenIds = array_reduce(
                    $product->getTypeInstance(true)->getChildrenIds($product->getId()),
                    function (array $reduce, $value) {
                        return array_merge($reduce, $value);
                    }, array()
                );
                foreach ($childrenIds as $childrenId) {
                    $productTemporary = Mage::getModel('catalog/product')
                        ->setOriginalCurrency($this->getOriginalCurrency())
                        ->setCurrentCurrencyCode($this->getCurrentCurrencyCode())
                        ->setStoreId($this->_id_store)
                        ->load($childrenId);
                    $qtys[]           = $productTemporary->getData('stock_item')->getQty();
                    unset($productTemporary);
                }
                $qtyTemp = min($qtys) > 0 ? min($qtys) : 0;
            }
            if ($product->getTypeId() == 'simple') {
                $countSimple++;
                $parents = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($p['entity_id']);
                if (!empty($parents)) {
                    $parentInstance = $this->_getParentEntity((int)$parents[0]);

                    // Exclude if parent is disabled
                    if ($parentInstance
                        && $parentInstance->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED
                    ) {
                        $countSimpleDisabled++;

                        if ($productInc % 20 == 0) {
                            $this->_logExportCron(
                                Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
                                'Export ' . $productInc . ' products'
                            );
                        }

                        if (method_exists($product, 'clearInstance')) {
                            $product->clearInstance();
                            if ($parent != null) {
                                $parent->clearInstance();
                            }

                            if ($parentInstance != null) {
                                $parentInstance->clearInstance();
                            }
                        }

                        unset($arrayData);
                        continue;
                    }

                    if ($parentInstance && $parentInstance->getId() && $parentInstance->getTypeId() == 'configurable') {
                        $parentId = $parentInstance->getId();

                        $variations = $parentInstance->getTypeInstance(true)
                            ->getConfigurableAttributesAsArray($parentInstance);
                        if ($variations) {
                            foreach ($variations as $variation) {
                                $variationName .= $variation['frontend_label'] . ',';
                            }
                            $variationName = rtrim($variationName, ',');
                        }
                        $productType = 'child';
                    }
                }
            }
            $parents = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild(
                $parentId
                    ? $parentId
                    :
                    $p['entity_id']
            );
            if (!empty($parents)) {
                $tempInstance = Mage::getModel('catalog/product')
                    ->setOriginalCurrency($this->getOriginalCurrency())
                    ->setCurrentCurrencyCode($this->getCurrentCurrencyCode())
                    ->setStoreId($this->_id_store)
                    ->getCollection()
                    ->addAttributeToFilter('type_id', 'grouped')
                    ->addAttributeToFilter('entity_id', array('in' => $parents))
                    ->getFirstItem();

                $parentInstance = $this->_getParentEntity($tempInstance->getId());
            }
            $qty = $product->getData('stock_item');
            // Default data
            $arrayData['sku']        = $product->getSku();
            $arrayData['product_id'] = $product->getId();
            $arrayData['qty']        = (integer)$qty->getQty();
            if ($this->_config_model->get('data/without_product_ordering')) {
                $arrayData['qty'] = $arrayData['qty'] - (integer)$qty->getQtyOrdered();
            }
            if ($product->getTypeId() == 'grouped' || $product->getTypeId() == 'bundle') {
                $arrayData['qty'] = (integer)$qtyTemp;
            }
            $arrayData['status']
                       = $product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED ? 'Disabled'
                : 'Enabled';
            $arrayData = array_merge(
                $arrayData,
                $product->getCategories($product, $parentInstance, $this->_id_store, $this->categoryCache)
            );

            //start create breadcrumb oldest category //
            $categoryIds = $product->getCategoryIds();

            /** @var Mage_Catalog_Model_Resource_Category_Collection $categoryCollection */
            $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('level', array('in' => array('4', '3')))
                ->addAttributeToFilter('entity_id', array('in' => $categoryIds))
                ->addIsActiveFilter();

            // sort by lowest level category, and oldest (smallest entity_id)
            $categoryCollection->addAttributeToSort('level', 'DESC');
            $categoryCollection->addAttributeToSort('entity_id', 'ASC');

            /** @var C2bi_Catalogc2bi_Model_Categoryc2bi $category */
            $category = $categoryCollection->getFirstItem();

            $pathInStore = $category->getPathInStore();
            $pathIds     = array_reverse(explode(',', $pathInStore));

            $categories = $category->getParentCategories();

            $crumbs = null;

            foreach ($pathIds as $categoryId) {
                if (isset($categories[$categoryId]) && $categories[$categoryId]->getName()) {
                    if ($crumbs != null) {
                        $crumbs .= ' > ';
                    }

                    $crumbs .= $categories[$categoryId]->getName();
                }
            }

            $arrayData['category-breadcrumb'] = $crumbs;
            //end create breadcrumb oldest category //


            $arrayData = array_merge(
                $arrayData, $product->getPrices($product, $configurableInstance, $this->_id_store)

            );
            $arrayData = array_merge($arrayData, $product->getShippingInfo($product));
            // Images, gestion de la fusion parent / enfant

            if ($this->_config_model->get('data/parentsimages') && isset($parentInstance) && $parentInstance !== false
            ) {
                $count  = 1;
                $images = array();
                foreach ($product->getMediaGalleryImages() as $image) {

                    $images['image-url-' . $count] = $image->getData('url');
                    $count++;
                }

                $imagesp = array('images' => array(), 'value' => array());
                foreach ($parentInstance->getMediaGalleryImages() as $image) {
                    $imagesp['images'][] = $image->getData();
                }

                $arrayData = array_merge($arrayData, $images, $imagesp);
            } else {
                $count  = 1;
                $images = array();
                foreach ($product->getMediaGalleryImages() as $image) {
                    $images['image-url-' . $count] = $image->getData('url');
                    $count++;
                }

                $arrayData = array_merge($arrayData, $images);

                for ($i = 1; $i <= 5; $i++) {
                    if (!$arrayData['image-url-' . $i]) {
                        $arrayData['image-url-' . $i] = "";
                    }
                }
            }

            // formatdata -> replace special chars with html chars
            $formatData = $this->_config_model->get('data/formatdata') == 1 ? true : false;
            if ($product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
                && isset($parentInstance)
            ) {
                $arrayData['product-url']       = $parentInstance->getUrlInStore()
                    ? $parentInstance->getUrlInStore()
                    :
                    $parentInstance->getProductUrl();
                $arrayData['name']              = $this->_helper->cleanData(
                    $parentInstance->getName(), $formatData,
                    in_array('name', $this->_config_model->getHtmlAttributes())
                );
                $arrayData['description']       = $this->_helper->cleanData(
                    $parentInstance->getDescription(), $formatData,
                    in_array('description', $this->_config_model->getHtmlAttributes())
                );
                $arrayData['short_description'] = $this->_helper->cleanData(
                    $parentInstance->getShortDescription(), $formatData,
                    in_array('short_description', $this->_config_model->getHtmlAttributes())
                );
            } else {
                $arrayData['product-url']       = $product->getUrlInStore() ? $product->getUrlInStore()
                    : $product->getProductUrl();
                $arrayData['name']              = $this->_helper->cleanData(
                    $product->getName(), $formatData,
                    in_array('name', $this->_config_model->getHtmlAttributes())
                );
                $arrayData['description']       = $this->_helper->cleanData(
                    $product->getDescription(), $formatData,
                    in_array('description', $this->_config_model->getHtmlAttributes())
                );
                $arrayData['short_description'] = $this->_helper->cleanData(
                    $product->getShortDescription(), $formatData,
                    in_array('short_description', $this->_config_model->getHtmlAttributes())
                );
            }
            $arrayData['parent_id'] = $parentId;
            // Product variation
            $arrayData['product_type']      = $productType;
            $arrayData['product_variation'] = $variationName;
            $arrayData['image_default']
                                            = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
                . 'catalog/product' . $product->getImage();
            $arrayData['child_name']        = $this->_helper->cleanData($product->getName(), $formatData);
            // Selected attributes to export with Frond End value of current shop
            if (!empty($attributesToExport)) {
                foreach ($attributesToExport as $field => $attr) {
                    if (!in_array($field, $this->_excludes) && !isset($arrayData[$field])) {
                        if ($product->getData($field) === null) {
                            $arrayData[$attr] = '';
                        } else {
                            if (is_array($product->getData($field))) {
                                $arrayData[$attr] = implode(',', $product->getData($field));
                            } else {
                                $arrayData[$attr] = $this->_helper->cleanData(
                                    $product->getResource()->getAttribute($field)->getFrontend()->getValue($product),
                                    $formatData,
                                    in_array($field, $this->_config_model->getHtmlAttributes())
                                );
                            }
                        }
                    }
                }
            }
            // Get header of feed
            if ($first) {
                $fieldsHeader = array();
                foreach ($arrayData as $name => $value) {
                    $fieldsHeader[] = $name;
                }

                $feed->setFields($fieldsHeader);
                $this->_write($feed->makeHeader());
                $first = false;
            }
            $this->_write($feed->makeData($arrayData, array('last' => $last)));

            if ($productInc % 20 == 0) {
                $this->_logExportCron(
                    Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
                    'Export ' . $productInc . ' products'
                );
            }

            if (method_exists($product, 'clearInstance')) {
                $product->clearInstance();
            }
            unset($arrayData);
        }
        $this->_write($feed->makeFooter());
        // Product counter and warning
        $totalSimple  = $countSimple - $countSimpleDisabled;
        $total        = $countConfigurable + $countGrouped + $countBundle + $countVirtual + $totalSimple;
        $messageCount = 'Export ' . $total . ' product' . ($totalProduct > 1 ? 's ' : '') . ' ('
            . $totalSimple . ' simple product' . ($totalSimple > 1 ? 's ' : '') . ', '
            . $countConfigurable . ' configurable product' . ($countConfigurable > 1 ? 's ' : '') . ', '
            . $countBundle . ' bundle product' . ($countBundle > 1 ? 's ' : '') . ', '
            . $countGrouped . ' grouped product' . ($countGrouped > 1 ? 's ' : '') . ', '
            . $countVirtual . ' virtual product' . ($countVirtual > 1 ? 's ' : '') . ')';
        Mage::helper('lensync/data')->log($messageCount);
        if ($countSimpleDisabled > 1) {
            if ($countSimpleDisabled == 1) {
                $messageWarning = 'WARNING ! 1 simple product is associated with a disabled configurable product';
            } else {
                $messageWarning = 'WARNING ! ' . $countSimpleDisabled .
                    ' simple products are associated with configurable products disabled';
            }

            Mage::helper('lensync/data')->log($messageWarning);
            $this->_logExportCron(Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_WARNING, $messageWarning);
        }

        $this->_logExportCron(Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO, $messageCount);

        $this->_copyFile();

        $urlFile = DS . 'ftp' . DS . 'out' . DS . 'lengow' . DS . 'export' . DS;

        $this->_logExportCron(
            Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
            'Export of the store ' . Mage::app()->getStore($this->_id_store)->getName() . '(' . $this->_id_store .
            ') generated a file here : ' . $urlFile
        );

        Mage::helper('lensync/data')->log(
            'Export of the store ' .
            Mage::app()->getStore($this->_id_store)->getName() . '(' .
            $this->_id_store . ') generated a file here : ' . $urlFile
        );

        $timeEnd = $this->microtime_float();
        $time    = $timeEnd - $timeStart;
        $this->_logExportCron(
            Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
            "Memory Usage " . memory_get_usage() / 1000000
        );
        $this->_logExportCron(
            Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_TYPE_INFO,
            "Execution time $time secondes"
        );

        return true;
    }

    /**
     * Function _logExportCron
     *
     * @param $type
     * @param $message
     */
    protected function _logExportCron($type, $message)
    {
        $logFile = Mage::getBaseDir('log') . DS . Cgi_LengowExport_Model_Export::LENGOWEXPORT_LOG_FILE_NAME . '_' .
            date('Ymd') . '.log';

        $dateTimestamp = date('Ymd') . ' / ' . date('His') . ' : ';

        if (!file_exists($logFile)) {
            file_put_contents($logFile, $dateTimestamp . ' ' . $type . ' ' . $message . PHP_EOL);
            chmod($logFile, 0777);
        } else {
            file_put_contents(
                $logFile, $dateTimestamp . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * Function getFilePath
     *
     * @return string
     */
    public function getFilePath()
    {
        $filePath = Afg_Path::getFTPPath() . 'out' . DS . 'lengow' . DS . 'export' . DS;

        return $filePath . $this->_fileName . '.' . $this->_format;
    }
}
<?php

/**
 * Class Magoffice_Sellsecure_Block_Adminhtml_Sales_Order_Grid
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Magoffice_Sellsecure_Block_Adminhtml_Sales_Order_Grid constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Retrieve collection class
     *
     * @return string
     */
    protected function _getCollectionClass()
    {
        return 'sales/order_grid_collection';
    }

    /**
     * Function _prepareColumns
     *
     * @return $this
     * @throws Exception
     */
    protected function _prepareColumns()
    {

        $this->addColumn(
            'real_order_id', array(
                'header'       => Mage::helper('sales')->__('Order #'),
                'width'        => '80px',
                'type'         => 'text',
                'index'        => 'increment_id',
                'filter_index' => 'main_table.increment_id',
            )
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'store_id', array(
                    'header'          => Mage::helper('sales')->__('Purchased From (Store)'),
                    'index'           => 'store_id',
                    'type'            => 'store',
                    'store_view'      => true,
                    'display_deleted' => true,
                )
            );
        }

        $this->addColumn('order_id_lengow', array(
            'header' => Mage::helper('sales')->__('Id Lengow'),
            'width'  => '80px',
            'type'   => 'text',
            'index'  => 'order_id_lengow',
            'filter_index'=>'main_table.order_id_lengow',
        ));

        $this->addColumn(
            'created_at', array(
                'header'       => Mage::helper('sales')->__('Purchased On'),
                'index'        => 'created_at',
                'type'         => 'datetime',
                'width'        => '100px',
                'filter_index' => 'main_table.created_at',
            )
        );

        $this->addColumn(
            'pro_rs', array(
                'header' => Mage::helper('customer')->__('Société'),
                'width'  => '100',
                'index'  => 'pro_rs',
            )
        );


        $this->addColumn(
            'billing_name', array(
                'header' => Mage::helper('sales')->__('Bill to Name'),
                'index'  => 'billing_name',
            )
        );

        $this->addColumn(
            'shipping_name', array(
                'header' => Mage::helper('sales')->__('Ship to Name'),
                'index'  => 'shipping_name',
            )
        );

        $this->addColumn('method', array(
            'header'   => Mage::helper('sales')->__('Paiement'),
            'index'    => 'method',
            'type'     => 'options',
            'renderer' => 'flow/adminhtml_renderer_paiement',
            'options'  =>
                array(
                    "checkmo"     => __('Chèque'),
                    "atos"        => __('CB'),
                    "ops_cc"      => __('CB Ogone'),
                    "bankpayment" => __('Virement'),
                    "oney"        => __('Oney'),
                    "retrait"     => __('Retrait'),
                    "nopayment"   => __('Sans paiement'),
                    "market_place" => __('Market place')
                ),
        ));


        $this->addColumn(
            'base_grand_total', array(
                'header'   => Mage::helper('sales')->__('G.T. (Base)'),
                'index'    => 'base_grand_total',
                'type'     => 'currency',
                'currency' => 'base_currency_code',
            )
        );

        $this->addColumn(
            'grand_total', array(
                'header'   => Mage::helper('sales')->__('G.T. (Purchased)'),
                'index'    => 'grand_total',
                'type'     => 'currency',
                'currency' => 'order_currency_code',
            )
        );

        $this->addColumn(
            'status', array(
                'header'  => Mage::helper('sales')->__('Status'),
                'index'   => 'status',
                'type'    => 'options',
                'width'   => '70px',
                'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
            )
        );

        $this->addColumn(
            'traitement', array(
                'header'   => Mage::helper('sales')->__('En traitement'),
                'index'    => 'traitement',
                'width'    => '150px',
                'type'     => 'options',
                'renderer' => 'salesweb/adminhtml_renderer_traitement',
                'options'  => Mage::helper('salesweb')->getTraitementFilter()

            )
        );


        $this->addColumn(
            'transactions_cb_id', array(
                'header' => Mage::helper('sales')->__('N trans. CB'),
                'width'  => '80px',
                'type'   => 'text',
                'index'  => 'transactions_cb_id',
            )
        );

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            $this->addColumn(
                'action',
                array(
                    'header'    => Mage::helper('sales')->__('Action'),
                    'width'     => '50px',
                    'type'      => 'action',
                    'getter'    => 'getId',
                    'actions'   => array(
                        array(
                            'caption' => Mage::helper('sales')->__('View'),
                            'url'     => array('base' => '*/sales_order/view'),
                            'field'   => 'order_id'
                        )
                    ),
                    'filter'    => false,
                    'sortable'  => false,
                    'index'     => 'stores',
                    'is_system' => true,
                )
            );
        }

        if (Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/execute_mode_activation')) {
            $this->addColumn(
                'sellsecure',
                array(
                    'header'   => 'Sellsecure',
                    'sortable' => false,
                    'type'     => 'sellsecure',
                    'align'    => 'center',
                    'width'    => '20',
                    'renderer' => 'magoffice_sellsecure/adminhtml_widget_grid_column_renderer_sellsecure',
                    'filter'   => 'magoffice_sellsecure/adminhtml_widget_grid_column_filter_sellsecure'
                )
            );
        }

        $this->addColumn(
            'id_address_ldf', array(
                'header'       => Mage::helper('sales')->__('Article(s) LDF ?'),
                'align'        => 'left',
                'width'        => '80px',
                'filter_index' => 'id_address_ldf',
                'index'        => 'id_address_ldf',
                'renderer'     => 'salesweb/adminhtml_renderer_ldf',
                'escape'       => true,
            )
        );

        $this->addColumn(
            'is_master', array(
                'header'       => Mage::helper('sales')->__('Parenté'),
                'align'        => 'left',
                'width'        => '80px',
                'filter_index' => 'is_master',
                'index'        => 'is_master',
                'renderer'     => 'salesweb/adminhtml_renderer_parenthood',
                'escape'       => true,
            )
        );

        $this->addRssList('rss/order/new', Mage::helper('sales')->__('New Order RSS'));

        $this->addExportType('*/*/exportCsv', Mage::helper('sales')->__('CSV'));
        $this->addExportType('*/*/exportExcel', Mage::helper('sales')->__('Excel XML'));

        return parent::_prepareColumns();
    }

    /**
     * Function _prepareCollection
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        /** @var Mage_Sales_Model_Resource_Order_Grid_Collection $collection */
        $collection = Mage::getResourceModel($this->_getCollectionClass());

        $collection->join(
            array('order_payment' => 'sales/order_payment'),
            'main_table.entity_id = order_payment.parent_id'
            ,
            array(
                'method' => 'method'
            )
        );

        $collection->join(
            array('attribute' => 'eav/attribute'),
            'attribute_code = "pro_rs"'
            ,
            array(
                'attribute_id' => 'attribute_id'
            )
        );

        $customerEntityVarchar = Mage::getSingleton('core/resource')->getTableName('customer_entity_varchar');
        $collection->getSelect()
            ->joinLeft(
                array(
                    'at_pro_rs' =>
                        $customerEntityVarchar
                ),
                'at_pro_rs.entity_id = main_table.customer_id AND at_pro_rs.attribute_id = attribute.attribute_id',
                array(
                    'pro_rs' => 'at_pro_rs.value'
                )
            )
            ->joinLeft(
                array(
                    'sellsecure_order' =>
                        $collection->getTable('magoffice_sellsecure/sell_secure_order')
                ),
                'main_table.entity_id = sellsecure_order.order_id',
                array(
                    'sellsecure_state' => 'sellsecure_order.state',
                    'sellsecure_eval'  => 'sellsecure_order.scoring_eval_score'
                )
            );

        $collection->getSelect()->group('main_table.entity_id');
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Function _addColumnFilterToCollection
     *
     * @param $column
     *
     * @return $this
     */
    protected function _addColumnFilterToCollection($column)
    {
        $filter = $column->getFilter();

        if ($filter->getData('type') != 'adminhtml/widget_grid_column_filter_datetime') {
            if ($column->getFilter()->getValue() == 'drive_pending') {
                $this->getCollection()->getSelect()->where("is_drive=?", 1);
            }
        }

        $column_id = array('pro_rs');

        if (in_array($column->getId(), $column_id) && $column->getFilter()->getValue()) {
            $value = $column->getFilter()->getValue();
            $this->getCollection()->getSelect()->where("at_pro_rs.value like '$value%'");
        } else {
            parent::_addColumnFilterToCollection($column);
        }

        return $this;
    }

    /**
     * Function _prepareMassaction
     *
     * @return $this
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->setTemplate('sales/order/massaction.phtml');
        $this->getMassactionBlock()->addItem(
            'bon_preparation', array(
                'label'   => Mage::helper('admindrive')->__('Imprimer le Bon de Préparation'),
                'url'     => $this->getUrl('admindrive/adminhtml_commandes/print'),
                'onclick' => "openOrderFormPopup(this,'" . $this->getUrl('admindrive/adminhtml_commandes/print') . "');"
            )
        );

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/cancel')) {
            $this->getMassactionBlock()->addItem(
                'cancel_order', array(
                    'label' => Mage::helper('sales')->__('Cancel'),
                    'url'   => $this->getUrl('*/sales_order/massCancel'),
                )
            );
        }

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/hold')) {
            $this->getMassactionBlock()->addItem(
                'hold_order', array(
                    'label' => Mage::helper('sales')->__('Hold'),
                    'url'   => $this->getUrl('*/sales_order/massHold'),
                )
            );
        }

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/unhold')) {
            $this->getMassactionBlock()->addItem(
                'unhold_order', array(
                    'label' => Mage::helper('sales')->__('Unhold'),
                    'url'   => $this->getUrl('*/sales_order/massUnhold'),
                )
            );
        }

        $this->getMassactionBlock()->addItem(
            'pdfinvoices_order', array(
                'label' => Mage::helper('sales')->__('Print Invoices'),
                'url'   => $this->getUrl('*/sales_order/pdfinvoices'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'pdfshipments_order', array(
                'label' => Mage::helper('sales')->__('Print Packingslips'),
                'url'   => $this->getUrl('*/sales_order/pdfshipments'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'pdfcreditmemos_order', array(
                'label' => Mage::helper('sales')->__('Print Credit Memos'),
                'url'   => $this->getUrl('*/sales_order/pdfcreditmemos'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'pdfdocs_order', array(
                'label' => Mage::helper('sales')->__('Print All'),
                'url'   => $this->getUrl('*/sales_order/pdfdocs'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'print_shipping_label', array(
                'label' => Mage::helper('sales')->__('Print Shipping Labels'),
                'url'   => $this->getUrl('*/sales_order_shipment/massPrintShippingLabel'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'sent_sell_secure', array(
                'label' => Mage::helper('magoffice_sellsecure')->__('Send to Sell Secure'),
                'url'   => $this->getUrl('magoffice_sellsecure/adminhtml_order/sendEvaluation'),
            )
        );

        return $this;
    }

    /**
     * Function getRowUrl
     *
     * @param $row
     *
     * @return bool|string
     */
    public function getRowUrl($row)
    {
        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            return $this->getUrl('*/sales_order/view', array('order_id' => $row->getId()));
        }

        return false;
    }

    /**
     * Function getGridUrl
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}

<?php
class Lightspeed_Syncproducts_MonitorController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        $offset = $this->getRequest()->getParam('offset', 0);
        if(!isset($offset)) {
            $offset = 0;
        }
        $status = $this->getRequest()->getParam('status', null);
        if(!isset($status) || $status == '' || $status == '-1') {
            $status = null;
        }
        Mage::register('offset', $offset);
        Mage::register('status', $status);
        $this->loadLayout();
        $this->renderLayout();
    }

    public function checkAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function reorderAction() {
        $orderId = $this->getRequest()->getParam("order_id", -1);
        if($orderId >= 0) {
            $newOrderId = Mage::helper('lightspeed_syncproducts/import')->reorder($orderId);
            $this->_redirect('adminhtml/sales_order/view/order_id/'.$newOrderId);
        } else {
            $this->_redirect('*/*/index');
        }
    }
}
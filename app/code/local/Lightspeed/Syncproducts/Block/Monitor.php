<?php
class Lightspeed_Syncproducts_Block_Monitor extends Mage_Adminhtml_Block_Template {

    private function log($message){
        Mage::log($message, null, "lightspeed.log");
    }

    public function __construct() {
        $this->statuses = array(
            'ALL' => '-1',
            'PROCESSED' => 'PROCESSED',
            'DENIED' => 'DENIED',
            'ACCEPTED' => 'ACCEPTED',
        );
    }


    public function getOrders() {
        $this->offset = Mage::registry('offset');
        $this->status = Mage::registry('status');
        $this->nextPage = false;
        $this->previousPage = false;
        $this->log('Status in block = '.$this->status);
        $orders = Mage::helper('lightspeed_syncproducts/api')->getOrders($this->status, $this->offset);

        foreach($orders as $order){
            $magentoOrder = $this->getMagentoOrder($order->id);
            $totalPrice = 0;
            foreach($order->orderTaxInfo as $orderTaxInfo){
                $totalPrice += $orderTaxInfo->totalWithTax;
            }
            $order->totalPrice = Mage::helper('core')->currency($totalPrice, true, false);
            if(isset($magentoOrder)){
                $order->customerName = $this->getCustomerName($magentoOrder->getCustomerId());
                $order->magentoId = $magentoOrder->getId();
                $order->magentoIncId = $magentoOrder->getIncrementId();
                $order->magentoLink = $this->getOrderUrl($magentoOrder->getId());
                $order->totalPrice =  Mage::helper('core')->currency($magentoOrder->getGrandTotal(), true, false);
            }
        }
        if($this->offset > 0) {
            $this->previousPage = true;
        }
        if(count($orders) >= 15) {
            $this->nextPage = true;
        }
        return $orders;
    }

    private function getMagentoOrder($lightspeedId) {
        $orders = Mage::getModel('sales/order')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('posiosId', array('eq'=>$lightspeedId));
        $ret = array();
        foreach($orders as $order){
            $ret[] = $order;
        }
        if(count($ret) > 0){
            $this->log('Found a magento order...');
            return $ret[0];
        } else {
            return null;
        }
    }

    private function getCustomerName($id) {
        $customer = Mage::getModel('customer/customer')->load($id);
        if($customer->getId()){
            return $customer->getFirstname() . ' ' . $customer->getLastname();
        } else {
            return "Customer deleted";
        }
    }

    public function getConfigUrl() {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/system_config/edit/section/lightspeed_settings');
    }

    public function getOrderUrl($id) {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/sales_order/view/order_id/'.$id);
    }

    public function getreOrderUrl($id) {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/monitor/reorder/order_id/'.$id);
    }

    public function getNextPage() {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/monitor/index/offset/'.($this->offset + 15));
    }

    public function getPreviousPage() {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/monitor/index/offset/'.($this->offset - 15));
    }
}
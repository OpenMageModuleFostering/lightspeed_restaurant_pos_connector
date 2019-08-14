<?php
class Lightspeed_Syncproducts_Model_Order_Observer {

    public function __construct()
    {
    }

    private function log($message){
        Mage::log($message, null, "lightspeed.log");
    }

    public function syncOrder($magentoOrder){
        $this->log('Start order syncing');
        $order = array();
        $order["id"] = 0;
        $order["description"] = "auto-generated order by magento-plugin for invoice ";

        $useEstablishment = false;
        $establishmentField = Mage::getStoreConfig('lightspeed_settings/lightspeed_establishments/lightspeed_establishment_field');
        if(!isset($establishmentField) || $establishmentField == '0'){
            $establishmentId = null;
        } else {
            $establishmentId = $magentoOrder->getData($establishmentField);
            if(isset($establishmentId)){
                $establishmentId = (int)$establishmentId;
                $order['companyId'] = (int)$establishmentId;
                $useEstablishment = true;
            }
        }

        $this->log('Using establishments? ' . ($useEstablishment ? 'Yes' : 'No'));

        $customer= mage::getModel('customer/customer')->load($magentoOrder->getCustomerId());
        $order["customerId"] = $this->getCustomerId($customer, $establishmentId, $magentoOrder);
        $this->log('Lightspeed customer id: ' .$order['customerId']);

        $order["deliveryDate"] = $this->getDeliveryTimestamp($magentoOrder);
        $order["type"] = $this->getShippingType($magentoOrder->getShippingMethod(true)->getCarrierCode());
        $orderItems = array();
        $items = Mage::getModel("sales/order_item")->getCollection()->addFieldToFilter("order_id",$magentoOrder->getEntityId());
        $taxes = array();
        $useModifiers = (Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_import_modifiers') == '1');
        foreach($items as $orderItem){
            $item = array();
            $product = Mage::getModel('catalog/product')->load($orderItem->getProductId());
            if(false){
                $this->log('Adding product plu...');
                $item["productPlu"] = $product->getSku();
            } else {
                $this->log('Adding product id...');
                $item["productId"] = (int)$product->getData("posiosId");
            }
            $item["amount"] = (float)$orderItem->getQtyOrdered();
            $item["unitPrice"] = (float)$orderItem->getPriceInclTax();
            $item["totalPrice"] =(float)$orderItem->getRowTotalInclTax();
            $item["unitPriceWithoutVat"] = (float)$orderItem->getPrice();
            $item["totalPriceWithoutVat"] =(float)$orderItem->getRowTotal();

            $taxClass = Mage::getModel('tax/class')->load($product->getTaxClassId());
            $taxClassName = $taxClass->getClassName();
            if(isset($taxes[$taxClassName])){
                $taxes[$taxClassName]["total"] += $orderItem->getRowTotal();
                $taxes[$taxClassName]["totalInclTax"] += $orderItem->getRowTotalInclTax();
            } else {
                $taxes[$taxClassName] = array("total" => (float)$orderItem->getRowTotal(), "totalInclTax" => (float)$orderItem->getRowTotalInclTax());
            }
            $modifiers = array();
            $options = $orderItem->getProductOptions();
            if ($useModifiers && isset($options) && array_key_exists('options', $options)) {
                foreach($options['options'] as $option){
                    $modifiers = $this->addModifier($option, $modifiers);
                }
                $item['modifiers'] = $modifiers;
            }


            $orderItems[] = $item;
        }

        $deliveryCostProduct = array();
        $deliveryProduct = explode('_',Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_delivery_costs'));
        if(count($deliveryProduct) > 1){
            if(false){
                $deliveryCostProduct["productPlu"] = $deliveryProduct[1];
            } else {
                $deliveryCostProduct["productId"] = $deliveryProduct[0];
            }
            $deliveryCostProduct["amount"] = 1;
            $deliveryCostProduct["unitPrice"] = (float)$magentoOrder->getShippingAmount();
            $deliveryCostProduct["unitPriceWithoutVat"] = (float)$magentoOrder->getShippingAmount();
            $deliveryCostProduct["totalPrice"] = (float)$magentoOrder->getShippingAmount();
            $deliveryCostProduct["totalPriceWithoutVat"] = (float)$magentoOrder->getShippingAmount();
            $orderItems[] = $deliveryCostProduct;
        }


        $order["orderItems"] = $orderItems;
        $paymentMethod = $magentoOrder->getPayment()->getMethod();
        if($paymentMethod != "checkmo" && $paymentMethod != "free"){
            $paymentId = Mage::getStoreConfig('lightspeed_settings/lightspeed_payment/lightspeed_payment_'.$paymentMethod);
            $payment = Mage::helper('lightspeed_syncproducts/api')->getPaymentType($paymentId);
            $order["orderPayment"] = array("amount" => (float)$magentoOrder->getGrandTotal(), "paymentTypeId"=>(int)$payment->id, "paymentTypeTypeId"=>(int)$payment->typeId);
        }

        $taxInfo = $magentoOrder->getFullTaxInfo();
        $orderTaxInfo = array();
        foreach($taxInfo as $taxItem){
            $taxClassName = $taxItem["id"];
            $total = $taxes[$taxClassName]["total"];
            $totalInclTax = $taxes[$taxClassName]["totalInclTax"];
            $orderTaxInfo[] = array("tax" => (float)$taxItem["amount"], "taxRate" => $taxItem["percent"], "totalWithoutTax" => $total, "totalWithTax" => $totalInclTax);
        }

        if(count($orderTaxInfo) > 0){
            $order["orderTaxInfo"] = $orderTaxInfo;
        }

        $this->log('Going to create an order for establishment: ' .$establishmentId);
        $posiosId = Mage::helper('lightspeed_syncproducts/api')->createOrder($order, $establishmentId);
        $magentoOrder->setData('posiosId', $posiosId);
        $magentoOrder->save();

        $this->log('Order synced');
    }

    protected function getCustomerId($customer, $establishmentId, $order){
        $this->log('Start syncing user...');
        if(!isset($establishmentId)){
            $lightspeedId = $customer->getData("posiosId");
            if(isset($lightspeedId)){
                return (int)$lightspeedId;
            } else {
                return (int)(Mage::helper('lightspeed_syncproducts/import')->importCustomer($customer, $order->getBillingAddress(), $order->getShippingAddress(), null));
            }
        } else {
            $this->log('Start syncing user... using establishment');
            $this->log('Got establishment id: ' .$establishmentId);
            return (int)(Mage::helper('lightspeed_syncproducts/import')->importCustomer($customer, $order->getBillingAddress(), $order->getShippingAddress(), $establishmentId));
        }

    }


    public function syncOrderAfterPayment($event){
        $invoice = $event->getInvoice();
        $magentoOrder = $invoice->getOrder();

        $paymentMethod = $magentoOrder->getPayment()->getMethod();
        $this->log("Payment placed with: ".$paymentMethod);
        if($paymentMethod != "checkmo"){
            $this->syncOrder($magentoOrder);
        }
    }

    public function syncOrderAfterPlacement ($event){
        $magentoOrder = $event->getOrder();

        $paymentMethod = $magentoOrder->getPayment()->getMethod();
        $this->log("Order placed with: ".$paymentMethod);

        if($paymentMethod == "free" || $paymentMethod == "checkmo"){
            $this->syncOrder($magentoOrder);
        }
    }

    private function getDeliveryTimestamp($order){
        try{
            $mod = Mage::getModel("invoiceogone/deliverytime");
            if($mod){
                $deliveryTimeStamp = $mod->loadDeliverytime($order->getEntityId());
                $this->log('Using bluevision deliveryDate: '. date('c', ((int)$deliveryTimeStamp)/1000));
                return Mage::app()->getLocale()->date(((int)$deliveryTimeStamp)/1000, null, null, false)->toString('c');
            }else{
                return Mage::app()->getLocale()->date(strtotime($order->getCreatedAt()), null, null, false)->toString('c');
            }
        }catch (Exception $ex){
            return Mage::app()->getLocale()->date(strtotime($order->getCreatedAt()), null, null, false)->toString('c');
        }
    }

    private function getShippingType($shippingMethod){
        if(strpos(Mage::getStoreConfig('lightspeed_settings/lightspeed_shipping/lightspeed_shipping_delivery'), $shippingMethod) !== false) {
            return 'delivery';
        } elseif(strpos(Mage::getStoreConfig('lightspeed_settings/lightspeed_shipping/lightspeed_shipping_takeaway'), $shippingMethod) !== false) {
            return 'takeaway';
        } else {
            return 'takeaway';
        }
    }

    private function addModifier($option, $modifiers) {
        //$this->log('Getting option: '.print_r($option, true));
        if($option["option_type"] != 'area'){
            $optionValueIds = explode(',', $option['option_value']);
            $optionValues = explode(',', $option['value']);
            for($index = 0; $index < count($optionValueIds); $index++){
                $optionValue = Mage::getModel('catalog/product_option_value')->load($optionValueIds[$index]);
                //$this->log(print_r($optionValue, true));
                $lightspeedIds = explode('_', $optionValue['sku']);
                $modifiers[] = array('modifierId' => $lightspeedIds[0], 'modifierValueId' => $lightspeedIds[1], 'price' => 0, 'priceWithoutVat' => 0, 'description' => $optionValues[$index], 'modifierName' => $option['label']);
            }
        } else {
            $optionValue = Mage::getModel('catalog/product_option')->load($option['option_id']);
            $lightspeedIds = explode('_', $optionValue['sku']);
            //$this->log('OptionValue: '.print_r($optionValue->getData(),true));
            //$this->log('Option: '.print_r($option,true));
            $modifiers[] = array('modifierId' => $lightspeedIds[0], 'modifierValueId' => -1, 'price' => 0, 'priceWithoutVat' => 0, 'description' => $option['value'], 'modifierName' => $option['label']);
        }
        return $modifiers;
    }
}
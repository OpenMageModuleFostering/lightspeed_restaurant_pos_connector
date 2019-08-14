<?php
class Lightspeed_Syncproducts_Model_Product_Source {
    private function getSyncSession(){
        $session = Mage::getSingleton('adminhtml/session')->getLightspeedProducts();
        if($session == null){
            $session = new Varien_Object();
        }
        return $session;
    }

    private function setSyncSession($session){
        return Mage::getSingleton('adminhtml/session')->getLightspeedProducts($session);
    }

    public function toOptionArray(){
        $ret = $this->getSyncSession()->getProducts();
        $lastUpdate = $this->getSyncSession()->getLastUpdate();

        if(!isset($ret) || (time() - $lastUpdate) > 300){
            $products = Mage::helper('lightspeed_syncproducts/api')->getAllProducts();
            $ret = array(array("value"=>-1, "label" => "-- Please select a product --"));
            if(isset($products) && count($products) > 0){
                foreach($products as $products){
                    $ret[] = array("value" => $products->id.'_'.$products->sku, "label" => $products->name);
                }
                $this->setSyncSession($this->getSyncSession()->setProducts($ret));
                $this->setSyncSession($this->getSyncSession()->setLastUpdate(time()));
            } else {
                $ret = array();
            }
        }
        return $ret;
    }
}
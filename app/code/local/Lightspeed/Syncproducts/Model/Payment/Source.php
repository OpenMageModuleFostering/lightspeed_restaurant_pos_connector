<?php
class Lightspeed_Syncproducts_Model_Payment_Source {
    private function getSyncSession(){
        $session = Mage::getSingleton('adminhtml/session')->getLightspeedPayments();
        if($session == null){
            $session = new Varien_Object();
        }
        return $session;
    }

    private function setSyncSession($session){
        return Mage::getSingleton('adminhtml/session')->setLightspeedPayments($session);
    }

    public function toOptionArray(){
        $ret = $this->getSyncSession()->getPayments();
        $lastUpdate = $this->getSyncSession()->getLastUpdate();

        if(!isset($ret) || (time() - $lastUpdate) > 300){
            $paymentTypes = Mage::helper('lightspeed_syncproducts/api')->getPaymentTypes();
            $ret = array(array("value"=>-1, "label" => "-- Please select a payment type --"));
            if(isset($paymentTypes) && count($paymentTypes) > 0){
                foreach($paymentTypes as $paymentType){
                    $ret[] = array("value" => $paymentType->id, "label" => $paymentType->name);
                }
                $this->setSyncSession($this->getSyncSession()->setPayments($ret));
                $this->setSyncSession($this->getSyncSession()->setLastUpdate(time()));
            } else {
                $ret = array();
            }
        }
        return $ret;
    }
}
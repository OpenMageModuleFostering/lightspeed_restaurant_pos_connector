<?php
class Lightspeed_Syncproducts_Model_Tax_Source {


    public function toOptionArray(){
        $customerTaxClasses = Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_type', 'CUSTOMER')
            ->load();
        $ret = array(array("value"=>-1, "label" => "-- Please select a customer tax class --"));
        if(isset($customerTaxClasses) && count($customerTaxClasses) > 0){
            foreach($customerTaxClasses as $customerTaxClass){
                $ret[] = array("value" => $customerTaxClass->getClassName(), "label" => $customerTaxClass->getClassName());
            }
        } else {
            $ret = array();
        }
        return $ret;
    }
}
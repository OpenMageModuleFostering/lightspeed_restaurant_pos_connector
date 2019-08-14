<?php
class Lightspeed_Syncproducts_Model_Category_Source {
    private function getSyncSession(){
        $session = Mage::getSingleton('adminhtml/session')->getMagentoCategories();
        if($session == null){
            $session = new Varien_Object();
        }
        return $session;
    }

    private function setSyncSession($session){
        return Mage::getSingleton('adminhtml/session')->getMagentoCategories($session);
    }

    public function toOptionArray(){
        $ret = $this->getSyncSession()->getCategories();
        $lastUpdate = $this->getSyncSession()->getLastUpdate();

        if(!isset($ret) || (time() - $lastUpdate) > 300){
            $categories = Mage::getModel('catalog/category')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addIsActiveFilter();
            $ret = array(array("value"=>-1, "label" => "-- Please select a product --"));
            if(isset($categories) && count($categories) > 0){
                foreach($categories as $category){
                    $posiosId = $category->getData('posiosId');
                    if(!isset($posiosId)){
                        $ret[] = array("value" => $category->getId(), "label" => $category->getName());
                    }
                }
                $this->setSyncSession($this->getSyncSession()->setProductGroups($ret));
                $this->setSyncSession($this->getSyncSession()->setLastUpdate(time()));
            } else {
                $ret = array();
            }
        }
        return $ret;
    }
}
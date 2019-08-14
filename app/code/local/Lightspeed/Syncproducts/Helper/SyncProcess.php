<?php
class Lightspeed_Syncproducts_Helper_SyncProcess extends Mage_Core_Helper_Abstract{

    private function log($message) {
        Mage::log($message, null, "lightspeed.log", true);
    }

    private function getSyncSession(){
        $session = Mage::getSingleton('adminhtml/session')->getLightspeedSync();
        if($session == null){
            $session = new Varien_Object();
        }
        return $session;
    }

    private function setSyncSession($session){
        return Mage::getSingleton('adminhtml/session')->setLightspeedSync($session);
    }

    public function getPriceField() {
        return $this->getSyncSession()->getPriceField();
    }

    public function setPriceField($priceField) {
        $this->setSyncSession($this->getSyncSession()->setPriceField($priceField));
    }

    public function getProductGroups() {
        return $this->getSyncSession()->getProductGroups();
    }

    public function getProductGroup($id) {
        $groups = $this->getSyncSession()->getProductGroups();
        return $groups[$id];   
    }

    public function setProductGroups($productGroups)
    {
        $this->setSyncSession($this->getSyncSession()->setProductGroups($productGroups));
    }

    /**
     * An associative array of category ids 
     * Key: Category Id
     * Value: Array of product ids
     */
    public function getProductIds()
    {
        return $this->getSyncSession()->getProductIds();
    }

    public function setProductIds($productIds)
    {
        $this->setSyncSession($this->getSyncSession()->setProductIds($productIds));
    }

    public function resetProducts()
    {
        $this->setSyncSession($this->getSyncSession()->setProducts(array()));
    }

    public function addProduct($product)
    {
        $products = $this->getSyncSession()->getProducts();
        if($products == null){
            $products = array();
        }
        $products[$product->id] = $product;
        $this->setSyncSession($this->getSyncSession()->setProducts($products));
    }

    public function addProducts($products)
    {
    	foreach($products as $product){
            $this->addProduct($product);
        }
    }

    public function getProduct($id) {
        $products = $this->getSyncSession()->getProducts();
        return $products[$id];
    }
}
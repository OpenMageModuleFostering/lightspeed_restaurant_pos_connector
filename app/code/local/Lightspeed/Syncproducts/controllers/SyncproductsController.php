<?php
class Lightspeed_Syncproducts_SyncproductsController extends Mage_Adminhtml_Controller_Action
{

    private function log($message){
        Mage::log($message, null, "lightspeed.log");
    }


    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function checkAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function categoriesAction() {
        $priceField = $this->getRequest()->getPost("priceField", "price");
        Mage::helper('lightspeed_syncproducts/syncProcess')->setPriceField($priceField);
        $this->loadLayout();
        $this->renderLayout();
    }

    public function productsAction() {
        $productGroups = array();
        foreach($this->getRequest()->getPost() as $name => $id){
            if($name != "form_key"){
                $productGroups[] = array("name" => $name, "id" => $id);
            }
        }
        Mage::helper('lightspeed_syncproducts/syncProcess')->setProductGroups($productGroups);
        $this->loadLayout();
        $this->renderLayout();
    }

    public function syncAction() {
        $products = array();
        foreach($this->getRequest()->getPost() as $productId => $categoryId){
            if($productId != "form_key"){
                $categoryId = intval($categoryId);
                if(!isset($products[$categoryId])){
                    $products[$categoryId] = array();
                }
                $products[$categoryId][] = $productId;
            }
        }
        Mage::helper('lightspeed_syncproducts/syncProcess')->setProductIds($products);
        Mage::helper('lightspeed_syncproducts/import')->importTaxClasses();
        Mage::helper('lightspeed_syncproducts/import')->importCategories();
        $this->loadLayout();
        $this->renderLayout();
    }
}
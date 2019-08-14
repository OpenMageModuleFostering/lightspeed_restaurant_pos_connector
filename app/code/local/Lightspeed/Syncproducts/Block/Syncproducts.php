<?php
class Lightspeed_Syncproducts_Block_Syncproducts extends Mage_Adminhtml_Block_Template {

    private function log($message){
        Mage::log($message, null, "lightspeed.log");
    }


    public function checkSync(){
        $errors = array();

        $server = Mage::getStoreConfig('lightspeed_settings/lightspeed_account/lightspeed_server');
        $username = Mage::getStoreConfig('lightspeed_settings/lightspeed_account/lightspeed_username');
        $password = Mage::getStoreConfig('lightspeed_settings/lightspeed_account/lightspeed_password');
        $companyId = Mage::getStoreConfig('lightspeed_settings/lightspeed_account/lightspeed_company');

        if($server == null || strlen($server) == 0){
            $errors[] = 'Hostname';
        }

        if($username == null || strlen($username) == 0){
            $errors[] = 'Username';
        }

        if($password == null || strlen($password) == 0){
            $errors[] = 'Password';
        }

        if($companyId == null || strlen($companyId) == 0){
            $errors[] = 'Company id';
        }

        return $errors;
    }

    public function getProductGroups(){
        return Mage::helper('lightspeed_syncproducts/api')->getProductGroups();
    }

    public function getProducts(){
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        $productGroups = $syncHelper->getProductGroups();
        $ret = array();
        foreach($productGroups as $productGroup){
            $products = $apiHelper->getProducts($productGroup["id"]);
            $syncHelper->addProducts($products);
            $productGroup['products'] = $products;
            $ret[] = $productGroup;
        }
        return $ret;
    }

    public function getConfigUrl() {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/system_config/edit/section/lightspeed_settings');
    }

    public function getLightSpeedUrl($action) {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/syncproducts/'.$action);
    }

    public function getToken(){
        return Mage::helper('lightspeed_syncproducts/api')->getClientApiToken();
    }

    public function formatPrice($price){
        return Mage::helper('core')->currency($price, true, false);
    }

    public function import(){
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $productGroups = $syncHelper->getProductGroups();
    }

    public function getPrice($product, $vatIncl){
        $priceField = Mage::helper('lightspeed_syncproducts/syncProcess')->getPriceField();
        if ($priceField == "normal") {
            $priceField = "price";
        } else {
            $priceField .= "Price";
        }
        if ($vatIncl) {
            return $product->{$priceField};
        } else {
            $priceField .= "WithoutVat";
            return $product->{$priceField};
        }
    }

    public function parseName($name){
        $name = str_replace(' ', '@', $name);
        $name = str_replace('.', '!', $name);
        return $name;
    }

    public function decodeName($name){
        $name = str_replace('@', ' ', $name);
        $name = str_replace('!', '.', $name);
        return $name;
    }
}
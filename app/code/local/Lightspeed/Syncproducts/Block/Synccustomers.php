<?php
class Lightspeed_Syncproducts_Block_Synccustomers extends Mage_Adminhtml_Block_Template {

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

    public function getConfigUrl() {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/system_config/edit/section/lightspeed_settings');
    }

    public function getLightSpeedUrl($action) {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/synccustomers/'.$action);
    }
}
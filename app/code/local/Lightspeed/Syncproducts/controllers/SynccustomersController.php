<?php
class Lightspeed_Syncproducts_SynccustomersController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->loadLayout()->_setActiveMenu('lightspeed');
        $this->renderLayout();
    }

    public function checkAction() {
        $this->loadLayout()->_setActiveMenu('lightspeed');
        $this->renderLayout();
    }

    public function syncAction() {
        $syncField = $this->getRequest()->getPost('syncField');
        Mage::helper('lightspeed_syncproducts/import')->syncCustomers($syncField);
        $this->loadLayout()->_setActiveMenu('lightspeed');
        $this->renderLayout();
    }
}
<?php

class Lightspeed_Syncproducts_Model_Log_Resource_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {

    protected function _construct()
    {
        $this->_init('lightspeed/log');
    }
}
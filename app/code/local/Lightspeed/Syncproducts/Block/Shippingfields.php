<?php
class Lightspeed_Syncproducts_Block_Shippingfields extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

    protected $_dummyElement;
    protected $_fieldRenderer;
    protected $_values;

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $html.= $this->_getFieldHtml($element, 'delivery', 'Delivery');
        $html.= $this->_getFieldHtml($element, 'takeaway', 'Take-Away');
        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    //this creates a dummy element so you can say if your config fields are available on default and website level - you can skip this and add the scope for each element in _getFieldHtml method
    protected function _getDummyElement()
    {
        if (empty($this->_dummyElement)) {
            $this->_dummyElement = new Varien_Object(array('show_in_default'=>1, 'show_in_website'=>1));
        }
        return $this->_dummyElement;
    }

    //this sets the fields renderer. If you have a custom renderer tou can change this.
    protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }
        return $this->_fieldRenderer;
    }

    protected function _getValues()
    {
        if (empty($this->_values)) {
            $methods = Mage::getSingleton('shipping/config')->getActiveCarriers();
            $options = array();
            foreach($methods as $_code => $_method)
            {
                if(!$_title = Mage::getStoreConfig("carriers/$_code/title")){
                    $_title = $_code;
                }

                $options[] = array('value' => $_code, 'label' => $_title . " ($_code)");
            }
            $this->_values = $options;
        }
        return $this->_values;
    }

    //this actually gets the html for a field
    protected function _getFieldHtml($fieldset, $shippingMethod, $shippingTitle)
    {
        $configData = $this->getConfigData();
        $path = 'lightspeed_settings/lightspeed_shipping/lightspeed_shipping_'.$shippingMethod;
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int)(string)$this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }

        $e = $this->_getDummyElement();//get the dummy element

        $field = $fieldset->addField($shippingMethod, 'multiselect',//this is the type of the element (can be text, textarea, select, multiselect, ...)
            array(
                'name'          => 'groups[lightspeed_shipping][fields][lightspeed_shipping_'.$shippingMethod.'][value]',//this is groups[group name][fields][field name][value]
                'label'         => $shippingTitle,//this is the label of the element
                'value'         => $data,//this is the current value
                'values'        => $this->_getValues(),//this is necessary if the type is select or multiselect
                'inherit'       => $inherit,
                'can_use_default_value' => $this->getForm()->canUseDefaultValue($e),//sets if it can be changed on the default level
                'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e),//sets if can be changed on website level
            ))->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }

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
}
<?php
class Lightspeed_Syncproducts_Block_Establishmentfields extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

    protected $_dummyElement;
    protected $_fieldRenderer;
    protected $_values;

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $establishments = Mage::helper('lightspeed_syncproducts/api')->getEstablishments();

        if(count($establishments) > 1) {
            for ($index = 1; $index <= count($establishments); $index++) {
                $html.= $this->_getFieldHtml($element, $index);
            }
            $html.= $this->_getEstablishmentMappingField($element);
        } else {
            $html .= "<p>You don't have any establishments connected to this account.</p>";
        }



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
            $establishmentValues = $this->getSyncSession()->getEstablishments();
            $lastUpdate = $this->getSyncSession()->getLastUpdate();

            if(!isset($establishmentValues) || (time() - $lastUpdate) > 300){
                $establishments = Mage::helper('lightspeed_syncproducts/api')->getEstablishments();
                $establishmentValues = array(array("value"=>-1, "label" => "-- Please select an establishment --"));
                $currentUser = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_username");
                $currentCompany = (int)Mage::getStoreConfig('lightspeed_settings/lightspeed_account/lightspeed_company');
                $establishmentValues[] = array("value" => $currentCompany, "label" => $currentUser);
                if(isset($establishments) && count($establishments) > 0){
                    foreach($establishments as $establishment){
                        $establishmentValues[] = array("value" => $establishment->id, "label" => $establishment->name);
                    }
                    $this->setSyncSession($this->getSyncSession()->setPayments($establishmentValues));
                    $this->setSyncSession($this->getSyncSession()->setLastUpdate(time()));
                } else {
                    $establishmentValues = array();
                }
            }
            $this->_values = $establishmentValues;
        }
        return $this->_values;
    }

    //this actually gets the html for a field
    protected function _getFieldHtml($fieldset, $index)
    {
        $configData = $this->getConfigData();
        $path = 'lightspeed_settings/lightspeed_establishments/lightspeed_establishment_'.$index;
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int)(string)$this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }

        $e = $this->_getDummyElement();//get the dummy element

        $field = $fieldset->addField($index, 'select',//this is the type of the element (can be text, textarea, select, multiselect, ...)
            array(
                'name'          => 'groups[lightspeed_establishments][fields][lightspeed_establishment_'.$index.'][value]',//this is groups[group name][fields][field name][value]
                'label'         => 'Establishment '.$index,//this is the label of the element
                'value'         => $data,//this is the current value
                'values'        => $this->_getValues(),//this is necessary if the type is select or multiselect
                'inherit'       => $inherit,
                'can_use_default_value' => $this->getForm()->canUseDefaultValue($e),//sets if it can be changed on the default level
                'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e),//sets if can be changed on website level
            ))->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }

    protected function _getEstablishmentMappingField($fieldset){
        $configData = $this->getConfigData();
        $path = 'lightspeed_settings/lightspeed_establishments/lightspeed_establishment_field';
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int)(string)$this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }

        $e = $this->_getDummyElement();//get the dummy element

        $field = $fieldset->addField('field', 'text',//this is the type of the element (can be text, textarea, select, multiselect, ...)
            array(
                'name'          => 'groups[lightspeed_establishments][fields][lightspeed_establishment_field][value]',//this is groups[group name][fields][field name][value]
                'label'         => 'Establishment Field Mapping',//this is the label of the element
                'value'         => $data,//this is the current value
                'inherit'       => $inherit,
                'can_use_default_value' => $this->getForm()->canUseDefaultValue($e),//sets if it can be changed on the default level
                'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e),//sets if can be changed on website level
            ))->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }

    private function getSyncSession(){
        $session = Mage::getSingleton('adminhtml/session')->getLightspeedEstablishments();
        if($session == null){
            $session = new Varien_Object();
        }
        return $session;
    }

    private function setSyncSession($session){
        return Mage::getSingleton('adminhtml/session')->setLightspeedEstablishments($session);
    }
}
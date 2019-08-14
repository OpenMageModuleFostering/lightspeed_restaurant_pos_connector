<?php
class Lightspeed_Syncproducts_Helper_Import extends Mage_Core_Helper_Abstract{

    private function log($message){
        Mage::log($message, null, "lightspeed.log");
    }

    private function logOptions($message){
        Mage::log($message, null, "lightspeedOptions.log");
    }

    public function importTaxClasses(){
        $customerName = Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_customer_tax');
        $country = Mage::getStoreConfig('general/country/default');

        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        $taxClasses = $apiHelper->getTaxClasses();

        //Check to see if lightspeed customer class already exists
        $lightSpeedCustomerClass = Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_name', $customerName)
            ->load()
            ->getFirstItem();

        if(!$lightSpeedCustomerClass->getId()){
            $lightSpeedCustomerClass = Mage::getModel('tax/class')
                ->setData(
                    array(
                        'class_name' => $customerName,
                        'class_type' => 'CUSTOMER'
                    )
                )
                ->save();
        }

        foreach($taxClasses as $taxClass){
            $taxRate = Mage::getModel('tax/calculation_rate')
                ->getCollection()
                ->addFieldToFilter('code', $taxClass->id)
                ->load()
                ->getFirstItem();

            if(!$taxRate->getId()){
                $taxRate = Mage::getModel('tax/calculation_rate')
                    ->setData(
                        array(
                            'code' => $taxClass->id,
                            'rate' => $taxClass->taxRate,
                            'tax_country_id' => $country,
                            "tax_region_id" => "0",
                            "zip_is_range"  => "0",
                            "tax_postcode"  => "*",
                        )
                    )
                    ->save();

                $productClass = Mage::getModel('tax/class')
                    ->setData(
                        array(
                            'class_name' => $taxClass->id,
                            'class_type' => 'PRODUCT'
                        )
                    )
                    ->save();

                $taxRule = Mage::getModel('tax/calculation_rule')
                    ->setData(array(
                        "code"                  => $taxClass->id,
                        "tax_customer_class"    => array($lightSpeedCustomerClass->getId()),
                        "tax_product_class"     => array($productClass->getId()),
                        "tax_rate"              => array($taxRate->getId()),
                        "priority"              => "0",
                        "position"              => "0",
                    ))->save();
            } else {
                $taxRate
                    ->setData('rate', $taxClass->taxRate)
                    ->save();
            }
        }
    }

    public function importCategories(){
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $this->log('Start importing categories');
        $categories = $syncHelper->getProductGroups();
        $parentCategoryId = Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_parent_category');
        if(!isset($parentCategoryId) || ((int)$parentCategoryId) == -1){
            $parentCategoryId = 2;
        }
        $parentCategory = Mage::getModel('catalog/category')->load((int)$parentCategoryId);
        foreach($categories as $category){
            $magentoCategory = null;
            $this->log('Start importing category '.$category["name"]);
            $magentoCategories = $this->getMagentoCategory($category["id"], $category["name"]);
            if(isset($magentoCategories) && count($magentoCategories) == 1){
                $magentoCategory = $magentoCategories[0];
                $magentoCategory->setPath($parentCategory->getPath().'/'.$magentoCategory->getId());
                $this->log('Found magento category');
            } else if(isset($magentoCategories) && count($magentoCategories) > 0){
                foreach($magentoCategories as $c){
                    $magentoCategory = $c;
                    if($c->posiosId == $category["id"]){
                        break;
                    }
                }
                $magentoCategory->setPath($parentCategory->getPath());
                $magentoCategory->setPath($parentCategory->getPath().'/'.$magentoCategory->getId());
                $this->log('Found magento category');
            }
            else{
                $this->log('Did not find magento category');

                $magentoCategory = Mage::getModel('catalog/category');
                $magentoCategory->setPath($parentCategory->getPath());
            }

            $magentoCategory->setName($this->decodeName($category["name"]))
                ->setIsActive(1)                       //activate your category
                ->setDisplayMode('PRODUCTS')
                ->setIsAnchor(1)
                ->setCustomDesignApply(1);
            $this->log('Adding posios id');
            $magentoCategory->setData("posiosId", $category["id"]);
            $magentoCategory->save();
            $this->log('Magento category saved');
            $this->importProducts(intval($category["id"]), intval($magentoCategory->getId()));
        }
    }

    public function importProducts($categoryId, $magentoCategoryId){
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        if(array_key_exists($categoryId, $syncHelper->getProductIds())){
            $allProducts = $syncHelper->getProductIds();
            $productIds = $allProducts[$categoryId];
            $taxClasses = array();
            $mediaApi = Mage::getModel("catalog/product_attribute_media_api");
            $taxInclusivePrices = (Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_use_tax_inclusive') == '1');
            $useModifiers = (Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_import_modifiers') == '1');
            foreach($productIds as $productId){
                $product = $syncHelper->getProduct($productId);

                $taxClassId = null;
                if(!isset($product->taxClass)){
                    $product->taxClass = "BUILTIN-21.00";
                }
                if(!array_key_exists($product->taxClass, $taxClasses)){
                    $taxClassId = Mage::getModel('tax/class')
                        ->getCollection()
                        ->addFieldToFilter('class_name', $product->taxClass)
                        ->load()
                        ->getFirstItem()
                        ->getId();
                    $taxClasses[$product->taxClass] = $taxClassId;
                } else {
                    $taxClassId = $taxClasses[$product->taxClass];
                }

                $magentoProducts = $this->getMagentoProduct($productId);
                $magentoProduct = null;
                if(isset($magentoProducts) && count($magentoProducts) > 0){
                    $magentoProduct = Mage::getModel('catalog/product')->load($magentoProducts[0]->getId());
                } else if(isset($magentoProducts) && count($magentoProducts) > 1){
                    foreach($magentoProducts as $p){
                        $magentoProduct = Mage::getModel('catalog/product')->load($p->getId());
                        if($p->posiosId == $productId){
                            break;
                        }
                    }
                } else {
                    $magentoProduct = Mage::getModel('catalog/product');
                    $magentoProduct->setVisibility(4);
                    $magentoProduct->setStatus(1);
                    $magentoProduct->setWebsiteIds(array(1));
                }
                $stockData = array(
                    'manage_stock' => 0
                );
                $magentoProduct->setStockData($stockData);
                $magentoProduct->setAttributeSetId($magentoProduct->getDefaultAttributeSetId());
                $magentoProduct->setSku($product->sku);
                $magentoProduct->setTypeId('simple');
                $magentoProduct->setName($product->name);
                $magentoProduct->setDescription($product->info);
                $magentoProduct->setShortDescription($product->info);
                $magentoProduct->setCategoryIds(array($magentoCategoryId));
                $magentoProduct->setPrice($this->getPrice($product, $taxInclusivePrices));
                $magentoProduct->setWeight(0);
                $magentoProduct->setTaxClassId($taxClassId);
                $magentoProduct->setCreatedAt(strtotime('now'));
                $magentoProduct->setData("posiosId", $productId);

                if($product->imageLocation != ""){
                    $magentoProduct->getOptionInstance()->unsetOptions();
                    $this->deleteImages($magentoProduct, $mediaApi);
                    $destinationName = "imgprod_" . $productId ."_".strtotime('now').".".pathinfo($product->imageLocation, PATHINFO_EXTENSION);;
                    $imgImportDir = Mage::getBaseDir('media') . DS . 'import';
                    if (!file_exists($imgImportDir)) {
                        mkdir($imgImportDir, 0755, true);
                    }
                    $destinationPath = $imgImportDir.DS.$destinationName;
                    if(!file_exists($destinationPath)){
                        $imageLocation = $product->imageLocation;
                        $this->downloadFile($imageLocation, $destinationPath);
                    }

                    $magentoProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
                    $magentoProduct->addImageToMediaGallery ($destinationPath, array ('image','small_image','thumbnail'), false, false);
                }

                if($useModifiers && isset($product->additions) && count($product->additions) > 0){
                    $this->addModifications($magentoProduct, $product, $taxInclusivePrices);
                }

                try {
                    $magentoProduct->save();
                } catch (Exception $e) {
                    $magentoProduct->getResource()->save($magentoProduct);
                }
            }
        }
    }

    private function getPrice($product, $vatIncl){
        $priceField = Mage::helper('lightspeed_syncproducts/syncProcess')->getPriceField();
        if($vatIncl){
            return $product->{$priceField};
        } else {
            $priceField .= "WithoutVat";
            return $product->{$priceField};
        }
    }

    private function getMagentoProduct($id){
        $products = $categories = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter(array(array('attribute'=>'posiosId','eq'=>$id)));

        $ret = array();

        foreach($products as $product){
            $ret[] = $product;
        }
        return $ret;
    }


    private function getMagentoCategory($posiosId, $name){

        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addIsActiveFilter()
            ->addFieldToFilter(array(array('attribute'=>'posiosId','eq'=>$posiosId), array('attribute'=>'name','eq'=>$name)));

        $ret = array();

        foreach($categories as $category){
            $ret[] = $category;
        }

        return $ret;
    }

    public function syncCustomers($syncField){
        $magentoCustomers = $this->getMagentoCustomers($syncField);
        foreach($magentoCustomers as $magentoCustomer){
            $this->importCustomer($magentoCustomer, null, null, null);
        }
    }

    public function importCustomer($magentoCustomer, $billingAddress, $shippingAddress, $establishmentId){
        $customer = array();

        if(!isset($billingAddress)){
            $billingAddress = Mage::getModel('customer/address')->load($magentoCustomer->getData("default_billing"));
        }

        if(!isset($shippingAddress)){
            $shippingAddress = Mage::getModel('customer/address')->load($magentoCustomer->getData("default_shipping"));
        }

        if(!isset($billingAddress)){
            $billingAddress = $shippingAddress;
        }

        if(!isset($shippingAddress)){
            $shippingAddress = $billingAddress;
        }


        $customer["firstName"] = $magentoCustomer->getData("firstname");
        $customer["lastName"] = $magentoCustomer->getData("lastname");
        $customer["email"] = $magentoCustomer->getData("email");

        $street = $this->parseMagentoStreet($billingAddress->getStreetFull());
        $customer["street"] = $street[0];
        $customer["streetNumber"] = $street[1];
        $customer["zip"] = $billingAddress->getData('postcode');
        $customer["city"] = $billingAddress->getData('city');
        $customer["country"] = $billingAddress->getCountry();

        $street2 = $this->parseMagentoStreet($shippingAddress->getStreetFull());
        $customer["deliveryStreet"] = $street2[0];
        $customer["deliveryStreetNumber"] = $street2[1];
        $customer["deliveryZip"] = $shippingAddress->getData('postcode');
        $customer["deliveryCity"] = $shippingAddress->getData('city');
        $customer["deliveryCountry"] = $shippingAddress->getCountry();

        $posiosId = $magentoCustomer->getData('posiosId');
        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        if(isset($posiosId)){
            if(isset($establishmentId)){
                $apiHelper->saveCustomer($customer, $posiosId, null);
                $establishmentCustomer= $apiHelper->getCustomer($posiosId, $establishmentId);
                $apiHelper->saveCustomer($customer, $establishmentCustomer->id, $establishmentId);
                $posiosId = $establishmentCustomer->id;
            } else {
                $customer["id"] = (int)$posiosId;
                $apiHelper->saveCustomer($customer, $posiosId, null);
            }
        } else {
            $posiosId = $apiHelper->createCustomer($customer, null);
            $magentoCustomer->setData('posiosId', (int)$posiosId);
            $magentoCustomer->getResource()->saveAttribute($magentoCustomer,'posiosId');
            if(isset($establishmentId)){
                $establishmentCustomer= $apiHelper->getCustomer($posiosId, $establishmentId);
                $posiosId = $establishmentCustomer->id;
            }
        }
        return $posiosId;
    }

    private function getCustomerIdForEstablishment($attributeValue, $establishmentId){
        $this->log('Parsing establishment posios id...');
        if(strpos($attributeValue, (String)$establishmentId) !== false){
            $this->log('Establishment id found...');
            preg_match('/'.$establishmentId.'_(.*?);/', $attributeValue, $matches);
            if(count($matches) > 0){
                return $matches[1];
            } else {
                return false;
            }
        } else {
            $this->log('Establishment id not found...');
            return false;
        }

    }

    private function downloadFile($url, $fullPath){
        $fp = fopen($fullPath, 'w');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies');
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies');
        $data = curl_exec($ch);

        if(fwrite($fp,$data))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    private function parseMagentoStreet($street){
        $this->log('Parse street '.print_r($street, true));
        $arr = is_array($street) ? $street : explode("\n", $street);
        $street = implode(" ", $arr);

        $result = array();
        $lastSpacePosition = strrpos($street, " ");
        if($lastSpacePosition > 0){
            $number = substr($street, $lastSpacePosition+1);
            $street = substr($street, 0, $lastSpacePosition);
        } else {
            $number = $street;
            $street = '?';
        }

        $result[0] = $street;
        $result[1] = $number;

        if(!isset($result[0])){
            $result[0] = "";
        }

        if(!isset($result[1])){
            $result[1] = "";
        }

        $this->log('Parsed street '.print_r($result, true));
        return $result;
    }

    private function getMagentoCustomers($syncField){
        $users = mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('*');
        if('existing' == $syncField){
            $users->addFieldToFilter(array(array('attribute'=>'posiosId','neq'=>'NULL')));
        } else if('new' == $syncField){
            $users->addAttributeToFilter('posiosId', array('null' => true), 'left');
        }
        $ret = array();
        foreach($users as $user){
            $ret[] = $user;
        }
        return $ret;
    }

    private function decodeName($name){
        $name = str_replace('@', ' ', $name);
        $name = str_replace('!', '.', $name);
        return $name;
    }

    private function addModifications($magentoProduct, $lightspeedProduct, $taxInclusivePrices){
        $store = Mage::app()->getStore('default');
        $taxCalculation = Mage::getModel('tax/calculation');
        $request = $taxCalculation->getRateRequest(null, null, null, $store);
        $taxClassId = $magentoProduct->getTaxClassId();
        $percent = $taxCalculation->getRate($request->setProductClassId($taxClassId));

        $magentoProduct->setHasOptions(1);
        $optionInstance = $magentoProduct->getOptionInstance();
        $optionInstance = $optionInstance->setProduct($magentoProduct);
        $oldOptions = $magentoProduct->getOptions();
        $optionsToDelete = null;
        if (count($oldOptions) > 0) {
            $optionsToDelete = array();
            foreach($oldOptions as $option){
                $optionsData = $option->getData();
                $optionsData['is_delete'] = '1';
                $optionsToDelete[] = $optionsData;
            }
            $optionInstance->setOptions($optionsToDelete)->saveOptions();
        }

        $optionInstance = $magentoProduct->getOptionInstance()->unsetOptions();

        $index  = 0;
        $sku = $lightspeedProduct->sku;
        foreach($lightspeedProduct->additions as $addition){
            $index2 = 0;
            $option = array(
                'title' => $addition->name,
                'type' => $this->getOptionType($addition),
                'is_require' => $this->isRequired($addition),
                'sort_order' => $index,
                'sku' => $addition->id.'_'.$sku,
                'values' => array()
            );
            if(count($addition->values) == 0){
                $option['values'][] = array(
                    'price' => 0,
                    'price_type' => 'fixed',
                    'sku' => $addition->id.'_note'.'_'.$sku,
                    'sort_order' => $index2,
                    'sku' => $addition->id
                );
            } else {
                foreach($addition->values as $additionValue){
                    $price = $additionValue->price;
                    if(!$taxInclusivePrices){
                        $price -= $taxCalculation->calcTaxAmount($additionValue->price, $percent, true, false);
                    }
                    $option['values'][] = array(
                        'title' => $additionValue->name,
                        'price' => $price,
                        'price_type' => 'fixed',
                        'sku' => $addition->id.'_'.$additionValue->id.'_'.$sku,
                        'sort_order' => $index2
                    );
                    $index2++;
                }
            }
            $index++;
            $optionInstance->addOption($option);
        }
        $magentoProduct->setOptionInstance($optionInstance);
    }

    private function getOptionType($option){
        if(!isset($option->values) || count($option->values) == 0){
            return 'area';
        } elseif($option->multiselect){
            return 'multiple';
        } else {
            return 'drop_down';
        }
    }

    private function isRequired($option){
        if(!isset($option->values) || count($option->values) == 0){
            return false;
        }else if(!$option->multiselect){
            return true;
        } else {
            return $option->minSelectedAmount > 0;
        }
    }

    private function  createSubProducts($lightspeedProduct, $taxClasses, $magentoCategoryId){
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        $subProducts = $apiHelper->getSubProducts($lightspeedProduct->id);

        $ret = array();

        foreach ($subProducts as $subProduct) {
            $sub = $syncHelper->getProduct($subProduct->id);
            if (!$sub) {
                $sub = $apiHelper->getProduct($subProduct->id);
            }
            if (array_key_exists($magentoCategoryId, $sub->groupIds)) {
                $subCategoryId = $magentoCategoryId;
            } else {
                $lightspeedCategory = $syncHelper->getProductGroup($sub->groupIds[0]);
                if (!$lightspeedCategory) {
                    $lightspeedCategory = $apiHelper->getProductGroup($sub->groupIds[0]);
                    $subCategory = $this->importCategory(array('id' => $lightspeedCategory->id, 'name' => $lightspeedCategory->name));
                } else {
                    $subCategory = $this->importCategory($lightspeedCategory);
                }
                $subCategoryId = $subCategory->getId();
            }
            $magentoSubProduct = $this->createProduct($sub, $this->getTaxClassId($sub, $taxClasses), $subCategoryId);
            $ret[] = array('magento' => $magentoSubProduct, 'lightspeed' => $sub);
        }
        return $ret;
    }

    private function deleteImages ($magentoProduct, $mediaApi){
        if($magentoProduct->getId()){
            $items = $mediaApi->items($magentoProduct->getId());
            foreach($items as $item) {
                $mediaApi->remove($magentoProduct->getId(), $item['file']);
            }
        }

    }

    public function reorder($orderId) {
        Mage::unregister('rule_data');
        Mage::getSingleton('adminhtml/session_quote')->clear();

        $order = Mage::getModel('sales/order')->load($orderId);
        $incId = $order->getIncrementId();

        $newQuote = new Mage_Sales_Model_Quote();
        $newQuote->setStoreId($order->getStoreId());
        Mage::getSingleton('adminhtml/sales_order_create')->setQuote($newQuote);

        $order_model = Mage::getSingleton('adminhtml/sales_order_create');
        $order_model->getSession()->clear();

        try {
            $order->setReordered(true);
            Mage::getSingleton('adminhtml/session_quote')->setUseOldShippingMethod(true);

            $reorder = new Varien_Object();
            $reorder = $order_model->initFromOrder($order);
            $newOrder = $reorder->createOrder();

            $reOrderId = $newOrder->getId();
            return $reOrderId;

        } catch (Exception $e) {
            Mage::log("Order #{$incId} Reorder Error : {$e->getMessage()}",null,"reorder.log");
        }
    }






}

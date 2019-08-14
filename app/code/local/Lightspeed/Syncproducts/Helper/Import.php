<?php

class Lightspeed_Syncproducts_Helper_Import extends Mage_Core_Helper_Abstract {
    private function log($message) {
        Mage::log($message, null, "lightspeed.log", true);
    }

    private function logOptions($message) {
        Mage::log($message, null, "lightspeedOptions.log", true);
    }

    public function importTaxClasses() {
        $customerName = Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_customer_tax');
        $country = Mage::getStoreConfig('general/country/default');

        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        $taxClasses = $apiHelper->getTaxClasses();

        // Check to see if lightspeed customer class already exists
        $lightSpeedCustomerClass = Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_name', $customerName)
            ->load()
            ->getFirstItem();

        if (!$lightSpeedCustomerClass->getId()) {
            $lightSpeedCustomerClass = Mage::getModel('tax/class')
                ->setData(array('class_name' => $customerName, 'class_type' => 'CUSTOMER'))
                ->save();
        }

        foreach ($taxClasses as $taxClass) {
            $taxRate = Mage::getModel('tax/calculation_rate')
                ->getCollection()
                ->addFieldToFilter('code', $taxClass->id)
                ->load()
                ->getFirstItem();

            if (!$taxRate->getId()) {
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

    public function importCategories() {
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $this->log('Start importing categories');
        $categories = $syncHelper->getProductGroups();

        $parentCategory = $this->getParentCategory();
        $parentCategoryPath = $parentCategory->getPath();

        foreach ($categories as $category) {
            $magentoCategory = null;
            $this->log('Start importing category: ' . $category["name"]);
            $magentoCategories = $this->getMagentoCategory($category["id"], $category["name"]);
            if (isset($magentoCategories) && count($magentoCategories) == 1) {
                $magentoCategory = $magentoCategories[0];
                $magentoCategory->setPath($parentCategoryPath . '/' . $magentoCategory->getId());
                $this->log('Found magento category');
            } else if (isset($magentoCategories) && count($magentoCategories) > 0) {
                foreach ($magentoCategories as $c) {
                    $magentoCategory = $c;
                    if ($c->posiosId == $category["id"]) {
                        break;
                    }
                }
                $magentoCategory->setPath($parentCategoryPath);
                $magentoCategory->setPath($parentCategoryPath . '/' . $magentoCategory->getId());
                $this->log('Found magento category');
            } else {
                $this->log('Did not find magento category: ' . $category["name"] . '. ParentCategoryPath: ' . $parentCategoryPath);

                $magentoCategory = Mage::getModel('catalog/category');
                $magentoCategory->setPath($parentCategoryPath);
            }

            $magentoCategory->setName($this->decodeName($category["name"]))
                ->setIsActive(1) // activate your category
                ->setDisplayMode('PRODUCTS')
                ->setIsAnchor(1)
                ->setCustomDesignApply(1);
            $this->log('Adding posios id');
            $magentoCategory->setData("posiosId", $category["id"]);
            $magentoCategory->save();
            $this->importProducts(intval($category["id"]), intval($magentoCategory->getId()));
        }

    }

    public function getParentCategory() {
        $parentCategoryId = Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_parent_category');

        if (!isset($parentCategoryId) || ((int) $parentCategoryId) == -1) {
            $parentCategoryId = 2;
        }

        $parentCategory = Mage::getModel('catalog/category')->load((int) $parentCategoryId);

        return $parentCategory;
    }

    public function importCategory($category) {
        $magentoCategory = null;

        $magentoCategory = Mage::getModel('catalog/category');

        $parentCategory = $this->getParentCategory();
        $magentoCategory->setPath($parentCategory->getPath());

        $magentoCategory->setName($this->decodeName($category["name"]))
            ->setIsActive(1) // activate your category
            ->setDisplayMode('PRODUCTS')
            ->setIsAnchor(1)
            ->setCustomDesignApply(1);

        $this->log('Adding posios id');
        $magentoCategory->setData("posiosId", $category["id"]);

        try {
            $magentoCategory->save();
        } catch (Exception $e) {
            $magentoCategory->getResource()->save($magentoCategory);
        }

        return $magentoCategory;
    }

    public function importProducts($categoryId, $magentoCategoryId) {
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $allProducts = $syncHelper->getProductIds();

        if (array_key_exists($categoryId, $allProducts)) {
            $productIds = $allProducts[$categoryId];

            foreach ($productIds as $productId) {
                $this->createProduct($productId, $magentoCategoryId);
            }
            $this->reindexPrice();
        }
    }

    private function createProduct($productId, $magentoCategoryId) {
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $product =  $syncHelper->getProduct($productId);

        if (!$product) {
            $apiHelper = Mage::helper('lightspeed_syncproducts/api');
            $product = $apiHelper->getProduct($productId);
        }

        $taxInclusivePrices = (Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_use_tax_inclusive') == '1');
        $useModifiers = (Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_import_modifiers') == '1');

        $magentoProduct = $this->createMagentoProduct($product, $magentoCategoryId, $taxInclusivePrices);

        if ($product->productType == "CHOICE") {
            $this->createChoice($magentoProduct, $productId);
            $magentoProduct->setPriceType(0);
        }

        if ($product->productType == "GROUP") {
            $this->createGroup($magentoProduct, $productId);
            $magentoProduct->setPriceType(0);
        }

        if ($product->imageLocation != "") {
            $this->getImageForProduct($product, $magentoProduct, $productId);
        }

        if ($useModifiers && isset($product->additions) && count($product->additions) > 0) {
            $this->addModifications($magentoProduct, $product, $taxInclusivePrices);
        }

        $this->reindexPrice();

        try {
            $magentoProduct->save();
        } catch (Exception $e) {
            $magentoProduct->getResource()->save($magentoProduct);
        }
    }

    private function getTaxClassId($product) {
        $taxClassId = null;
        $taxClasses = array ();
        $taxClass = $this->getTaxClass();

        if (!isset($product->taxClass)) {
            $product->taxClass = "BUILTIN-21.00";
        }
        if (!array_key_exists($product->{$taxClass}, $taxClasses)) {
            $taxClassId = Mage::getModel('tax/class')
                ->getCollection()
                ->addFieldToFilter('class_name', $product->{$taxClass})
                ->load()
                ->getFirstItem()
                ->getId();
            $taxClasses[$product->{$taxClass}] = $taxClassId;
        } else {
            $taxClassId = $taxClasses[$product->{$taxClass}];
        }
        return $taxClassId;
    }

    private function createMagentoProduct($product, $magentoCategoryId, $taxInclusivePrices) {
        $this->log("Create magento product with categoryId: " . $magentoCategoryId);
        $productId = $product->id;
        $taxClassId = $this->getTaxClassId($product);

        $magentoProducts = $this->getMagentoProduct($productId);

        $magentoProduct = null;
        if (isset($magentoProducts) && count($magentoProducts) > 0) {
            $magentoProduct = Mage::getModel('catalog/product')->load($magentoProducts[0]->getId());
        } else {
            $this->log('No magento products found');
            $magentoProduct = Mage::getModel('catalog/product');
            $magentoProduct->setVisibility(4);
            $magentoProduct->setStatus(1);
            $magentoProduct->setWebsiteIds(array(1));
        }

        $stockData = array('manage_stock' => 0);
        $magentoProduct->setStockData($stockData);
        $magentoProduct->setAttributeSetId($magentoProduct->getDefaultAttributeSetId());
        $magentoProduct->setSku($product->sku);
        $magentoProduct->setTypeId('simple');
        $magentoProduct->setName($product->name);
        $magentoProduct->setDescription($product->info);
        $magentoProduct->setShortDescription($product->info);
        $magentoProduct->setCategoryIds(array($magentoCategoryId));

        if ($product->productType != "CHOICE") {
            $magentoProduct->setPrice($this->getPrice($product, $taxInclusivePrices));
            $magentoProduct->setTaxClassId($taxClassId);
        }

        $magentoProduct->setWeight(0);
        $magentoProduct->setCreatedAt(strtotime('now'));
        $magentoProduct->setData("posiosId", $productId);

        return $magentoProduct;
    }

    private function getImageForProduct($product, $magentoProduct, $productId) {
        $mediaApi = Mage::getModel("catalog/product_attribute_media_api");
        $magentoProduct->getOptionInstance()->unsetOptions();
        $this->deleteImages($magentoProduct, $mediaApi);
        $destinationName = "imgprod_" . $productId . "_" . strtotime('now') . "." . pathinfo($product->imageLocation, PATHINFO_EXTENSION);

        $imgImportDir = Mage::getBaseDir('media') . DS . 'import';
        if (!file_exists($imgImportDir)) {
            mkdir($imgImportDir, 0755, true);
        }
        $destinationPath = $imgImportDir . DS . $destinationName;
        if (!file_exists($destinationPath)) {
            $imageLocation = $product->imageLocation;
            $this->downloadFile($imageLocation, $destinationPath);
        }

        $magentoProduct->setMediaGallery(array (
                'images' => array (),
                'values' => array ()
        ));
        $magentoProduct->addImageToMediaGallery($destinationPath, array (
                'image',
                'small_image',
                'thumbnail'
        ), false, false);
    }

    private function getPrice($product, $vatIncl) {
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

    private function getTaxClass() {
        $priceField = Mage::helper('lightspeed_syncproducts/syncProcess')->getPriceField();
        if ($priceField == "normal") {
            return "taxClass";
        }

        return $priceField . "TaxClass";
    }

    private function createChoice($magentoProduct, $productId){
        $subProducts = $this->createSubProducts($productId);
        $bundleInfo = array('name' => 'Options', 'values' => array());

        foreach($subProducts as $subProduct){
            if($subProduct['lightspeed']->productType != 'CHOICE' && $subProduct['lightspeed']->productType != 'GROUP'){
                $bundleInfo['values'][] = $subProduct['magento'];
            }
        }

        $bundle = $this->createBundleOptionsAndSelections(array($bundleInfo));

        $magentoProduct->setTypeId('bundle');

        Mage::unregister('product');
        Mage::register('product', $magentoProduct);

        $magentoProduct->setCanSaveConfigurableAttributes(false);

        $magentoProduct->setCanSaveCustomOptions(true);
        $magentoProduct->setCanSaveBundleSelections(true);
        $magentoProduct->setAffectBundleProductSelections(true);

        $magentoProduct->setBundleOptionsData($bundle['options']);
        $magentoProduct->setBundleSelectionsData($bundle['selections']);

        try {
            $magentoProduct->save();
        } catch (Exception $e) {
            $magentoProduct->getResource()->save($magentoProduct);
        }
    }

    private function reindex() {
        $indexCollection = Mage::getModel('index/process')->getCollection();
        foreach ($indexCollection as $index) {
            $index->reindexAll();
        }
    }

    private function reindexPrice() {
        $process = Mage::getModel('index/process')->load(2);
        $process->reindexAll();
    }

    private function reindexItem($product) {
        $stockItem = mage::getmodel('cataloginventory/stock_item')->loadbyproduct($product->getid());
        $stockItem->setforcereindexrequired(true);
        mage::getsingleton('index/indexer')->processEntityAction($stockItem, Mage_CatalogInventory_Model_Stock_Item::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
        $product->setforcereindexrequired(true)->setischangedcategories(true);
        mage::getsingleton('index/indexer')->processEntityAction($product, Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
    }

    private function createGroup($magentoProduct, $productId) {
        $subProducts = $this->createSubProducts($productId);

        $bundleInfo = array();

        foreach($subProducts as $subProduct){
            $bundleTemp = array('name' => $subProduct['lightspeed']->name, 'values' => array());
            if($subProduct['lightspeed']->productType != 'CHOICE' && $subProduct['lightspeed']->productType != 'GROUP'){
                $bundleTemp['values'][] = $subProduct['magento'];
            }
            $bundleInfo[] = $bundleTemp;
        }

        $bundle = $this->createBundleOptionsAndSelections($bundleInfo);

        $magentoProduct->setTypeId('bundle');

        Mage::unregister('product');
        Mage::register('product', $magentoProduct);

        $magentoProduct->setCanSaveConfigurableAttributes(false);

        $magentoProduct->setCanSaveCustomOptions(true);
        $magentoProduct->setCanSaveBundleSelections(true);
        $magentoProduct->setAffectBundleProductSelections(true);

        $magentoProduct->setBundleOptionsData($bundle['options']);
        $magentoProduct->setBundleSelectionsData($bundle['selections']);

        try {
            $magentoProduct->save();
        } catch (Exception $e) {
            $magentoProduct->getResource()->save($magentoProduct);
        }
    }

    private function createBundleOptionsAndSelections($options){
        $bundleOptions = array();
        $bundleSelections = array();
        $index = 0;

        foreach($options as $option){
            $key = strtolower($option['name']);
            $bundleOptions[$key] = array(
                'title' => $option['name'],
                'option_id' => '',
                'delete' => '',
                'type' => 'radio',
                'required' => '1',
                'position' => $index
                );
            $index2 = 0;
            $bundleSelections[$key] = array();
            foreach($option['values'] as $product){
                $bundleSelections[$key][] = array(
                    'product_id' => $product["id"],
                    'selection_id' => '',
                    'delete' => '',
                    'selection_price_value' => $product["price"],
                    'selection_price_type' => 0,
                    'selection_qty' => 1,
                    'selection_can_change_qty' => 0,
                    'position' => $index2,
                    'is_default' => $index2 == 0 ? 1 : 0,
                    'sku' => 'options_'.$index2.'_'.time(),
                    );
                $index2++;
            }
            $index++;
        }

        return array('options' => $bundleOptions, 'selections' => $bundleSelections);
    }

    private function createSubProducts($productId) {
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        $subProducts = $apiHelper->getSubProducts($productId);
        $taxInclusivePrices = (Mage::getStoreConfig('lightspeed_settings/lightspeed_sync/lightspeed_use_tax_inclusive') == '1');

        $allCategories = $apiHelper->getProductGroups();

        $ret = array();

        foreach ($subProducts as $sub) {
            $subProduct = $syncHelper->getProduct($sub->id);

            if (!$subProduct){
                $subProduct = $apiHelper->getProduct($sub->id);
            }

            $subCategory = $this->findCategory($allCategories, $subProduct->groupIds);
            $magentoSubCategory = $this->getMagentoCategory($subCategory->categoryId, $subCategory->name);

            if (!$magentoSubCategory) {
                $magentoSubCategory = $this->importCategory(array('id' => $subCategory->id, 'name' => $subCategory->name));
            }

            // Is sometimes an array sometimes not.
            if (is_array($magentoSubCategory)) {
                $magentoSubCategory = $magentoSubCategory[0];
            }

            $magentoSubProduct = $this->createMagentoProduct($subProduct, $magentoSubCategory->getId(), $taxInclusivePrices);

            try {
                $magentoSubProduct->save();
            } catch (Exception $e) {
                $magentoSubProduct->getResource()->save($magentoSubProduct);
            }

            $ret[] = array('magento' => array("id" => $magentoSubProduct->getId(), "price" => $magentoSubProduct->getPrice()), 'lightspeed' => $subProduct);
        }
        return $ret;
    }

    private function findCategory($allCategories, $groupIds) {
        foreach ($groupIds as $id) {
            foreach ($allCategories as $category) {
                if (!$category->shortcutCategory && $category->id == $id) {
                    return $category;
                }
            }
        }
    }


    private function  createComboSubProducts($productId, $lightspeedProduct, $taxClasses, $magentoCategoryId){
        $syncHelper = Mage::helper('lightspeed_syncproducts/syncProcess');
        $apiHelper = Mage::helper('lightspeed_syncproducts/api');
        $subProducts = $apiHelper->getSubProducts($id);
        $index = 0;

        $bundleSelections = array();

        foreach($subProducts as $subProduct){
            $sub = $syncHelper->getProduct($subProduct->id);
            if(!isset($sub)){
                $sub = $apiHelper->getProduct($subProduct->id);
            }
            if(array_key_exists($magentoCategoryId, $sub->groupIds)){
                $subCategoryId = $magentoCategoryId;
            } else {
                $lightspeedCategory = $syncHelper->getProductgetProductGroup($sub->groupIds[0]);
                if(!$lightspeedCategory){
                    $lightspeedCategory = $apiHelper->getProductGroup($sub->groupIds[0]);
                    $subCategory = $this->importCategory(array('id' => $lightspeedCategory->id, 'name'=> $lightspeedCategory->name));
                } else {
                    $subCategory = $this->importCategory($lightspeedCategory);
                }
                $subCategoryId = $subCategory->getId();
            }
            $magentoSubProduct = $this->createProduct($subCategoryId, $this->getTaxClassId($sub, $taxClasses), $subCategoryId);
            $bundleSelections[$index] = array();
            $bundleSelections[$index][] = array(
                'product_id' => $magentoSubProduct->getId(),
                'delete' => '',
                'selection_price_value' => $sub->price,
                'selection_price_type' => 0,
                'selection_qty' => 1,
                'selection_can_change_qty' => 0,
                'position' => $index,
                'is_default' => $index == 0 ? 1 : 0,
                'sku' => 'options_'.$index.'_'.time(),
                );
            $index++;
        }
        return $bundleSelections;
    }

    private function getMagentoProduct($id) {
        $products = $categories = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter(array(array('attribute' => 'posiosId', 'eq' => $id)));

        $ret = array();

        foreach ($products as $product) {
            $ret[] = $product;
        }

        return $ret;
    }

    private function getMagentoCategory($posiosId, $name) {
        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addIsActiveFilter()
            ->addFieldToFilter(array(array('attribute' => 'posiosId', 'eq' => $posiosId), array('attribute' => 'name', 'eq' => $name )));

        $ret = array();
        foreach ($categories as $category) {
            $ret[] = $category;
        }
        return $ret;
    }

    private function getMagentoCategoryById($posiosId) {
        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addIsActiveFilter()
            ->addFieldToFilter(array(array('attribute' => 'posiosId', 'eq' => $posiosId)));

        $ret = array();
        foreach ($categories as $category) {
            if ($category->getData('posiosId') == $posiosId) {
                $ret = $category;
                break;
            }
        }
        return $ret;
    }

    public function syncCustomers($syncField) {
        $magentoCustomers = $this->getMagentoCustomers($syncField);
        foreach ($magentoCustomers as $magentoCustomer) {
            $this->importCustomer($magentoCustomer, null, null, null);
        }
    }

    public function importCustomer($magentoCustomer, $billingAddress, $shippingAddress, $establishmentId) {
        $customer = array ();

        if (!isset($billingAddress)) {
            $billingAddress = Mage::getModel('customer/address')->load($magentoCustomer->getData("default_billing"));
        }

        if (!isset($shippingAddress)) {
            $shippingAddress = Mage::getModel('customer/address')->load($magentoCustomer->getData("default_shipping"));
        }

        if (!isset($billingAddress)) {
            $billingAddress = $shippingAddress;
        }

        if (!isset($shippingAddress)) {
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
        if (isset($posiosId)) {
            if (isset($establishmentId)) {
                $apiHelper->saveCustomer($customer, $posiosId, null);
                $establishmentCustomer = $apiHelper->getCustomer($posiosId, $establishmentId);
                $apiHelper->saveCustomer($customer, $establishmentCustomer->id, $establishmentId);
                $posiosId = $establishmentCustomer->id;
            } else {
                $customer["id"] = (int) $posiosId;
                $apiHelper->saveCustomer($customer, $posiosId, null);
            }
        } else {
            $posiosId = $apiHelper->createCustomer($customer, null);
            $magentoCustomer->setData('posiosId', (int) $posiosId);
            $magentoCustomer->getResource()->saveAttribute($magentoCustomer, 'posiosId');
            if (isset($establishmentId)) {
                $establishmentCustomer = $apiHelper->getCustomer($posiosId, $establishmentId);
                $posiosId = $establishmentCustomer->id;
            }
        }
        return $posiosId;
    }
    private function getCustomerIdForEstablishment($attributeValue, $establishmentId) {
        $this->log('Parsing establishment posios id...');
        if (strpos($attributeValue, ( string ) $establishmentId) !== false) {
            $this->log('Establishment id found...');
            preg_match('/' . $establishmentId . '_(.*?);/', $attributeValue, $matches);
            if (count($matches) > 0) {
                return $matches[1];
            } else {
                return false;
            }
        } else {
            $this->log('Establishment id not found...');
            return false;
        }
    }
    private function downloadFile($url, $fullPath) {
        $fp = fopen($fullPath, 'w');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies');
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies');
        $data = curl_exec($ch);

        if (fwrite($fp, $data)) {
            return true;
        } else {
            return false;
        }
    }
    private function parseMagentoStreet($street) {
        $this->log('Parse street ' . print_r($street, true));
        $arr = is_array($street) ? $street : explode("\n", $street);
        $street = implode(" ", $arr);

        $result = array ();
        $lastSpacePosition = strrpos($street, " ");
        if ($lastSpacePosition > 0) {
            $number = substr($street, $lastSpacePosition + 1);
            $street = substr($street, 0, $lastSpacePosition);
        } else {
            $number = $street;
            $street = '?';
        }

        $result[0] = $street;
        $result[1] = $number;

        if (!isset($result[0])) {
            $result[0] = "";
        }

        if (!isset($result[1])) {
            $result[1] = "";
        }

        $this->log('Parsed street ' . print_r($result, true));
        return $result;
    }
    private function getMagentoCustomers($syncField) {
        $users = mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('*');
        if ('existing' == $syncField) {
            $users->addFieldToFilter(array (
                    array (
                            'attribute' => 'posiosId',
                            'neq' => 'NULL'
                    )
            ));
        } else if ('new' == $syncField) {
            $users->addAttributeToFilter('posiosId', array (
                    'null' => true
            ), 'left');
        }
        $ret = array ();
        foreach ($users as $user) {
            $ret[] = $user;
        }
        return $ret;
    }
    private function decodeName($name) {
        $name = str_replace('@', ' ', $name);
        $name = str_replace('!', '.', $name);
        return $name;
    }
    private function addModifications($magentoProduct, $lightspeedProduct, $taxInclusivePrices) {
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
            $optionsToDelete = array ();
            foreach ($oldOptions as $option) {
                $optionsData = $option->getData();
                $optionsData['is_delete'] = '1';
                $optionsToDelete[] = $optionsData;
            }
            $optionInstance->setOptions($optionsToDelete)->saveOptions();
        }

        $optionInstance = $magentoProduct->getOptionInstance()->unsetOptions();

        $index = 0;
        $sku = $lightspeedProduct->sku;
        foreach ($lightspeedProduct->additions as $addition) {
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
                foreach ($addition->values as $additionValue) {
                    $price = $additionValue->price;
                    if (!$taxInclusivePrices) {
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
    private function getOptionType($option) {
        if (!isset($option->values) || count($option->values) == 0) {
            return 'area';
        } elseif ($option->multiselect) {
            return 'multiple';
        } else {
            return 'drop_down';
        }
    }
    private function isRequired($option) {
        if (!isset($option->values) || count($option->values) == 0) {
            return false;
        } else if (!$option->multiselect) {
            return true;
        } else {
            return $option->minSelectedAmount > 0;
        }
    }

    private function deleteImages($magentoProduct, $mediaApi) {
        if ($magentoProduct->getId()) {
            $items = $mediaApi->items($magentoProduct->getId());
            foreach ($items as $item) {
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
            Mage::log("Order #{$incId} Reorder Error : {$e->getMessage()}", null, "reorder.log");
        }
    }
}

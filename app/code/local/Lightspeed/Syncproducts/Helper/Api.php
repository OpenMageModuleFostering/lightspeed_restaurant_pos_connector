<?php
class Lightspeed_Syncproducts_Helper_Api  extends Mage_Core_Helper_Abstract{

    const CORE =  "PosServer/rest/core/";
    const HEARTBEAT_CONTROLLER =  "PosServer/rest/heartbeat/";
    const INVENTORY =  "PosServer/rest/inventory/";
    const ONLINE_ORDERING =  "PosServer/rest/onlineordering/";
    const SECURITY_API = "PosServer/rest/";
    const RPC = "PosServer/JSON-RPC";

    private $cachedClientApiToken=null;


    private function log($message){
        Mage::log($message, null, "lightspeed.log");
    }

    private function logPost($message){
        Mage::log($message, null, "lightspeedPost.log");
    }

    private function logGet($message){
        Mage::log($message, null, "lightspeedGet.log");
    }

    private function logPut($message){
        Mage::log($message, null, "lightspeedPut.log");
    }

    private function logPatch($message) {
        Mage::log($message, null, "lightspeedPatch.log");
    }

    public function getClientApiToken(){
        $this->log('Getting api token...');

        if($this->cachedClientApiToken == null){
            $username = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_username");
            $password = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_password");
            $companyId = Mage::getStoreConfig('lightspeed_settings/lightspeed_account/lightspeed_company');
            $response = $this->post(self::SECURITY_API, "token",array("username" => $username, "password" => $password, "companyId" => $companyId, "deviceId" => "webmanager"), true, false);
            if($response["status"] == 200){
                $this->cachedClientApiToken = $response["data"]->token;
                $this->log("Got api token: " . $this->cachedClientApiToken);
            } else {
                $this->log("Error getting api token: ");
                $this->cachedClientApiToken = null;
            }
        }
        return $this->cachedClientApiToken;
    }

    protected function getEstablishmentToken($establishmentId){
        $username = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_username");
        $password = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_password");
        $response = $this->post(self::SECURITY_API, "token",array("username" => $username, "password" => $password, "companyId" => $establishmentId, "deviceId" => "webmanager"), true, false);
        if($response["status"] == 200){
            $this->log("Got establishment api token: " . $response["data"]->token);
            return $response["data"]->token;
        } else {
            $this->log("Error getting establishment api token: ");
        }
    }

    public function getProductGroups(){
        $response = $this->get(self::INVENTORY, "productgroup", array("amount" => 50));
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getProductGroup($productId){
        $response = $this->get(self::INVENTORY, "productgroup/".$productId, array("amount" => 50));
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getProducts($productGroupId){
        $response = $this->get(self::INVENTORY, "productgroup/".$productGroupId."/product", array("amount" => 100));
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getProduct($productId){
        $response = $this->get(self::INVENTORY, "product/".$productId, array("amount" => 100));
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getSubProducts($productId) {
        $response = $this->get(self::INVENTORY, "product/".$productId."/subproduct", array("amount" => 100));
        if ($response["status"] == 200) {
            return $response["data"];
        } else {
        }
    }

    public function getAllProducts(){
        $response = $this->get(self::INVENTORY, "product", array("amount" => 100));
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function createCustomer($customer, $establishmentId){
        $this->log('Creating user for establishment: '.$establishmentId);
        if(isset($establishmentId)){
            $token = $this->getEstablishmentToken($establishmentId);
        } else {
            $token = null;
        }
        $response = $this->post(self::ONLINE_ORDERING, "customer", $customer, false, true, $token);
        if($response["status"] == 201){
            return intval($response["data"]);
        } else {
        }
    }

    public function saveCustomer($customer, $id, $establishmentId = null){
        $this->log('Updating user for establishment: '.$establishmentId);
        if(isset($establishmentId)){
            $token = $this->getEstablishmentToken($establishmentId);
        } else {
            $token = null;
        }
        $customer['id'] = (int)$id;
        $response = $this->put(self::ONLINE_ORDERING, "customer/".$id, $customer, true, $token);
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getCustomer($id, $establishmentId = null){
        if(isset($establishmentId)){
            $token = $this->getEstablishmentToken($establishmentId);
        } else {
            $token = null;
        }
        $response = $this->get(self::ONLINE_ORDERING, 'customer/'.$id, array(), true, $token);
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getPaymentTypes(){
        $response = $this->get(self::ONLINE_ORDERING, "paymenttype");
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getPaymentType($id){
        $response = $this->get(self::ONLINE_ORDERING, "paymenttype");
        $id = (int)$id;
        if($response["status"] == 200){
            foreach($response["data"] as $paymentType){
                if($paymentType->id == $id){
                    return $paymentType;
                }
            }
            return null;
        } else {
        }
    }

    public function getTaxClasses(){
        $response = $this->get(self::ONLINE_ORDERING, "taxclass");
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function createOrder($order, $establishmentId = null){
        $this->log('Creating order' . (($establishmentId !== null) ? ' for establishment: ' . $establishmentId : ''));

        if(isset($establishmentId)){
            $token = $this->getEstablishmentToken($establishmentId);
            $response = $this->post(self::ONLINE_ORDERING, "customer/".$order["customerId"]."/establishmentorder", $order, false, true, $token);
        } else {
            $token = null;
            $response = $this->post(self::ONLINE_ORDERING, "customer/".$order["customerId"]."/order", $order, false, true, $token);
        }

        if($response["status"] == 201){
            return intval($response["data"]);
        } else {
        }
    }

    public function updateOrder($customerId, $posiosId, $orderPayment, $status) {
        $this->logPatch('Updating order with posiosId: ' . $posiosId . ' to payment status: ' . $status);

        $response = $this->patch(self::ONLINE_ORDERING, "customer/" . $customerId . "/order/" . $posiosId, array("orderPayment" => $orderPayment, "status" => $status), true, null);
        $this->logPut($response);

        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getOrders($status, $offset){
        $params = array(
            "amount" => 15
        );
        if(isset($status)){
            $params["status"] = $status;
        }
        $this->logGet('Offset = ' .$offset);
        if(isset($offset)){
            $params["offset"] = $offset;
        }
        $response = $this->get(self::ONLINE_ORDERING, "order", $params);
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    public function getEstablishments(){
        $response = $this->get(self::CORE, "company");
        if($response["status"] == 200){
            return $response["data"];
        } else {
        }
    }

    private  function post($api, $resource, array $data = array(), $decode = true, $secure = true, $token = null)
    {
        $this->logPost('Creating '.$resource);
        $this->logPost(print_r($data, true));
        $this->logPost(json_encode($data));
        $url = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_server");
        if(!substr($url, -strlen('/'))==='/'){
            $url .= '/';
        }
        $url .= $api.$resource;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        $headers = array('Content-Type: application/json', 'Accept: application/json');
        if($secure){
            if(isset($token)){
                $this->log('Posting '.$resource.' using provided token...');
                $headers[] = 'X-Auth-Token: '.$token;
            } else {
                $this->log('Posting '.$resource.' using normal token...');
                $headers[] = 'X-Auth-Token: '.$this->getClientApiToken();
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $curl_post_response = curl_exec($curl);
        $curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if($decode){
            $curl_post_response = json_decode($curl_post_response);
        }
        $this->logPost('Created '.$resource);
        $this->logPost(print_r(array("data" => $curl_post_response, "status" => $curl_status), true));
        return array("data" => $curl_post_response, "status" => $curl_status);
    }

    private  function put($api, $resource, array $data = array(), $secure = true, $token = null)
    {
        $this->logPut('Creating '.$resource);
        $this->logPut(print_r($data, true));
        $this->logPut(json_encode($data));
        $url = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_server");
        if(!substr($url, -strlen('/'))==='/'){
            $url .= '/';
        }
        $url .= $api.$resource;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        $headers = array('Content-Type: application/json', 'Accept: application/json');
        if($secure){
            if(isset($token)){
                $headers[] = 'X-Auth-Token: '.$token;
            } else {
                $headers[] = 'X-Auth-Token: '.$this->getClientApiToken();
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $curl_post_response = curl_exec($curl);
        $curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->logPut('Created '.$resource);
        $this->logPut(print_r(array("data" => $curl_post_response, "status" => $curl_status), true));
        return array("data" => json_decode($curl_post_response), "status" => $curl_status);
    }

    private  function patch($api, $resource, array $data = array(), $secure = true, $token = null) {
        $this->logPatch('Creating '.$resource);
        $this->logPatch(print_r($data, true));
        $this->logPatch(json_encode($data));
        $url = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_server");
        if(!substr($url, -strlen('/'))==='/'){
            $url .= '/';
        }
        $url .= $api.$resource;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        $headers = array('Content-Type: application/json', 'Accept: application/json');
        if($secure){
            if(isset($token)){
                $headers[] = 'X-Auth-Token: '.$token;
            } else {
                $headers[] = 'X-Auth-Token: '.$this->getClientApiToken();
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $curl_post_response = curl_exec($curl);
        $curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->logPatch('Created ' . $resource);
        $this->logPatch(print_r(array("data" => $curl_post_response, "status" => $curl_status), true));
        return array("data" => json_decode($curl_post_response), "status" => $curl_status);
    }

    private  function get($api, $resource, array $data = array(), $secure = true, $token = null)
    {
        $this->logGet('Getting '.$resource);
        $this->logGet(print_r($data, true));
        $this->logGet(json_encode($data));
        $url = Mage::getStoreConfig("lightspeed_settings/lightspeed_account/lightspeed_server");
        if(!substr($url, -strlen('/'))==='/'){
            $url .= '/';
        }
        $url .= $api.$resource;
        if(isset($data) && count($data) > 0){
            $url .= '?' . http_build_query($data);
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array('Content-Type: application/json', 'Accept: application/json');
        if($secure){
            if(isset($token)){
                $headers[] = 'X-Auth-Token: '.$token;
            } else {
                $headers[] = 'X-Auth-Token: '.$this->getClientApiToken();
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $curl_response = curl_exec($curl);
        $curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->logGet('Got '.$resource);
        $this->logGet(print_r(array("data" => $curl_response, "status" => $curl_status), true));
        curl_close($curl);

        return array("data" => json_decode($curl_response), "status" => $curl_status);
    }
}

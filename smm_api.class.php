<?php
/**
 * SMM API Helper Class
 * Yeh class API providers se baat karne, services fetch karne,
 * order place karne, aur refill/cancel (aap ki request) ka kaam karti hai.
 */
class SmmApi
{
    private $api_url;
    private $api_key;

    public function __construct($api_url, $api_key)
    {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
    }

    /**
     * API ko call karne ka main function
     */
    private function connect($action, $post_data = [])
    {
        $post_data['key'] = $this->api_key;
        $post_data['action'] = $action;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_USERAGENT, 'SubHub-SMM-Script');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout 60 seconds (sync ke liye)
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return json_encode(['error' => 'cURL Error: ' . $error_msg]);
        }
        
        curl_close($ch);

        if ($http_code != 200) {
             return json_encode(['error' => 'API returned HTTP code ' . $http_code]);
        }
        
        return $response;
    }

    /**
     * Action: 'services'
     * Provider se tamam services fetch karta hai
     */
    public function getServices()
    {
        $response = $this->connect('services');
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return ['success' => false, 'error' => 'Failed to decode API response.', 'response' => $response];
        }
        
        return ['success' => true, 'services' => $data];
    }
    
    /**
     * Action: 'balance'
     * Provider par balance check karta hai
     */
    public function getBalance()
    {
        $response = $this->connect('balance');
        $data = json_decode($response, true);

        if (isset($data['balance']) && isset($data['currency'])) {
            return ['success' => true, 'balance' => $data['balance'], 'currency' => $data['currency']];
        }
        
        return ['success' => false, 'error' => $data['error'] ?? 'Invalid balance response'];
    }

    /**
     * Action: 'add'
     * Naya order place karta hai
     */
    public function placeOrder($service_id, $link, $quantity, $drip_feed_data = null)
    {
        $post_data = [
            'service' => $service_id,
            'link' => $link,
            'quantity' => $quantity
        ];
        
        // Drip-Feed (Aap ki request)
        if ($drip_feed_data) {
            $post_data['dripfeed'] = 'yes';
            $post_data['runs'] = $drip_feed_data['runs'];
            $post_data['interval'] = $drip_feed_data['interval'];
        }

        $response = $this->connect('add', $post_data);
        $data = json_decode($response, true);

        if (isset($data['order'])) {
            return ['success' => true, 'provider_order_id' => $data['order']];
        }
        
        return ['success' => false, 'error' => $data['error'] ?? 'Failed to place order'];
    }

    /**
     * Action: 'status'
     * Order ka status check karta hai
     */
    public function getOrderStatus($provider_order_id)
    {
        $response = $this->connect('status', ['order' => $provider_order_id]);
        $data = json_decode($response, true);
        
        if (isset($data['status'])) {
            return ['success' => true, 'status_data' => $data];
        }
        
        return ['success' => false, 'error' => $data['error'] ?? 'Failed to get status'];
    }

    /**
     * Action: 'refill' (Aap ki request)
     * Order ko refill karta hai
     */
    public function refillOrder($provider_order_id)
    {
        $response = $this->connect('refill', ['order' => $provider_order_id]);
        $data = json_decode($response, true);

        if (isset($data['refill']) || (isset($data['status']) && $data['status'] == 'Success')) {
            return ['success' => true, 'refill_id' => $data['refill'] ?? 'OK'];
        }
        
        return ['success' => false, 'error' => $data['error'] ?? 'Refill failed or not supported'];
    }
    
    /**
     * Action: 'cancel' (Aap ki request)
     * Order ko cancel karta hai
     */
    public function cancelOrder($provider_order_id)
    {
        $response = $this->connect('cancel', ['order' => $provider_order_id]);
        $data = json_decode($response, true);

        if (isset($data['order']) || (isset($data['status']) && $data['status'] == 'Success') ) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $data['error'] ?? 'Cancel failed or not supported'];
    }
}
?>
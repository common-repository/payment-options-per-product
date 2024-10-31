<?php
/**
 * Plugin Name: Payment Options Per Product
 * Version: 1.0.3
 * Author: Payiban
 * Author URI: www.payiban.nl 
 * Description: Select payment gateways per product in Woocommerce and subscriptions
 * Requires at least: 3.7
 * Tested up to: 4.7.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$product_id = 600;
$base_url      = 'https://www.payiban.com/nl/'; //testen met https://vansteinengroentjes.nl/agora/

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {


    
    /**
     * Form to select gateways per product.
     */
    function payiban_enabled_gateways_form() {
            global $post, $wc;

            
            echo '<p>Check the payment gateways you like to use for this product, at least one payment gateway should be selected.</p>';
            $subscription =false;


            if ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
                if (WC_Subscriptions_Product::get_price( $post->ID ) != ""){
                    $subscription = true;
                }
            }
            
            $wc = new WC_Payment_Gateways();
            $payment_gateways = $wc->payment_gateways;
            if ($subscription){
                foreach ($payment_gateways as $key => $gateway){
                    //check if gateway supports subscription
                    if (!in_array('subscriptions', $gateway->supports)){
                        unset($payment_gateways[$key]);
                    }
                }
            }

            $payment_gateway_ids = array();
            foreach ($payment_gateways as $gateway) {
                $payment_gateway_ids[] = $gateway->id;
            }
            $postenabled_gateways = count(get_post_meta($post->ID, 'enabled_gateways', true)) ? get_post_meta($post->ID, 'enabled_gateways', true) : $payment_gateway_ids;
            if (count($postenabled_gateways) ==0 || !is_array($postenabled_gateways)){
                //none enabled = all enabled    
                $postenabled_gateways = $payment_gateway_ids;
            }
            foreach ($payment_gateways as $gateway) {
                    if ($gateway->enabled == 'no') {
                            continue;
                    }
                    $checked = '';
                    if (is_array($postenabled_gateways) && in_array($gateway->id, $postenabled_gateways)) {
                            $checked = ' checked="checked" ';
                    }
                    echo '<input type="checkbox" '.$checked.' value="'.$gateway->id.'" name="enabled_gateways[]" id="gateway_'.$gateway->id.'" />
                    <label for="payment_'.$gateway->id.'">'.$gateway->title.'</label>  
                    <br />';
            }
    }

    function payiban_meta_box_add() {
            add_meta_box('enabled_gateways', 'Enable payment gateways', 'payiban_enabled_gateways_form', 'product', 'side', 'default');
            add_meta_box('enabled_gateways_2', 'Enable payment gateways', 'payiban_enabled_gateways_form', 'product_variation', 'side', 'default');
    }
    add_action('add_meta_boxes', 'payiban_meta_box_add');

    

    /**
     * Saves the filled in payment gateways per product in the meta info
     * @param  [type] $post_id 
     * @param  [type] $post    
     */
    function payiban_meta_box_save($post_id, $post) {
            if (isset($post->post_type) && $post->post_type == 'revision') {
                    //revisions are not useful
                    return $post_id;
            }
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    //autosave is also not handy for this
                    return $post_id;
            }
            if (isset($_POST['post_type']) && ($_POST['post_type'] == 'product' || $_POST['post_type'] == 'product_variation') && isset($_POST['enabled_gateways'])  ) {
                    //if post is posttype and we have input for gateways we need to save it.
                    
                    $paymentArray = array();
                    foreach ($_POST['enabled_gateways'] as $pay) {
                            $paymentArray[] = sanitize_text_field($pay);
                    }
                    update_post_meta($post_id, 'enabled_gateways', $paymentArray);
            }elseif (isset($_POST['post_type']) && $_POST['post_type'] == 'product'  ) {
                    update_post_meta($post_id, 'enabled_gateways', array());
            }
    }
    add_action('save_post', 'payiban_meta_box_save', 10, 2);

    /**
     * Disables gateways if they are not set for a certain product
     * @param  [array] $available_gateways Gateways available in general
     */
    function payibanpayment_gateway_disable_perproduct($available_gateways) {
            global $woocommerce;
            $arrayKeys = array_keys($available_gateways);
            if (count($woocommerce->cart)) {
                    $items = $woocommerce->cart->cart_contents;
                    $itemsPays = '';
                    if (is_array($items)) {
                            foreach ($items as $item) {
                                    $itemsPays = get_post_meta($item['product_id'], 'enabled_gateways', true);
                                    if (is_array($itemsPays) && count($itemsPays)) {
                                            foreach ($arrayKeys as $key) {
                                                    if (array_key_exists($key, $available_gateways) && !in_array($available_gateways[$key]->id, $itemsPays)) {
                                                        if (count($available_gateways)>1){
                                                            unset($available_gateways[$key]);
                                                        }
                                                    }
                                            }
                                    }
                            }
                    }
            }
            return $available_gateways;
    }
    add_filter('woocommerce_available_payment_gateways', 'payibanpayment_gateway_disable_perproduct',99);
}
<?php
/*
	Plugin Name: shurjoPay for Easy Digital Download WP V5.2.*
	Plugin URI: https://github.com/smukhidev
	Description: This plugin allows you to accept payments on your EDD store from customers using MFS, iBanking, Local and Internation Debit/Credit Card etc. via shurjoPay payment gateway.
	Version: 1
	Author: Nazmus Shahadat
	Author Email: shurjopay@shurjomukhi.com.bd
	Copyright: © 2015-2021 shurjoPay.
	License: GNU General Public License v3.0
	License URI: https://docs.easydigitaldownloads.com/article/942-terms-and-conditions
*/

if ( ! defined( 'ABSPATH' ) ) exit;

#--------------Show Label In Settings Page----------------------

function shurjopay_edd_register_gateway($gateways) {
	$gateways['shurjoPay'] = array('admin_label' => 'shurjoPay Payment Gateway', 'checkout_label' => __(edd_get_option( 'shurjopay_title' ), 'shurjopay'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'shurjopay_edd_register_gateway', 1, 1 );

// To remove the default cc form
remove_action( 'edd_cc_form', 'edd_get_cc_form' );
#----------------------END---------------------------------------


#-----------Payment GateWay Configure Page-----------------------
function shurjopay_edd_add_settings($settings) {
	
	$shurjopay_gateway_settings = array(
		array(
			'id' => 'spay_gateway_settings',
			'name' => '<br><br><hr><strong>' . __('Configure shurjoPay', 'spay_edd') . '</strong>',
			'desc' => __('Configure the gateway settings', 'spay_edd'),
			'type' => 'header'
		),
		array(
			'id' => 'spay_title',
			'name' => __('Checkout Title', 'spay_edd'),
			'desc' => __('This title will show in your Checkout page.', 'spay_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'store_id',
			'name' => __('Store ID', 'spay_edd'),
			'desc' => __('Enter your Store Id/API username provided from shurjoPay.', 'spay_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'store_password',
			'name' => __('Store/API Password', 'spay_edd'),
			'desc' => __('Enter your Store Password provided from shurjoPay.', 'spay_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'unique_id',
			'name' => __('Unique Code ', 'spay_edd'),
			'type' => 'text',
			'desc' => 'Enter your unique code'
		)
	);
	
	return array_merge($settings, $shurjopay_gateway_settings);	

}
add_filter('edd_settings_gateways', 'shurjopay_edd_add_settings', 1, 1);

#------------------------------------END---------------------------------------

#-------------------- Show setting link in plugin option -----------------------------------

function plugin_page_settings_link($links)
    {
        $links[] = '<a href="' .
        admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways') .
        '">' . __('Settings') . '</a>';
        return $links;
    }
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugin_page_settings_link');


#------------------------------------End-----------------------------------------

#---------------------Processing data and requesting shurjoPay Payment Gateway page-----------------------
function shurjoPay_process_payment($purchase_data) {
	global $edd_options;
 
	/**********************************
	* set transaction mode
	**********************************/
	if(edd_is_test_mode()) 
	{
		$request_api = "https://shurjotest.com/sp-data.php";
	} 
	else 
	{
		$request_api = "https://shurjopay.com/sp-data.php";
	}
	
	
		$success_url = add_query_arg( array(
			'payment-confirmation' => 'shurjoPay',
			'payment-id' => $payment
		), get_permalink( edd_get_option( 'success_page', false ) ) );


	$payment_data = array(
		'price'         => $purchase_data['price'],
		'date'          => $purchase_data['date'],
		'user_email'    => $purchase_data['user_email'],
		'purchase_key'  => $purchase_data['purchase_key'],
		'currency'      => edd_get_currency(),
		'downloads'     => $purchase_data['downloads'],
		'user_info'     => $purchase_data['user_info'],
		'cart_details'  => $purchase_data['cart_details'],
		'gateway'       => 'shurjoPay',
		'status'        => 'pending',
		'success_url' 	=> $return_url
	);

	$payment = edd_insert_payment( $payment_data );

	if ( ! $payment ) 
	{
		// Record the error
		edd_record_gateway_error( __( 'Payment Error', 'easy-digital-downloads' ), sprintf( __( 'Payment creation failed before sending buyer to shurjoPay. Payment data: %s', 'easy-digital-downloads' ), json_encode( $payment_data ) ), $payment );
		// Problems? send back
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	} 
	else 
	{
		$store_id = edd_get_option( 'store_id' );
		$store_password = edd_get_option( 'store_password' );
		$unique_id = edd_get_option('unique_id').$payment."_".rand(1,100);
		
		$return_url = add_query_arg( array(
			'payment-mode' => 'shurjoPay',
		), get_permalink( edd_get_option( 'purchase_page', false ) ) );
    
        
        $amount = 85 * $purchase_data['price'];
		
		$xml_data = 'spdata=<?xml version="1.0" encoding="utf-8"?>
		<shurjoPay><merchantName>' . $store_id . '</merchantName>
		<merchantPass>' . $store_password . '</merchantPass>
		<userIP>'. $_SERVER['REMOTE_ADDR'].'</userIP>
		<uniqID>' . $unique_id . '</uniqID>
		<currency>BDT</currency>
		<totalAmount>' . $amount . '</totalAmount>
		<paymentOption>shurjopay</paymentOption>
		<returnURL>' . $return_url . '</returnURL></shurjoPay>';
//<currency>' . edd_get_currency() . '</currency>
	    $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $request_api);
        curl_setopt($ch, CURLOPT_POST, 1);                //0 for a get request
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        curl_close($ch);
        print_r($response);
	}
}
add_action('edd_gateway_shurjoPay', 'shurjoPay_process_payment');

#-------------------------------END--------------------------------

#------------------- Add Custom Phone Field to purchase form --------------------

function user_contact_form_to_purchase()
{
	echo '<p id="edd-phone-wrap">	
		<span class="edd-description" id="edd-phone-description">We will send OTP to this number.</span>
		<label class="edd-label" for="edd-phone">
			Phone Number<span class="edd-required-indicator"> *</span></label>
		<input class="edd-input required" type="text" name="edd_phone" placeholder="Phone Number" id="edd-phone" value="" aria-describedby="edd-phone-description" required="">
		</p>';
}

add_action('edd_purchase_form_user_info', 'user_contact_form_to_purchase');


add_action('init', 'sp_redirect');

function sp_redirect() {
	$server_url="";
	if(edd_is_test_mode()) 
	{
		$server_url = "https://shurjotest.com";
	} 
	else 
	{
		$server_url = "https://shurjopay.com";
	}

	if (isset($_REQUEST['spdata']) && !empty($_REQUEST['spdata'])) {
		$response_encrypted = $_REQUEST['spdata'];
		$response_decrypted = file_get_contents($server_url . "/merchant/decrypt.php?data=" . $response_encrypted);
		$response_data = simplexml_load_string($response_decrypted) or die("Error: Cannot create object");
		
		if($response_data->spCode == "000")
		{
			$success_url = add_query_arg( array(
			'payment-confirmation' => 'shurjoPay',
			'payment-id' => $payment
		), get_permalink( edd_get_option( 'success_page', false ) ) );
			header("Location: ".html_entity_decode($success_url));
			 echo "<script type=\"text/javascript\">
				<!--
				window.location = \"".html_entity_decode($success_url)."\"
				//-->
				</script>";
		}
		else if($response_data->spCode == "001" and $response_data->status='')
		{
			$cancel_url = add_query_arg( array(
			'payment-confirmation' => 'shurjoPay',
			'payment-id' => $payment
		), get_permalink( edd_get_option( 'purchase_history_page', false ) ) );
		}
		else{
			$cancel_url = add_query_arg( array(
			'payment-confirmation' => 'shurjoPay',
			'payment-id' => $payment
		), get_permalink( edd_get_option( 'purchase_history_page', false ) ) );
			header("Location: ".html_entity_decode($spay_redirect['cancel_url']));
			 echo "<script type=\"text/javascript\">
				<!--
				window.location = \"".html_entity_decode($spay_redirect['cancel_url'])."\"
				//-->
				</script>";
		}
		die();
	}
}  


?>

<?php

/*Plugin Name: WooCommerce Uzcard Gateway
Plugin URI: https://uzcard.uz
Description: Take credit card payments on your store using uzcard
Version: 1.0
Author: Feruza Ernazarova
Author URI: https://uzcard.uz
*/


add_action ('plugins_loaded', 'init_uzcard', 1); 

function init_uzcard() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	include_once( 'includes/uzcard-gateway.php' );
}

add_filter( 'woocommerce_payment_gateways', 'uzcard_gateway' );


function uzcard_gateway( $methods ) {
	$methods[] = "WC_uzcard_Gateway";
	return $methods;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'uzcard_action_links' );

function uzcard_action_links( $links ) {
	$plugin_links = [
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=uzcard' ) . '">' . __( 'Settings', 'spyr-authorizenet-aim' ) . '</a>',
	];
	return array_merge( $plugin_links, $links );	
}




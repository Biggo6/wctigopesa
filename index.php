<?php

/*
  Plugin Name: TigoPesa
  Plugin URI:
  Description: TigoPesa Payment gateway for woocommerce
  Version: 1.0.0
  Author: Joram Kimata & Justin Kashaigili
  Author URI:
 */


add_action('plugins_loaded', 'tigopesa_init', 0);

function tigopesa_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;
    include_once( 'tigopesa.php' );
    add_filter('woocommerce_payment_gateways', 'tigopesa_gateway');

    function tigopesa_gateway($methods) {
        $methods[] = 'TigoPesa';
        return $methods;
    }

}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'tigopesa_action_links');

function tigopesa_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'tigopesa') . '</a>',
    );
    return array_merge($plugin_links, $links);
}

<?php

class TigoPesa extends WC_Payment_Gateway {

    // Setup our Gateway's id, description and other values
    function __construct() {

        // The global ID for this Payment method
        $this->id = "tigopesa";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("TigoPesa", 'tigopesa');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("TigoPesa Payment Gateway Plug-in for WooCommerce", 'tigopesa');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("TigoPesa", 'tigopesa');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = plugins_url() . "/tigopesa/assets/tigopesa.png";

        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        $this->supports = ['default_credit_card_form'];

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Lets check for SSL
        add_action('admin_notices', array($this, 'do_ssl_check'));

        add_action('woocommerce_receipt_tigopesa', array(&$this, 'receipt_page'));

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    }

    function receipt_page($order) {
        echo '<p>' . __('Thank you for your order, please click the button below to pay with PayU.', 'mrova') . '</p>';
    }

    public function api_interact_live() {
        
    }

    public function api_interact_test($auth_basic, $cid, $amount, $payer, $merchant) {
        $request = new HttpRequest();
        $request->setUrl('http://41.222.176.233:8080/v0.14/MM/transactions');
        $request->setMethod(HTTP_METH_POST);

        $request->setHeaders(array(
            'postman-token' => '7e551d06-6466-1ab3-fbf0-bac3bae8ded0',
            'cache-control' => 'no-cache',
            'authorization' => $auth_basic,
            'content-type' => 'application/json',
            'date' => date("Y-m-d H:i:s"),
            'x-correlationid' => $cid
        ));

        $request->setBody('{
            "amount": ' . $amount . ',
            "currency": "TZS",
            "type": "merchantPayment",
            "subType": "ussdPushAuthorisation",
            "debitParty": [
              {
                "key": "MSISDN",
                "value": ' . $payer . '
              }
            ],
            "creditParty": [
              {
                "key": "MSISDN",
                "value": ' . $merchant . '
              }
            ]

          }');

        try {
            $response = $request->send();

            return $response->getBody();
        } catch (HttpException $ex) {
            return $ex->getMessage();
        }
    }

// End __construct()
    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'tigopesa'),
                'label' => __('Enable this payment gateway', 'tigopesa'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'tigopesa'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'tigopesa'),
                'default' => __('Tigopesa', 'tigopesa'),
            ),
            'description' => array(
                'title' => __('Description', 'tigopesa'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'spyr-authorizenet-aim'),
                'default' => __('Pay securely using your Mobile Number.', 'tigopesa'),
                'css' => 'max-width:350px;'
            ),
            'correlation_id' => array(
                'title' => __('Correlation ID', 'tigopesa'),
                'type' => 'text',
                'desc_tip' => __('This is custom http header', 'tigopesa'),
                'default' => '42a5c221-7f6f-49b1-b780-d588903c732a'
            ),
            'auth_basic' => array(
                'title' => __('Basic Authorization', 'tigopesa'),
                'type' => 'text',
                'desc_tip' => __('This is The HTTP Basic Authentication string', 'tigopesa'),
                'default' => 'Basic MjU1NjU0NTU1Mzk0OjE5ODg='
            ),
            'merchant_msisdn' => array(
                'title' => __('Merchant Payment MSISDN', 'tigopesa'),
                'type' => 'text',
                'desc_tip' => __('This is MSISDN of merchant for payment in this format: 255XXXXXXXXX', 'tigopesa'),
                'default' => '255718532418'
            ),
            'environment' => array(
                'title' => __('TigoPesa Test Mode', 'tigopesa'),
                'label' => __('Enable Test Mode', 'tigopesa'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'tigopesa'),
                'default' => 'yes',
            )
        );
    }

    function payment_fields() {
        echo '<p>Pay securely using your Mobile Number.</p><br/><hr/><p class="form-row form-row-wide woocommerce-validated">
				<label for="tigopesa-card-number">Payer Mobile Number <span class="required">*</span></label>
				<input id="tigopesa-card-number" class="input-text wc-credit-card-form-card-number unknown" type="text" maxlength="12" autocomplete="off" placeholder="255••••••••••••" name="tigopesa-card-number">
			</p>';
    }

    // Submit payment and handle response
    public function process_payment($order_id) {
        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order($order_id);

        // Are we testing right now or is it a real transaction
        $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

        $payer = $_POST["tigopesa-card-number"];

        if ($payer == "") {
            throw new Exception(__('Please Enter Payer Mobile Number correctly', 'tigopesa'));
        }

        $merchant_msisdn = $this->merchant_msisdn;
        $auth_basic = $this->auth_basic;
        $correlation_id = $this->correlation_id;

        if ($environment == "TRUE") {
            $curl = curl_init();

            $json_data = ("{\r\n  \"amount\":  {$customer_order->order_total},\r\n  \"currency\": \"TZS\",\r\n  \"type\": \"merchantPayment\",\r\n  \"subType\": \"ussdPushAuthorisation\",\r\n  \"debitParty\": [\r\n    {\r\n      \"key\": \"MSISDN\",\r\n      \"value\": \"{$payer}\"\r\n    }\r\n  ],\r\n  \"creditParty\": [\r\n    {\r\n      \"key\": \"MSISDN\",\r\n      \"value\": \"{$merchant_msisdn}\"\r\n    }\r\n  ]\r\n  \r\n}");


            curl_setopt_array($curl, array(
                CURLOPT_PORT => "8080",
                CURLOPT_URL => "http://41.222.176.233:8080/v0.14/MM/transactions",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $json_data,
                CURLOPT_HTTPHEADER => array(
                    "authorization: Basic MjU1NjU0NTU1Mzk0OjE5ODg=",
                    "cache-control: no-cache",
                    "content-type: application/json",
                    "date: 2016-07-23T11:28:37.906Z",
                    "postman-token: 82311266-256a-7483-1a0e-ee9c4968a313",
                    "x-correlationid: 324564555"
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                $res = "Error #:" . $err;
                wc_add_notice($res, 'success');
            } else {
                $res1 = $response;
                $data = json_decode($res1);
                $res = $data->status;

                if ($res == "pending") {
                    // Payment has been successful
                    $customer_order->add_order_note(__('TigoPesa payment completed.', 'tigopesa'));

                    // Mark order as Paid
                    $customer_order->payment_complete();

                    // Empty the cart (Very important step)
                    $woocommerce->cart->empty_cart();

                    // Redirect to thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($customer_order),
                    );
                } 
            }
        } else {
            // Live Mode Codes
            $json_data = ("{\r\n  \"amount\":  {$customer_order->order_total},\r\n  \"currency\": \"TZS\",\r\n  \"type\": \"merchantPayment\",\r\n  \"subType\": \"ussdPushAuthorisation\",\r\n  \"debitParty\": [\r\n    {\r\n      \"key\": \"MSISDN\",\r\n      \"value\": \"{$payer}\"\r\n    }\r\n  ],\r\n  \"creditParty\": [\r\n    {\r\n      \"key\": \"MSISDN\",\r\n      \"value\": \"{$merchant_msisdn}\"\r\n    }\r\n  ]\r\n  \r\n}");
            wc_add_notice($json_data, 'success');
        }
    }
    
    
    

    // Validate fields
    public function validate_fields() {
        return true;
    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

}

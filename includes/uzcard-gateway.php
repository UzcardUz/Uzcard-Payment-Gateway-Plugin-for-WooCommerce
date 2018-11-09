<?php 	


define( 'UZCARD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
use Ratchet\Client;

class WC_Uzcard_Gateway extends WC_Payment_Gateway {

	function __construct() {

		$this->id = "uzcard";
		$this->method_title = __( "Uzcard", "uzcard");
		$this->method_description = __( "Uzcard Payment Gateway Plug-in for WooCommerce", "uzcard" );
		$this->title = __( "Uzcard", "uzcard");
		$this->icon = plugins_url("logo.png", dirname(__FILE__));
		$this->has_fields = true;
		// $this->supports = array( 'default_credit_card_form' );
		$this->init_form_fields();
		$this->init_settings();
		
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ), 1 );
		}
		$this->return = [];

	} 

    function init_form_fields(){

        $this->form_fields = [

    		'merchant_id' => [
    				'title' => __('Merchant ID', 'Uzcard'),
    				'type' => 'text',
    				'description' => __('Obtain and set Merchant ID from the Uzcard Merchant Cabinet', 'Uzcard'),
    				'default' => ''
    		],
    		'merchant_key' => [
    				'title' => __('Ключ - пароль кассы', 'Uzcard'),
    				'type' => 'text',
    				'description' => __('Obtain and set KEY from the Uzcard Merchant Cabinet', 'Uzcard'),
    				'default' => ''
    		],

    		'checkout_url' => [
    				'title' => 'Введите URL-адрес шлюза',
    				'type' => 'text',
    				'description' => __('Set Uzcard Checkout URL to submit a payment', 'Uzcard'),
    				'default' => 'http://195.158.28.125:9099/api/payment/PaymentsWithOutRegistration'
    		],
            
    		'enabled' => [
                'title' => 'Включить режим тестирования',
                'type' => 'checkbox',
                'label' => __('Enabled', 'Uzcard'),
                'default' => 'yes'
            ],

    		'merchant_key_test' => [
    				'title' => 'Ключ - пароль для тестов',
    				'type' => 'text',
    				'description' => __('Obtain and set KEY from the Uzcard Merchant Cabinet', 'Uzcard'),
    				'default' => ''
    		],

    		'checkout_url_test' => [
       				'title' => 'Введите URL-адрес шлюза для теста',
       				'type' => 'text',
       				'description' => '',
       				'default' => 'https://test.uzcard.uz'
       		],

    	];

	}


	function process_payment( $order_id ) {

	    global $woocommerce;
	    $post = $_POST;
	    $order = new WC_Order( $order_id );
	    $url = get_option('woocommerce_uzcard_settings')['checkout_url'];

		$now = new DateTime();
		$expire = explode('/', $post['uzcard-expire']);
		$expire = $expire[1].$expire[0];
		$cardLastNum = str_replace('-', '', $post['uzcard-card-last-number']);

		$jsonPayment = [
			'otp' => $post['uzcard-otp'],
			'expire' => $expire,
			'uniques' => $post['uzcard-unique-code'],
			'requestId' => $now->getTimestamp(),
			'phonenumber' => $post['uzcard-phone-number'],
			'cardLastNum' => $cardLastNum,
			'summa' => WC()->cart->cart_contents_total,
			'key' => get_option('woocommerce_uzcard_settings')['merchant_key'],
			'eposId' => get_option('woocommerce_uzcard_settings')['merchant_id']
		];

		$data = json_encode($jsonPayment);

		$msg = Requests::post( 
			$url,
			[
				'Content-Type' => 'application/json',
				'accept' => 'application/json',
				'auth' => false,
				'verify' => false
			],
			$data
		);

		$msg = json_decode($msg->body, true);

		if ($msg['result']) {
			if ($msg['result']["amount"] == WC()->cart->cart_contents_total)
				$order->update_status('completed', __( 'cheque payment uzcard', 'woocommerce' ));
			else
				$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));
		    $order->reduce_order_stock();
		    $woocommerce->cart->empty_cart();
			$order->payment_complete();
	    	return [
			    'result' => 'success',
			    'redirect' => $this->get_return_url($order)
			];
		} else {
			if ($msg['error']['code']=="-4" || $msg['error']['code']=="-6" || $msg['error']['code']=="-3" || $msg['error']['code']=="-11") {
				$msg['error']['message'] == "Возникла проблема с оплатой, попробуйте еще раз. Номер ошибки: " + $msg['error']['code'];
			}
			wc_add_notice( __('Payment error:', 'woothemes') . $msg['error']['message'], 'error' );
			return;
			// $order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));
		}



	}

	public function payment_fields() {
		if ( $this->supports( 'tokenization' ) && is_checkout() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->form();
			$this->save_payment_method_checkbox();
		} else {
			$this->form();
		}
	}


	public function field_name( $name ) {
		return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
	}

	public function form() {

		function wp_uzcard_scripts_with_jquery()
		{
		    wp_register_script('uzcard-script', 
		    	plugins_url( 'assets/js/uzcard-scripts.js', dirname(__FILE__)), [ 'jquery' ], 1.1, false);
		    wp_enqueue_script('uzcard-script');
		}

		function wp_uzcard_css()
		{
		    wp_register_style('uzcard-style', 
		    	plugins_url( 'assets/css/uzcard-gateway.css', dirname(__FILE__)));
		    wp_enqueue_style('uzcard-style');
		}

		add_action( 'wp_enqueue_scripts', 'wp_uzcard_scripts_with_jquery' );

		add_action( 'wp_enqueue_style', 'wp_uzcard_css' );

		function woocommerce_credit_card_form_first() {
		   // echo '<div id="form-first" style="display: none">';
		   echo '<div id="form-first">';
		}

		add_action( 'uzcard-form-1', 'woocommerce_credit_card_form_first');		

		function woocommerce_credit_card_form_second() {
		   // echo '<div id="form-second">';
		   echo '<div id="form-second" style="display: none">';
		}

		add_action( 'uzcard-form-2', 'woocommerce_credit_card_form_second');

		function formFooter() {
		   echo '</div>';
		}

		add_action( 'uzcardFooter', 'formFooter' );

		function script() {
			$cartTotal = WC()->cart->cart_contents_total;
			$eposId = get_option('woocommerce_uzcard_settings')['merchant_id'];
			$key = get_option('woocommerce_uzcard_settings')['merchant_key'];
			echo "<script type='text/javascript'>
			    var cartTotal = \"$cartTotal\";
			    var eposId = \"$eposId\";
			    var key = \"$key\";

			</script>";
		}

		add_action( 'uzcardScript', 'script' );

		$fields = [];
		
		$last_numbers_card = '<p class="form-row form-row-last">
			<label for="' . esc_attr( $this->id ) . '-last_numbers_card">' . esc_html__( 'Последние 6 цифр карты', 'woocommerce' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-last_numbers_card" class="input-text uzcard-payment-field wc-credit-card-form-last_numbers_card" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="7" placeholder="' . esc_attr__( 'XX-XXXX', 'woocommerce' ) . '" name="'.esc_attr( $this->id ) .'-card-last-number" style="width:100px" />
		</p>';		

		$otp = '<p class="form-row form-row-wide">
			<label for="' . esc_attr( $this->id ) . '-phone-otp">' . esc_html__( 'Введите код, полученный по СМС', 'woocommerce' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-otp" class="input-text wc-credit-card-form-otp" inputmode="numeric" autocapitalize="no" spellcheck="no"   placeholder="" name="' . esc_attr( $this->id ) . '-otp" type="text" maxlength="6"/>
		</p>';


		$default_fields = [
		'card-number-field' => '<p class="form-row form-row-wide">
			<label for="' . esc_attr( $this->id ) . '-phone-number">' . esc_html__( 'Введите номе телефона', 'woocommerce' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-phone-number" class="input-text uzcard-payment-field wc-credit-card-form-phone-number" inputmode="numeric"  spellcheck="no" type="tel" placeholder="" name="'.esc_attr( $this->id ) .'-phone-number" maxlength="12" onkeypress="validateExpireDateNumbers()"/>
		</p>',
		'card-expiry-field' => '<p class="form-row form-row-first">
			<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Срок действия (MM/YY)', 'woocommerce' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-expiry-uzcard" class="input-text uzcard-payment-field wc-credit-card-form-card-expiry-uzcard" inputmode="numeric"  autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM/YY', 'woocommerce' ) . '" name="'.esc_attr( $this->id ) .'-expire" maxlength="5" onkeypress="validateExpireDateNumbers(event)"/>
			<span class="expiredDateErrorClass" style="display: none; color: red;">Введите правильный месяц !</span>
		</p><p style="display: none"><input id="uniqueInput" name="'.esc_attr( $this->id ) .'-unique-code"  value="" type="text" /></p>',
		];		

		if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			$default_fields['card-cvc-field'] = $last_numbers_card;
		}

		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>

		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
			<?php do_action( 'uzcardScript', $this->id ); ?>			
			<?php do_action( 'wp_enqueue_scripts', $this->id ); ?>			
			<?php do_action( 'wp_enqueue_style', $this->id ); ?>			
			<?php do_action( 'uzcard-form-1', $this->id ); ?>
			<?php
				foreach ( $fields as $field ) {
				echo $field;
				}
			?>
			<?php do_action( 'uzcardFooter', $this->id ); ?>

			<?php do_action( 'uzcard-form-2', $this->id ); ?>
			<?php
				echo $otp;
			?>
			<?php do_action( 'uzcardFooter', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php

		if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			echo '<fieldset>' . $last_numbers_card . '</fieldset>';
		}
	}

}
	
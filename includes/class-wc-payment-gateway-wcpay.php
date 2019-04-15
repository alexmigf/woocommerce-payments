<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gateway class for WooCommerce Payments
 */
class WC_Payment_Gateway_WCPay extends WC_Payment_Gateway_CC {

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * API access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . self::GATEWAY_ID );
	}

	/**
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;

		$this->id                 = self::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon.
		$this->has_fields         = true;
		$this->method_title       = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via a WooCommerce-branded payment gateway', 'woocommerce-payments' );

		// Define setting fields.
		$this->form_fields = array(
			'enabled'              => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                => array(
				'title'       => __( 'Title', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => __( 'Credit Card (WooCommerce Payments)', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => __( 'Pay with your credit card via WooCommerce Payments.', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
			'testmode'             => array(
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable Test Mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_publishable_key' => array(
				'title'       => __( 'Test Publishable Key', 'woocommerce-payments' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_secret_key'      => array(
				'title'       => __( 'Test Secret Key', 'woocommerce-payments' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'publishable_key'      => array(
				'title'       => __( 'Live Publishable Key', 'woocommerce-payments' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'secret_key'           => array(
				'title'       => __( 'Live Secret Key', 'woocommerce-payments' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);

		// Load the settings.
		$this->init_settings();

		// Extract values we want to use in this class from the settings.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->testmode        = ( ! empty( $this->settings['testmode'] ) && 'yes' === $this->settings['testmode'] ) ? true : false;
		$this->publishable_key = ! empty( $this->settings['publishable_key'] ) ? $this->settings['publishable_key'] : '';
		$this->secret_key      = ! empty( $this->settings['secret_key'] ) ? $this->settings['secret_key'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $this->settings['test_publishable_key'] ) ? $this->settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $this->settings['test_secret_key'] ) ? $this->settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 */
	public function payment_fields() {
		// TODO: Revisit properly escaping this once showing payment fields is implemented.
		echo $this->get_description(); // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$amount = $order->get_total();

		if ( $amount > 0 ) {
			// TODO: implement the actual payment (that's the easy part, right?).
			try {
				$charge = $this->payments_api_client->create_charge( $amount, 'dummy-source-id' );

				$transaction_id = $charge->get_id();
				$order->add_order_note(
					sprintf(
						/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
						__( 'A payment of %1$s was successfully charged using WooCommerce Payments (Transaction #2%$s)', 'woocommerce-payments' ),
						wc_price( $amount ),
						$transaction_id
					)
				);

				$order->payment_complete( $transaction_id );
			} catch ( Exception $e ) {
				// TODO: Make this a less generic exception and handle a payment failing.
				// TODO: There may be failure cases we need to handle that we wouldn't raise an exception for as well.
				return null;
			}
		} else {
			$order->payment_complete();
		}

		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

}

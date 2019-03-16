<?php
/**
 * Plugin Name: WooCommerce XRP
 * Plugin URI: http://github.com/empatogen/woocommerce-xrp
 * Description: A payment gateway for WooCommerce to accept <a href="https://ripple.com/xrp">XRP</a> payments.
 * Version: 1.0.2
 * Author: Jesper Wallin
 * Author URI: https://ifconfig.se/
 * Developer: Jesper Wallin
 * Developer URI: https://ifconfig.se/
 * Text Domain: wc-gateway-xrp
 * Domain Path: /languages/
 *
 * WC requires at least: 3.5.6
 * WC tested up to: 3.5.6
 *
 * Copyright: © 2019 Jesper Wallin.
 * License: ISC license
 */

defined( 'ABSPATH' ) or die( 'Nothing to see here' );

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

include_once dirname( __FILE__ ) . '/includes/class-webhooks.php';
include_once dirname( __FILE__ ) . '/includes/class-rates.php';


/**
 * Load translations.
 */
add_action( 'plugins_loaded', 'wc_gateway_xrp_load_text_domain' );
function wc_gateway_xrp_load_text_domain() {
    load_plugin_textdomain( 'wc-gateway-xrp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}


/**
 * XRP Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Gateway_XRP
 * @extends     WC_Payment_Gateway
 * @version     1.0.2
 * @package     WooCommerce/Classes/Payment
 * @author      Jesper Wallin
 */
add_action( 'plugins_loaded', 'wc_gateway_xrp_init', 11 );
function wc_gateway_xrp_init() {

    class WC_Gateway_XRP extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                    = 'xrp';
            $this->has_fields            = false;
            $this->method_title          = __( 'XRP', 'wc-gateway-xrp' );
            $this->method_description    = __( 'Let your customers pay using the XRP Ledger.', 'wc-gateway-xrp' );

            $this->init_settings();

            $this->title                 = $this->settings['title'];
            $this->description           = $this->settings['description'];
            $this->xrp_account           = $this->settings['xrp_account'];
            $this->xrpl_webhook_api_pub  = $this->settings['xrpl_webhook_api_pub'];
            $this->xrpl_webhook_api_priv = $this->settings['xrpl_webhook_api_priv'];
            $this->xrp_node              = $this->settings['xrp_node'];
            $this->tx_limit              = $this->settings['tx_limit'];

            add_action( 'woocommerce_api_wc_gateway_xrp', array( $this, 'check_ledger' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            if ( ! is_admin() ) {
                return true;
            }

            if ( empty( $this->settings['xrp_account'] ) || empty( $this->settings['xrpl_webhook_api_pub'] ) || empty( $this->settings['xrpl_webhook_api_priv'] ) ) {
                 add_action( 'admin_notices', array( $this, 'require_xrp' ) );
            } elseif ( $this->check_webhooks() === false ) {
                 add_action( 'admin_notices', array( $this, 'invalid_xrp' ) );
            }

            if ( ! in_array( get_woocommerce_currency(), array( 'EUR', 'USD' ) ) ) {
                add_action( 'admin_notices', array( $this, 'supported_currencies' ) );
            }


            $this->init_form_fields();
        }


        /**
         * Display an error that the current currency is unsupported.
         */
        public function supported_currencies() {
            _e( '<div class="notice notice-error"><p>Your current currency is not supported yet. Please use <b>USD</b> or <b>EUR</b> with for now.</p></div>', 'wc-gateway-xrp' );
        }


        /**
         * Display an error that all XRP related data is required.
         */
        public function require_xrp() {
            _e( '<div class="notice notice-error"><p>Before you can use the XRP payment gateway, you <b>must</b> specify a <b>XRP Account</b> and your <b>XRPL Webhook</b> details.</p></div>', 'wc-gateway-xrp' );
        }


        /**
         * Display an error that the XRP details is invalid.
         */
        public function invalid_xrp() {
            _e( '<div class="notice notice-error"><p>The specified <b>XRP Account</b> and/or <b>XRPL Webhook</b> details are invalid. Please correct these for the <b>XRP Payment Gateway</b> to work properly.</p></div>', 'wc-gateway-xrp' );
        }


        /**
         * Save settings and reload.
         */
        public function process_admin_options() {
            parent::process_admin_options();

            wp_redirect( $_SERVER['REQUEST_URI'] );
            exit;
        }


        /**
         * Check and make sure the webhook and subscription exists.
         */
        public function check_webhooks() {
            if ( empty( $this->settings['xrpl_webhook_api_pub'] ) || empty( $this->settings['xrpl_webhook_api_priv']  ) ) {
                return false;
            }

            $wh = new Webhook( $this->settings['xrpl_webhook_api_pub'], $this->settings['xrpl_webhook_api_priv'] );

            /* webhooks */
            if ( ( $hooks = $wh->webhooks() ) === false ) {
                return false;
            }
            $url = WC()->api_request_url( 'WC_Gateway_XRP' );
            $exists = false;
            foreach ( $hooks as $hook ) {
                if ( $hook->url == $url ) {
                    $exists = true;
                    break;
                }
            }
            if ($exists === false && $wh->add_webhook( $url ) === false ) {
                return false;
            }

            /* subscriptions */
            if ( ( $subs = $wh->subscriptions() ) === false ) {
                return false;
            }
            $exists = false;
            foreach ( $subs as $sub ) {
                if ( $sub->address == $this->settings['xrp_account'] ) {
                    return true;
                }
            }
            if ($exists === false && $wh->add_subscription( $this->settings['xrp_account'] ) === false ) {
                return false;
            }

            return true;
        }


        /**
         * Return our XRP account.
         */
        public function get_xrp_account() {
            return $this->xrp_account;
        }


        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'wc_xrp_form_fields', array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-xrp' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable XRP Payments', 'wc-gateway-xrp' ),
                    'default' => 'no'
                ),

                'title' => array(
                    'title'       => __( 'Title', 'wc-gateway-xrp' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-xrp' ),
                    'default'     => __( 'XRP', 'wc-gateway-xrp' ),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __( 'Description', 'wc-gateway-xrp' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-xrp' ),
                    'default'     => __( 'Payment instruction will be shown once you\'ve placed your order.', 'wc-gateway-xrp' ),
                    'desc_tip'    => true,
                ),

                'xrp' => array(
                    'title'       => __( 'XRP Account', 'wc-gateway-xrp' ),
                    'type'        => 'title',
                    'description' => __( 'Please specify the XRP Ledger account where your payments should be sent. This should be an account <b>YOU</b> own and should <b>NOT</b> be an exchange account, since a unique destination tag is generated for each order.', 'wc-gateway-xrp' ),
                ),

                'xrp_account' => array(
                    'title'       => __( 'XRP Account', 'wc-gateway-xrp' ),
                    'type'        => 'text',
                    'description' => __( 'Your XRP account where payments should be sent.', 'wc-gateway-xrp' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'xrpl_webhook' => array(
                    'title'       => __( 'XRPL Webhook options', 'wc-gateway-xrp' ),
                    'type'        => 'title',
                    'description' => __( 'In order to create your webhook and process your payments properly, please specify your XRPL Webhooks API key. For more informations how to obtain these keys, please visit <a href="https://webhook.xrpayments.co/">https://webhook.xrpayments.co</a>.', 'wc-gateway-xrp' ),
                ),

                'xrpl_webhook_api_pub' => array(
                    'title'       => __( 'API Key', 'wc-gateway-xrp' ),
                    'type'        => 'text',
                    'description' => __( 'Your XRPL XRPayments Webhook API key.', 'wc-gateway-xrp' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'xrpl_webhook_api_priv' => array(
                    'title'       => __( 'API Secret', 'wc-gateway-xrp' ),
                    'type'        => 'text',
                    'description' => __( 'Your XRPL XRPayments Webhook API secret.', 'wc-gateway-xrp' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'advanced' => array(
                    'title'       => __( 'Advanced', 'wc-gateway-xrp' ),
                    'type'        => 'title',
                    'description' => __( 'Leave these untouched unless you really know what you\'re doing.', 'wc-gateway-xrp' ),
                ),

                'xrp_node' => array(
                    'title'       => __( 'XRP Node', 'wc-gateway-xrp' ),
                    'type'        => 'text',
                    'description' => __( 'Which XRP node to use when checking our balance.', 'wc-gateway-xrp' ),
                    'default'     => 'https://s2.ripple.com:51234',
                    'desc_tip'    => true,
                ),

                'xrp_bypass' => array(
                    'title'       => __( 'Bypass firewall', 'wc-gateway-xrp' ),
                    'type'        => 'checkbox',
                    'label'   => __( 'Use a proxy to bypass your webservers firewall.', 'wc-gateway-xrp' ),
                    'description' => 'This is useful if your webserver does not allow outbound traffic on non-standard ports.',
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),

                'exchange' => array(
                    'title'       => __( 'Exchange', 'wc-gateway-xrp' ),
                    'type'        => 'select',
                    'description' => __( 'Which exchange to use when fetching the XRP rate.', 'wc-gateway-xrp' ),
                    'options'     => array(
                        'binance'  => 'Binance',
                        'bitfinex' => 'Bitfinex',
                        'bitmex'   => 'BitMEX',
                        'bitstamp' => 'Bitstamp Ltd',
                        'bittrex'  => 'Bittrex',
                        'kraken'   => 'Kraken',
                    ),
                    'default'     => 'bitstamp',
                    'desc_tip'    => true,
                ),

                'tx_limit' => array(
                    'title'       => __( 'Transaction Limit', 'wc-gateway-xrp' ),
                    'type'        => 'number',
                    'description' => __( 'The number of transactions to fetch from the ledger each time we check for new payments.', 'wc-gateway-xrp' ),
                    'default'     => 10,
                    'desc_tip'    => true,
                ),
            ) );
        }


        /**
         * Process the order and calculate the price in XRP.
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            /* specity where to obtain our rates from. */
            $rates = new Rates( $order->get_currency() );
            $rate  = $rates->get_rate( $this->settings['exchange'] );

            if ( $rate === false ) {
                return false;
            }

            /* calculate the amount in XRP. */
            $xrp = round( ceil( ( $order->get_total() / $rate ) * 1000000 ) / 1000000, 6);

            /* try to get the destination tag as random as possible. */
            if ( function_exists( 'random_int' ) ) {
                $tag = random_int( 1, 4294967295 );
            } else {
                $tag = mt_rand( 1, 4294967295 );
            }

            /**
             * make sure the tag hasn't been used already,
             * if so, bail out and have the user try again.
             */
            $orders = wc_get_orders( array( 'destination_tag' => $tag ) );
            if ( ! empty( $orders ) ) {
                return false;
            }

            $order->add_meta_data( 'total_amount', $xrp );
            $order->add_meta_data( 'destination_tag', $tag );
            $order->add_meta_data( 'delivered_amount', '0' );
            $order->add_meta_data( 'xrp_rate', $rate );
            $order->save_meta_data();

            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }


        /**
         * Parse the most recent transactions and match them against our orders.
         */
        public function check_ledger() {
            $node = $this->get_option( 'xrp_node' );
            if ( empty( $node ) ) {
                $node = 'https://s2.ripple.com:51234';
            }

            $account = $this->get_option( 'xrp_account' );
            if ( empty( $account ) ) {
                echo "no account specified";
                exit;
            }

            $limit = (int)$this->get_option( 'tx_limit' );
            if ( $limit === 0 ) {
                $limit = 10;
            }

            $payload = json_encode( [
                'method' => 'account_tx',
                'params' => [[
                    'account' => $account,
                    'ledger_index_min' => -1,
                    'ledger_index_max' => -1,
                    'limit' => $limit,
                ]]
            ] );

            $bypass  = $this->get_option( 'xrp_bypass' );
            $headers = array();
            if ( $bypass == 'yes' ) {
                $node = sprintf( 'https://cors-anywhere.herokuapp.com/%s', $node );
                $headers = array( 'origin' => get_site_url() );
            }

            $res = wp_remote_post( $node, array( 'body' => $payload, 'headers' => $headers ) );
            if ( is_wp_error( $res ) || $res['response']['code'] !== 200 || ( $ledger = json_decode( $res['body'] ) ) == null ) {
                echo "unable to reach the XRP ledger.";
                exit;
            }
            $rev = array_reverse( $ledger->result->transactions );

            foreach ( $rev as $tx ) {
                if ( $tx->tx->TransactionType != 'Payment' || $tx->tx->Destination != $account || !isset( $tx->tx->DestinationTag ) || $tx->tx->DestinationTag == 0 || !isset( $tx->meta->delivered_amount ) ) {
                    continue;
                }
                $orders = wc_get_orders( array( 'destination_tag' => $tx->tx->DestinationTag ) );
                if ( empty( $orders ) ) {
                    continue;
                }

                /* keep track of the sequence number */
                $seq = $orders[0]->get_meta( 'last_sequence' );
                if ( $seq != '' && $tx->tx->Sequence <= (int)$seq ) {
                    continue;
                }
                $orders[0]->update_meta_data( 'last_sequence', $tx->tx->Sequence );

                /* store the tx hash */
                $txlist = $orders[0]->get_meta( 'tx' );
                if ( empty($txlist) ) {
                    $txlist = array();
                } else {
                    $txlist = explode( ',', $txlist );
                }
                if ( ! in_array( $tx->tx->hash, $txlist ) ) {
                    array_push( $txlist, $tx->tx->hash );
                }
                $orders[0]->update_meta_data( 'tx', implode( ',', $txlist ) );

                /* update the delivered_amount */
                $delivered_amount = $orders[0]->get_meta( 'delivered_amount' );
                $delivered_amount += $tx->meta->delivered_amount / 1000000;
                $orders[0]->update_meta_data( 'delivered_amount', $delivered_amount );
                $orders[0]->save_meta_data();

                $total_amount = $orders[0]->get_meta( 'total_amount' );

                if ( $delivered_amount >= $total_amount ) {
                    $orders[0]->update_status( 'processing', __( sprintf( '%s XRP received', $delivered_amount ), 'wc-gateway-xrp' ) );
                    $orders[0]->reduce_order_stock();
                }
            }

            echo "ok";
            exit;
        }
    }
}


/**
 * Add the XRP payment gateway.
 */
function wc_xrp_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_XRP';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_xrp_add_to_gateways' );


/**
 * Add custom meta_query so we can search by destination_tag.
 */
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'handle_destination_tag_query', 10, 2 );
function handle_destination_tag_query( $query, $query_vars ) {
    if ( ! empty( $query_vars['destination_tag'] ) ) {
        $query['meta_query'][] = array(
            'key' => 'destination_tag',
            'value' => esc_attr( $query_vars['destination_tag'] ),
        );
    }

    return $query;
}


/*
 * Customize the "thank you" page in order to display payment info.
 */
add_action( 'woocommerce_thankyou', 'thankyou_xrp_payment_info', 10 );
add_action( 'woocommerce_view_order', 'thankyou_xrp_payment_info', 10 );
function thankyou_xrp_payment_info( $order_id ) {
    $gateway = new WC_Gateway_XRP;
    $remaining = round( (float)get_post_meta( $order_id, 'total_amount', true ) - (float)get_post_meta( $order_id, 'delivered_amount', true ) , 6 );
 ?>
    <h2><?php _e( 'XRP payment details', 'wc-gateway-xrp' ); ?></h2>
    <div class="xrp_qr_container">
        <?php if ( get_post_status( $order_id ) == 'wc-pending' ) { ?>
        <img id="xrp_qr" src="<?php echo xrp_qr( $gateway->settings['xrp_account'], get_post_meta( $order_id, 'destination_tag', true ), $remaining ) ?>">
        <?php } ?>
    </div>
    <table class="woocommerce-table shop_table xrp_info">
        <tbody>
            <tr>
                <th><?php _e( 'XRP Account', 'wc-gateway-xrp' ); ?></th>
                <td id="xrp_account"><?php echo _x( $gateway->settings['xrp_account'] , 'wc-gateway-xrp' ) ?></td>
            </tr>
            <tr>
                <th><?php _e( 'Destination tag', 'wc-gateway-xrp' ); ?></th>
                <td id="destination_tag"><?php echo get_post_meta( $order_id, 'destination_tag', true ) ?></td>
            </tr>
            <tr>
                <th><?php _e( 'XRP total', 'wc-gateway-xrp' ); ?></th>
                <td id="xrp_total"><?php echo round( get_post_meta( $order_id, 'total_amount', true ), 6 ) ?></td>
            </tr>
            <tr>
                <th><?php _e( 'XRP received', 'wc-gateway-xrp' ); ?></th>
                <td id="xrp_received"><?php echo round( get_post_meta( $order_id, 'delivered_amount', true ), 6 ) ?></td>
            </tr>
            <tr>
                <th><?php _e( 'XRP left to pay', 'wc-gateway-xrp' ); ?></th>
                <td id="xrp_remaining"><?php echo $remaining ?></td>
            </tr>
            <tr>
                <th><?php _e( 'Order status', 'wc-gateway-xrp' ); ?></th>
                <td id="xrp_status"><?php echo wc_pretty_status( get_post_status( $order_id ) ) ?></td>
            </tr>
        </tbody>
    </table>
    <?php

    if (get_post_status( $order_id ) == 'wc-pending' ) {
        wp_enqueue_script( 'ajax-script', plugins_url( '/js/checkout.js', __FILE__ ), array( 'jquery' ) );
        wp_localize_script( 'ajax-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'order_id' => $order_id ) );
    }
}


/**
 * Handle the AJAX callback to reload checkout details.
 */
add_action( 'wp_ajax_xrp_checkout', 'xrp_checkout_handler' );
add_action( 'wp_ajax_nopriv_xrp_checkout', 'xrp_checkout_handler' );
function xrp_checkout_handler() {
    $order = wc_get_order( $_POST['order_id'] );

    if ( $order == false ) {
        header( 'HTTP/1.0 404 Not Found' );
        wp_die();
    }

    $gateway      = new WC_Gateway_XRP;
    $tag          = get_post_meta( $_POST['order_id'], 'destination_tag', true );
    $xrp_total    = round( get_post_meta( $_POST['order_id'], 'total_amount', true ), 6 );
    $xrp_received = round( get_post_meta( $_POST['order_id'], 'delivered_amount', true ), 6 );
    $remaining    = round( (float)$xrp_total - (float)$xrp_received , 6 );
    $status       = get_post_status( $_POST['order_id'] );

    $result = array(
        'xrp_account'   => $gateway->settings['xrp_account'],
        'tag'           => $tag,
        'xrp_total'     => $xrp_total,
        'xrp_received'  => $xrp_received,
        'xrp_remaining' => $remaining,
        'status'        => wc_pretty_status( $status ),
        'qr'            => xrp_qr( $gateway->settings['xrp_account'], $tag, $remaining ),
        'raw_status'    => $status
    );

    echo json_encode($result);
    wp_die();
}


/**
 * Generate a QR-code for the XRP payment.
 */
function xrp_qr( $account, $tag, $amount ) {
    $data = sprintf(
        'https://ripple.com/send?to=%s&dt=%s&amount=%s',
        $account,
        $tag,
        $amount
    );
    return sprintf(
        'https://chart.googleapis.com/chart?chs=256x256&cht=qr&chld=M|0&chl=%s&choe=UTF-8',
        urlencode( $data )
    );
}


/**
 * Ugly helper to print pretty statuses.
 */
function wc_pretty_status( $status ) {
    switch ( $status ) {
        case 'wc-pending':
            return 'Pending payment';
        case 'wc-processing':
            return 'Processing (Paid)';
        case 'wc-on-hold':
            return 'On hold';
        case 'wc-completed':
            return 'Completed';
        case 'wc-cancelled':
            return 'Cancelled';
        case 'wc-refunded':
            return 'Refunded';
        case 'wc-failed':
            return 'Failed';
        default:
            return 'Unknown';
    }
}

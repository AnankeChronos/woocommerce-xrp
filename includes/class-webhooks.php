<?php

class Webhook {

    protected $pub;
    protected $secret;
    public $base = 'https://webhook.xrpayments.co/';
    public $error = null;


    public function __construct( $pub, $secret ) {
        $this->pub = $pub;
        $this->secret = $secret;
    }


    public function subscriptions() {
        if ( empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }

        $headers = array(
            'x-api-key: ' . $this->pub,
            'x-api-secret: ' . $this->secret
        );

        $curl = new Curl( $this->base . 'api/v1/subscriptions', $headers );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $res = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        return $res->subscriptions;
    }


    public function add_subscription( $address ) {
        if ( empty( $address ) || empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }

        $headers = array(
            'Content-Type: application/json; charset=utf-8',
            'x-api-key: ' . $this->pub,
            'x-api-secret: ' . $this->secret
        );
        $payload = json_encode( array( 'address' => trim( $address ) ) );

        $curl = new Curl( $this->base . 'api/v1/subscriptions', $headers );
        if ( $curl->post( $payload ) === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $res = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        return $res->subscription_id;
    }


    public function webhooks() {
        if ( empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }

        $headers = array(
            'x-api-key: ' . $this->pub,
            'x-api-secret: ' . $this->secret
        );

        $curl = new Curl( $this->base . 'api/v1/webhooks', $headers );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $res = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        return $res->webhooks;
    }


    public function add_webhook( $url ) {
        if ( empty( $url ) || empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }

        $headers = array(
            'Content-Type: application/json; charset=utf-8',
            'x-api-key: ' . $this->pub,
            'x-api-secret: ' . $this->secret
        );
        $payload = json_encode( array( 'url' => trim( $url ) ) );

        $curl = new Curl( $this->base . 'api/v1/webhooks', $headers );
        if ( $curl->post( $payload ) === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $res = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        return $res->webhook_id;
    }
}

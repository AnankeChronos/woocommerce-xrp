<?php

class Webhook {

    protected $pub;
    protected $secret;
    public $base = 'https://webhook.xrpayments.co/api/v1/';
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
            'x-api-key' => $this->pub,
            'x-api-secret' => $this->secret
        );
        $res = wp_remote_get( $this->base . 'subscriptions', array( 'headers' => $headers ) );
        if ( is_wp_error( $res ) || $res['response']['code'] !== 200 || ( $data = json_decode( $res['body'] ) ) == null ) {
            return false;
        }

        return $data->subscriptions;
    }


    public function add_subscription( $address ) {
        if ( empty( $address ) || empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'x-api-key' => $this->pub,
            'x-api-secret' => $this->secret
        );
        $payload = json_encode( array( 'address' => trim( $address ) ) );

        $res = wp_remote_post( $this->base . 'subscriptions', array(
            'headers' => $headers,
            'body' => $payload
        ) );
        if ( is_wp_error( $res ) || $res['response']['code'] !== 200 || ( $data = json_decode( $res['body'] ) ) == null ) {
            return false;
        }

        return $data->subscription_id;
    }


    public function webhooks() {
        if ( empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }
        $headers = array(
            'x-api-key' => $this->pub,
            'x-api-secret' => $this->secret
        );
        $res = wp_remote_get( $this->base . 'webhooks', array( 'headers' => $headers ) );
        if ( is_wp_error( $res ) || $res['response']['code'] !== 200 || ( $data = json_decode( $res['body'] ) ) == null ) {
            return false;
        }

        return $data->webhooks;
    }


    public function add_webhook( $url ) {
        if ( empty( $url ) || empty( $this->pub ) || empty( $this->secret ) ) {
            return false;
        }

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'x-api-key' => $this->pub,
            'x-api-secret' => $this->secret
        );
        $payload = json_encode( array( 'url' => trim( $url ) ) );
        $res = wp_remote_post( $this->base . 'webhooks', array(
            'headers' => $headers,
            'body' => $payload
        ) );
        if ( is_wp_error( $res ) || $res['response']['code'] !== 200 || ( $data = json_decode( $res['body'] ) ) == null ) {
            return false;
        }

        return $data->webhook_id;
    }
}

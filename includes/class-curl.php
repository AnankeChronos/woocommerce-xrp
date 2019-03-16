<?php

class Curl {

    private $ch = false;
    public $data = null;
    public $info = [];
    public $error = false;

    public function __construct( $url, $headers = null ) {
        if ( $this->has_curl() == false ) {
            return false;
        }

        $this->ch = curl_init( $url );

        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $this->ch, CURLOPT_HEADER, false );

        if ( ! is_null( $headers ) ) {
            $this->headers( $headers );
        }
    }


    public function has_curl() {
        if ( ! function_exists( 'curl_init' ) ) {
            return false;
        }

        return true;
    }


    public function headers( $headers ) {
        if ( $this->ch == false || !is_array( $headers ) ) {
            return false;
        }

        return curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $headers );
    }


    public function execute() {
        if ( $this->ch == false ) {
            return false;
        }

        $this->data = curl_exec( $this->ch );
        $this->info = curl_getinfo( $this->ch );
        $this->error = curl_error( $this->ch );
        curl_close( $this->ch );

        if ($this->data === false) {
            return false;
        }

        return true;
    }


    public function get() {
        if ( $this->ch == false ) {
            return false;
        }

        curl_setopt( $this->ch, CURLOPT_HTTPGET, true );

        return $this->execute();
    }


    public function post( $data = null ) {
        if ( $this->ch == false ) {
            return false;
        }

        curl_setopt( $this->ch, CURLOPT_POST, true );

        if ( ! is_null( $data ) ) {
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $data );
        }

        return $this->execute();
    }
}

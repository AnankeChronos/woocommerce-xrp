<?php

class Rates {
    public function __construct( $base_currency ) {
        $this->base_currency = strtoupper($base_currency);
    }


    public function eurusd() {
        $curl = new Curl( 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' );
        if ( $curl->get() === false ) {
            return false;
        }

        /* use regex instead of SimpleXML to have less dependencies */
        if ( ! preg_match( "/\<Cube currency='USD' rate='([^']+)'\/\>/", $curl->data, $match ) ) {
            return false;
        }

        return $match[1];
    }


    public function get_rate( $exchange ) {
        switch ($exchange) {
            case 'bitstamp':
                $rate = $this->bitstamp();
                break;
            case 'binance':
                $rate = $this->binance();
                break;
            case 'bitfinex':
                $rate = $this->bitfinex();
                break;
            case 'bittrex':
                $rate = $this->bittrex();
                break;
            case 'bitmex':
                $rate = $this->bitmex();
                break;
            case 'kraken':
                $rate = $this->kraken();
                break;
        }

        # in case the exchange is unreachable, try a different one.
        if ( $rate === false ) {
            if ($exchange !== 'bitstamp' && ( $rate = $this->bitstamp() ) !== false ) {
                return $rate;
            } elseif ( $exchange === 'bitstamp' && ( $rate = $this->kraken() ) !== false ) {
                return $rate;
            }

            return false;
        }

        return $rate;
    }


    private function bitstamp() {
        if ( $this->base_currency === 'EUR' ) {
            $url = 'https://www.bitstamp.net/api/v2/ticker/xrpeur/';
        } elseif ( $this->base_currency === 'USD' ) {
            $url = 'https://www.bitstamp.net/api/v2/ticker/xrpusd/';
        } else {
            return false;
        }
        $curl = new Curl( $url );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        return (float)$rate->last;
    }


    private function kraken() {
        if ( $this->base_currency === 'EUR' ) {
            $url = 'https://api.kraken.com/0/public/Ticker?pair=XRPEUR';
        } elseif ( $this->base_currency === 'USD' ) {
            $url = 'https://api.kraken.com/0/public/Ticker?pair=XRPUSD';
        } else {
            return false;
        }
        $curl = new Curl( $url );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        foreach ($rate->result as $rate) {
            return (float)$rate->c[0];
        };

        return false;
    }


    private function bitfinex() {
        $curl = new Curl( 'https://api.bitfinex.com/v1/pubticker/xrpusd' );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        if ( $this->base_currency === 'USD' ) {
            return (float)$rate->last_price;
        } elseif ( $this->base_currency === 'EUR' && ( $usd = $this->eurusd() ) !== false ) {
            return (float)( $rate->last_price / $usd );
        } else {
            return false;
        }
    }


    private function bittrex() {
        $curl = new Curl( 'https://api.bittrex.com/api/v1.1/public/getticker?market=USD-XRP' );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        if ( $this->base_currency === 'USD' ) {
            return (float)$rate->result->Last;
        } elseif ( $this->base_currency === 'EUR' && ( $usd = $this->eurusd() ) !== false ) {
            return (float)( $rate->result->Last / $usd );
        } else {
            return false;
        }
    }


    private function bitmex() {
        $curl = new Curl( 'https://www.bitmex.com/api/v1/orderBook/L2?symbol=xbt&depth=1' );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        $btc = $rate[0]->price;

        $curl = new Curl( 'https://www.bitmex.com/api/v1/orderBook/L2?symbol=xrp&depth=1' );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        if ( $this->base_currency === 'USD' ) {
            return (float)( $btc * $rate[0]->price );
        } elseif ( $this->base_currency === 'EUR' && ( $usd = $this->eurusd() ) !== false ) {
            return (float)( ( $btc * $rate[0]->price) / $usd );
        } else {
            return false;
        }
    }


    private function binance() {
        $curl = new Curl( 'https://api.binance.com/api/v3/ticker/price?symbol=XRPUSDT' );
        if ( $curl->get() === false ) {
            return false;
        }

        if ( $curl->info['http_code'] !== 200 || ( $rate = json_decode( $curl->data ) ) == null ) {
            return false;
        }

        if ( $this->base_currency === 'USD' ) {
            return (float)$rate->price;
        } elseif ( $this->base_currency === 'EUR' && ( $usd = $this->eurusd() ) !== false ) {
            return (float)( $rate->price / $usd );
        } else {
            return false;
        }
    }
}

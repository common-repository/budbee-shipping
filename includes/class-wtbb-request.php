<?php
namespace includes;

class WTBB_Request{

    public static function get_zipcode_availability( $instance_id, $zipcode ) {

        return self::get( '/postalcodes/validate/' . $zipcode, $instance_id );
    }

    public static function get( $endpoint, $instance_id ) {
        $options = get_option( 'woocommerce_budbee_' . $instance_id . '_settings' );

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $options[  'budbee_api_key' ] . ':' . $options[ 'budbee_api_secret' ] ),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            )
        );
        $response = wp_remote_get( $options[ 'budbee_url' ] . $endpoint , $args );
        return json_decode( wp_remote_retrieve_body( $response ) );
    }
}


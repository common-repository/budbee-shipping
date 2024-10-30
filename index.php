<?php
/*
Plugin Name: Budbee Shipping
Plugin URI: https://wetail.se
Description: First class shipping in Stockholm, Gothenburg and Malmoe.
Author: Wetail
Version: 1.0
Author URI: https://wetail.se
*/
define( 'WTBB_NAME', basename(dirname(__DIR__)));
define( 'WTBB_URL', plugins_url(WTBB_NAME) );
define( 'WTBB', WTBB_NAME );

require_once "autoload.php";

use includes\WTBB_Request;

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function budbee_shipping_method_init() {
        if ( ! class_exists( 'WC_Budbee_Shipping_Method' ) ) {
            class WC_Budbee_Shipping_Method extends WC_Shipping_Method {

                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct( $instance_id=0 ) {
                    $this->id                 = 'budbee'; // Id for your shipping method. Should be uunique.
                    $this->instance_id        = $instance_id;
                    $this->method_title       = __( 'Budbee' );
                    $this->method_description = __( 'Budbee Hemleverans' );
                    $this->enabled            = "yes";
                    $this->title              = "Budbee";
                    $this->init();
                    $this->supports =  array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

                }

                /**
                 * Is this method available?
                 * @param array $package
                 * @return bool
                 */
                public function is_available( $package ) {

                    $statuses =  array(
                        'KO' => false,
                        'OK' => true
                    );

                    if( array_key_exists('destination',  $package ) ){
                        if( array_key_exists('postcode',  $package['destination'] ) ){
                            if( in_array( substr($package['destination']['postcode'] ,0, 1 ), [ '1', '2', '4'] ) ){
                                if( get_transient( 'budbee_' . $package['destination']['postcode'] ) ){
                                    return $statuses[ get_transient( 'budbee_' . $package['destination']['postcode'] ) ];
                                }
                                $response = WTBB_Request::get_zipcode_availability( $this->instance_id, $package['destination']['postcode'] );
                                set_transient( 'budbee_' . $package['destination']['postcode'] , $response->status, 86400 );
                                return $statuses[ $response->status ];

                            }
                        }
                    }
                }

                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package = array() ) {
                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $this->get_package_weight( $package ),
                        'calc_tax' => 'per_item'
                    );

                    // Register the rate
                    $this->add_rate( $rate );

                }

                /**
                 * Calculate the total package weight
                 */
                function get_package_weight( $package = array() ){
                    $total_weight = 0;

                    // Add up weight of each product
                    if ( sizeof( $package['contents'] ) > 0 ) {
                        foreach ( $package['contents'] as $item_id => $values ) {
                            if ( $values['data']->has_weight() ) {
                                $products_weight = $values['data']->get_weight() * $values['quantity'];
                                $total_weight = $total_weight + $products_weight;
                            }
                        }
                    }

                    if ( $total_weight < 3 ){
                        return $this->instance_settings['cost_2'];
                    }
                    elseif ( $total_weight < 5 ){
                        return $this->instance_settings['cost_4'];
                    }
                    elseif ( $total_weight < 8 ){
                        return $this->instance_settings['cost_7'];
                    }
                    elseif ( $total_weight < 11 ){
                        return $this->instance_settings['cost_10'];
                    }
                    elseif ( $total_weight < 16 ){
                        return $this->instance_settings['cost_15'];
                    }
                    elseif ( $total_weight < 21 ){
                        return $this->instance_settings['cost_20'];
                    }
                    elseif ( $total_weight < 26 ){
                        return $this->instance_settings['cost_25'];
                    }
                    elseif ( $total_weight < 31 ){
                        return $this->instance_settings['cost_30'];
                    }
                    elseif ( $total_weight < 41 ){
                        return $this->instance_settings['cost_40'];
                    }
                    elseif ( $total_weight < 51 ){
                        return $this->instance_settings['cost_50'];
                    }
                }


                public function process_admin_options() {
                    if ( $this->instance_id ) {
                        $this->init_instance_settings();

                        $post_data = $this->get_post_data();
                        error_log(print_r($this->instance_settings ,true));

                        foreach ( $this->get_instance_form_fields() as $key => $field ) {
                            if ( 'title' !== $this->get_field_type( $field ) ) {
                                try {
                                    $this->instance_settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
                                } catch ( Exception $e ) {
                                    $this->add_error( $e->getMessage() );
                                }
                            }
                        }

                        return update_option( $this->get_instance_option_key(), apply_filters( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', $this->instance_settings, $this ) );
                    } else {
                        return parent::process_admin_options();
                    }
                }

                function init_form_fields()
                {
                    $this->form_fields = array(
                        'budbee_url' => array(
                            'type'          => 'select',
                            'options'       => [
                                '' => __('Select Budbee mode...', WTBB),
                                'https://sandbox.api.budbee.com' => 'Sandbox',
                                'https://api.budbee.com' => 'Live'
                            ],
                            'description'   => __('This will define if this is a Budbee Live or Test mode', WTBB),
                            'default'       => '',
                            'desc_tip'      => true
                        ),
                        'budbee_api_key' => array(
                            'type'              => 'text',
                            'placeholder'       => __('Budbee API key', WTBB),
                            'description'       => '',
                            'custom_attributes' => [
                                'title' => __('Budbee API key', WTBB)
                            ],
                            'default'           => '',
                            'desc_tip'          => false
                        ),
                        'budbee_api_secret' => array(
                            'type'              => 'text',
                            'placeholder'       => __('Budbee API secret', WTBB),
                            'custom_attributes' => [
                                'title' => __('Budbee API secret', WTBB)
                            ],
                            'description'       => __('To get the API key / secret, please visit ', WTBB) .
                                '<a href="https://budbee.com/register-customer" 
                                            target="_blank">Budbee</a>',
                            'default'           => '',
                            'desc_tip'          => false
                        ),
                        'description' => array(
                            'title' => __('Description', 'woocommerce'),
                            'type' => 'text',
                            'placeholder' => __('A few words about this shipping method', WTBB),
                            'description' => __('Description will appear in the checkout shipping methods listing', WTBB),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'tax_status' => array(
                            'title' 		=> __( 'Tax status', 'woocommerce' ),
                            'type' 			=> 'select',
                            'class'         => 'wc-enhanced-select',
                            'default' 		=> 'taxable',
                            'options'		=> array(
                                'taxable' 	=> __( 'Taxable', 'woocommerce' ),
                                'none' 		=> _x( 'None', 'Tax status', 'woocommerce' ),
                            ),
                        ),
                        'cost_2' => array(
                            'title' 		=> __( 'Cost 1-2 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_4' => array(
                            'title' 		=> __( 'Cost 3-4 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_7' => array(
                            'title' 		=> __( 'Cost 5-7 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_10' => array(
                            'title' 		=> __( 'Cost 8-10 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_15' => array(
                            'title' 		=> __( 'Cost 11-15 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_20' => array(
                            'title' 		=> __( 'Cost 16-20 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_25' => array(
                            'title' 		=> __( 'Cost 21-25 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_30' => array(
                            'title' 		=> __( 'Cost 26-30 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_40' => array(
                            'title' 		=> __( 'Cost 31-40 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_50' => array(
                            'title' 		=> __( 'Cost 41-50 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                    );
                }

                function init_settings()
                {
                    $this->instance_form_fields = array(
                        'budbee_url' => array(
                            'type'          => 'select',
                            'options'       => [
                                '' => __('Select Budbee mode...', WTBB),
                                'https://sandbox.api.budbee.com' => 'Sandbox',
                                'https://api.budbee.com' => 'Live'
                            ],
                            'description'   => __('This will define if this is a Budbee Live or Test mode', WTBB),
                            'default'       => '',
                            'desc_tip'      => true
                        ),
                        'budbee_api_key' => array(
                            'type'              => 'text',
                            'placeholder'       => __('Budbee API key', WTBB),
                            'description'       => '',
                            'custom_attributes' => [
                                'title' => __('Budbee API key', WTBB)
                            ],
                            'default'           => '',
                            'desc_tip'          => false
                        ),
                        'budbee_api_secret' => array(
                            'type'              => 'text',
                            'placeholder'       => __('Budbee API secret', WTBB),
                            'custom_attributes' => [
                                'title' => __('Budbee API secret', WTBB)
                            ],
                            'description'       => __('To get the API key / secret, please visit ', WTBB) .
                                '<a href="https://budbee.com/register-customer" 
                                            target="_blank">Budbee</a>',
                            'default'           => '',
                            'desc_tip'          => false
                        ),
                        'description' => array(
                            'title' => __('Description', 'woocommerce'),
                            'type' => 'text',
                            'placeholder' => __('A few words about this shipping method', WTBB),
                            'description' => __('Description will appear in the checkout shipping methods listing', WTBB),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'tax_status' => array(
                            'title' 		=> __( 'Tax status', 'woocommerce' ),
                            'type' 			=> 'select',
                            'class'         => 'wc-enhanced-select',
                            'default' 		=> 'taxable',
                            'options'		=> array(
                                'taxable' 	=> __( 'Taxable', 'woocommerce' ),
                                'none' 		=> _x( 'None', 'Tax status', 'woocommerce' ),
                            ),
                        ),
                        'cost_2' => array(
                            'title' 		=> __( 'Cost 1-2 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_4' => array(
                            'title' 		=> __( 'Cost 3-4 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_7' => array(
                            'title' 		=> __( 'Cost 5-7 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_10' => array(
                            'title' 		=> __( 'Cost 8-10 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_15' => array(
                            'title' 		=> __( 'Cost 11-15 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_20' => array(
                            'title' 		=> __( 'Cost 16-20 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_25' => array(
                            'title' 		=> __( 'Cost 21-25 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_30' => array(
                            'title' 		=> __( 'Cost 26-30 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_40' => array(
                            'title' 		=> __( 'Cost 31-40 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),
                        'cost_50' => array(
                            'title' 		=> __( 'Cost 41-50 kg', 'woocommerce' ),
                            'type' 			=> 'text',
                            'placeholder'	=> '',
                            'description'	=> '',
                            'default'		=> '0',
                            'desc_tip'		=> true,
                        ),

                    );
                }
            }
        }
    }

    add_action( 'woocommerce_shipping_init', 'budbee_shipping_method_init' );

    function add_budbee_shipping_method( $methods ) {
        $methods['budbee'] = 'WC_Budbee_Shipping_Method';
        return $methods;
    }

    add_filter( 'woocommerce_shipping_methods', 'add_budbee_shipping_method' );
}
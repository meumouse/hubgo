<?php

namespace MeuMouse\Hubgo\Integrations;

defined('ABSPATH') || exit;

/**
 * Modular integration registry.
 *
 * Third parties can register their own HubGo integrations via the
 * `Hubgo/Integrations/Registered` filter. Each entry must be a class name; the
 * class decides internally whether its dependency is available.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Integration_Registry {

    /**
     * Instantiated integrations.
     *
     * @var array<string,object>
     */
    protected $integrations = array();


    /**
     * Constructor: boot all registered integrations.
     *
     * @since 3.0.0
     */
    public function __construct() {
        $classes = apply_filters( 'Hubgo/Integrations/Registered', array(
            Joinotify::class,
            Melhor_Envio::class,
            Frenet::class,
            Woo_Shipment_Tracking::class,
            Elementor::class,
        ) );

        foreach ( (array) $classes as $class ) {
            if ( is_string( $class ) && class_exists( $class ) && ! isset( $this->integrations[ $class ] ) ) {
                $this->integrations[ $class ] = new $class();
            }
        }

        do_action( 'Hubgo/Integrations/Loaded', $this->integrations );
    }


    /**
     * Get instantiated integrations.
     *
     * @since 3.0.0
     * @return array<string,object>
     */
    public function get_integrations() {
        return $this->integrations;
    }
}

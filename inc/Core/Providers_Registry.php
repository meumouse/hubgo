<?php

namespace MeuMouse\Hubgo\Core;

defined('ABSPATH') || exit;

/**
 * Class Providers_Registry
 *
 * Manages the list of shipping providers and their tracking URL formats.
 *
 * Providers are grouped by country/region and each provider value is an URL format
 * supporting sprintf placeholders (e.g. %1$s for tracking number).
 *
 * @since 2.1.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Providers_Registry {

	/**
	 * Custom provider key.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	const CUSTOM_PROVIDER_KEY = 'Custom';

	/**
	 * Get providers list grouped by country/region.
	 *
	 * @since 2.1.0
	 * @return array
	 */
	public static function get_providers() {
		$providers = array(
			'Brazil' => array(
				'Correios' => 'https://www.linkcorreios.com.br/?id=%1$s',
				'Jadlog' => 'https://www.jadlog.com.br/siteInstitucional/tracking.jad?cte=%1$s',
				'LATAM Cargo' => 'https://www.latamcargo.com/pt/track/%1$s',
				'Azul Cargo' => 'https://www.azulcargoexpress.com.br/rastreio/%1$s',
				'Total Express' => 'https://tracking.totalexpress.com.br/pedidos/%1$s',
				'Loggi' => 'https://www.loggi.com/rastreador/%1$s',
				'J&T Express' => 'https://www.jtexpress.com.br/track?billcode=%1$s',
				'Buslog' => 'https://www.buslog.com.br/rastreio/%1$s',
				'Rede Sul' => 'https://www.redesul.com.br/rastreamento/%1$s',
				'Sequoia' => 'https://rastreamento.sequoialog.com.br/%1$s',
				'Braspress' => 'https://www.braspress.com/rastrear/%1$s',
				'TNT Mercúrio' => 'https://www.tnt.com/express/pt_br/site/shipping-tools/tracking.html?searchType=con&cons=%1$s',
				'FedEx' => 'https://www.fedex.com/fedextrack/?tracknumbers=%1$s&locale=pt_BR',
				'DHL Express' => 'https://www.dhl.com/br-pt/home/tracking.html?tracking-id=%1$s',
				'Via Brasil' => 'https://www.viabrasil.com.br/rastreio/%1$s',

				// Custom provider (manual URL)
				self::CUSTOM_PROVIDER_KEY => '',
			),
            'Global'         => array(
                'Aramex' => 'https://www.aramex.com/track/track-results-new?ShipmentNumber=%1$s',
            ),
            'Australia'      => array(
                'Australia Post'   => 'https://auspost.com.au/mypost/track/#/details/%1$s',
                'Fastway Couriers' => 'https://www.fastway.com.au/tools/track/?l=%1$s',
                'Aramex Australia' => 'https://www.aramex.com.au/tools/track?l=%1$s',
            ),
            'Austria'        => array(
                'post.at' => 'https://www.post.at/sv/sendungsdetails?snr=%1$s',
                'dhl.at'  => 'https://www.dhl.at/content/at/de/express/sendungsverfolgung.html?brand=DHL&AWB=%1$s',
                'DPD.at'  => 'https://tracking.dpd.de/parcelstatus?locale=de_AT&query=%1$s',
            ),
            'Belgium'        => array(
                'bpost' => 'https://track.bpost.be/btr/web/#/search?itemCode=%1$s&postalCode=%2$s',
            ),
            'Canada'         => array(
                'Canada Post' => 'https://www.canadapost-postescanada.ca/track-reperage/en#/resultList?searchFor=%1$s',
                'Purolator'   => 'https://www.purolator.com/purolator/ship-track/tracking-summary.page?pin=%1$s',
            ),
            'Czech Republic' => array(
                'PPL.cz'      => 'https://www.ppl.cz/main2.aspx?cls=Package&idSearch=%1$s',
                'Česká pošta' => 'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo?parcelNumbers=%1$s',
                'DHL.cz'      => 'https://www.dhl.cz/cs/express/sledovani_zasilek.html?AWB=%1$s',
                'DPD.cz'      => 'https://tracking.dpd.de/parcelstatus?locale=cs_CZ&query=%1$s',
            ),
            'Finland'        => array(
                'Itella' => 'https://www.posti.fi/itemtracking/posti/search_by_shipment_id?lang=en&ShipmentId=%1$s',
            ),
            'France'         => array(
                'Colissimo' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=%1$s',
            ),
            'Germany'        => array(
                'DHL Intraship (DE)' => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=%1$s&rfn=&extendedSearch=true',
                'Hermes'             => 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#%1$s',
                'Deutsche Post DHL'  => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=%1$s',
                'UPS Germany'        => 'https://wwwapps.ups.com/WebTracking?sort_by=status&tracknums_displayed=1&TypeOfInquiryNumber=T&loc=de_DE&InquiryNumber1=%1$s',
                'DPD.de'             => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=en_DE',
            ),
            'Ireland'        => array(
                'DPD.ie'  => 'https://dpd.ie/tracking?deviceType=5&consignmentNumber=%1$s',
                'An Post' => 'https://track.anpost.ie/TrackingResults.aspx?rtt=1&items=%1$s',
            ),
            'Italy'          => array(
                'BRT (Bartolini)' => 'https://vas.brt.it/vas/sped_det_show.hsm?Nspediz=%1$s',
                'DHL Express'     => 'https://www.dhl.it/it/express/ricerca.html?AWB=%1$s&brand=DHL',
            ),
            'India'          => array(
                'DTDC' => 'https://www.dtdc.in/tracking/tracking_results.asp?Ttype=awb_no&strCnno=%1$s&TrkType2=awb_no',
            ),
            'Netherlands'    => array(
                'PostNL'          => 'https://postnl.nl/tracktrace/?B=%1$s&P=%2$s&D=%3$s&T=C',
                'DPD.NL'          => 'https://tracking.dpd.de/status/en_US/parcel/%1$s',
                'UPS Netherlands' => 'https://wwwapps.ups.com/WebTracking?sort_by=status&tracknums_displayed=1&TypeOfInquiryNumber=T&loc=nl_NL&InquiryNumber1=%1$s',
            ),
            'New Zealand'    => array(
                'Courier Post'       => 'https://trackandtrace.courierpost.co.nz/Search/%1$s',
                'NZ Post'            => 'https://www.nzpost.co.nz/tools/tracking?trackid=%1$s',
                'Aramex New Zealand' => 'https://www.aramex.co.nz/tools/track?l=%1$s',
                'PBT Couriers'       => 'http://www.pbt.com/nick/results.cfm?ticketNo=%1$s',
            ),
            'Poland'         => array(
                'InPost'        => 'https://inpost.pl/sledzenie-przesylek?number=%1$s',
                'DPD.PL'        => 'https://tracktrace.dpd.com.pl/parcelDetails?p1=%1$s',
                'Poczta Polska' => 'https://emonitoring.poczta-polska.pl/?numer=%1$s',
            ),
            'Romania'        => array(
                'Fan Courier'   => 'https://www.fancourier.ro/awb-tracking/?tracking=%1$s',
                'DPD Romania'   => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=ro_RO',
                'Urgent Cargus' => 'https://app.urgentcargus.ro/Private/Tracking.aspx?CodBara=%1$s',
            ),
            'South African'  => array(
                'SAPO'    => 'http://sms.postoffice.co.za/TrackingParcels/Parcel.aspx?id=%1$s',
                'Fastway' => 'https://fastway.co.za/our-services/track-your-parcel?l=%1$s',
                'EPX'     => 'https://epx.pperfect.com/?w=%1$s',
            ),
            'Sweden'         => array(
                'PostNord Sverige AB' => 'https://portal.postnord.com/tracking/details/%1$s',
                'DHL.se'              => 'https://www.dhl.com/se-sv/home/tracking.html?submit=1&tracking-id=%1$s',
                'Bring.se'            => 'https://tracking.bring.se/tracking/%1$s',
                'UPS.se'              => 'https://www.ups.com/track?loc=sv_SE&tracknum=%1$s&requester=WT/',
                'DB Schenker'         => 'http://privpakportal.schenker.nu/TrackAndTrace/packagesearch.aspx?packageId=%1$s',
            ),
            'United Kingdom' => array(
                'DHL'                       => 'https://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=%1$s',
                'DPD.co.uk'                 => 'https://www.dpd.co.uk/apps/tracking/?reference=%1$s#results',
                'DPD Local'                 => 'https://apis.track.dpdlocal.co.uk/v1/track?postcode=%2$s&parcel=%1$s',
                'EVRi'                      => 'https://www.evri.com/track/parcel/%1$s',
                'EVRi (international)'      => 'https://international.evri.com/tracking/%1$s',
                'ParcelForce'               => 'https://www7.parcelforce.com/track-trace?trackNumber=%1$s',
                'Royal Mail'                => 'https://www.royalmail.com/track-your-item?trackNumber=%1$s',
                'TNT Express (consignment)' => 'https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=con&cons=%1$s',
                'TNT Express (reference)'   => 'https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=ref&cons=%1$s',
                'DHL Parcel UK'             => 'https://track.dhlparcel.co.uk/?con=%1$s',
            ),
            'United States'  => array(
                'DHL US'        => 'https://www.logistics.dhl/us-en/home/tracking/tracking-ecommerce.html?tracking-id=%1$s',
                'DHL eCommerce' => 'https://webtrack.dhlecs.com/orders?trackingNumber=%1$s',
                'Fedex'         => 'https://www.fedex.com/apps/fedextrack/?action=track&action=track&tracknumbers=%1$s',
                'FedEx Sameday' => 'https://www.fedexsameday.com/fdx_dotracking_ua.aspx?tracknum=%1$s',
                'GlobalPost'    => 'https://www.goglobalpost.com/track-detail/?t=%1$s',
                'OnTrac'        => 'https://www.ontrac.com/tracking/?number=%1$s',
                'UPS'           => 'https://www.ups.com/track?loc=en_US&tracknum=%1$s',
                'USPS'          => 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=%1$s',
            ),
		);

		/**
		 * Filter providers list.
		 *
		 * @since 2.1.0
		 *
		 * @param array $providers Providers grouped by country/region.
		 * @return array
		 */
		return apply_filters( 'Hubgo/Tracking/Get_Providers', $providers );
	}


	/**
	 * Get translated label for country/region key.
	 *
	 * Keeps internal keys stable while allowing translated labels in UI.
	 *
	 * @since 2.1.0
	 * @param string $country Country/region key.
	 * @return string
	 */
	public static function get_country_label( $country ) {
		$labels = array(
			'Brazil'         => __( 'Brazil', 'hubgo' ),
			'Global'         => __( 'Global', 'hubgo' ),
			'Australia'      => __( 'Australia', 'hubgo' ),
			'Austria'        => __( 'Austria', 'hubgo' ),
			'Belgium'        => __( 'Belgium', 'hubgo' ),
			'Canada'         => __( 'Canada', 'hubgo' ),
			'Czech Republic' => __( 'Czech Republic', 'hubgo' ),
			'Finland'        => __( 'Finland', 'hubgo' ),
			'France'         => __( 'France', 'hubgo' ),
			'Germany'        => __( 'Germany', 'hubgo' ),
			'Ireland'        => __( 'Ireland', 'hubgo' ),
			'Italy'          => __( 'Italy', 'hubgo' ),
			'India'          => __( 'India', 'hubgo' ),
			'Netherlands'    => __( 'Netherlands', 'hubgo' ),
			'New Zealand'    => __( 'New Zealand', 'hubgo' ),
			'Poland'         => __( 'Poland', 'hubgo' ),
			'Romania'        => __( 'Romania', 'hubgo' ),
			'South African'  => __( 'South African', 'hubgo' ),
			'Sweden'         => __( 'Sweden', 'hubgo' ),
			'United Kingdom' => __( 'United Kingdom', 'hubgo' ),
			'United States'  => __( 'United States', 'hubgo' ),
		);

		return $labels[ $country ] ?? $country;
	}


	/**
	 * Get provider URL format by country and provider name.
	 *
	 * @since 2.1.0
	 *
	 * @param string $country Country/region key (e.g. "Brazil").
	 * @param string $provider Provider name (e.g. "Correios").
	 * @return string
	 */
	public static function get_provider_url_format( $country, $provider ) {

		$providers = self::get_providers();

		if ( isset( $providers[ $country ] ) && isset( $providers[ $country ][ $provider ] ) ) {
			return (string) $providers[ $country ][ $provider ];
		}

		// Fallback: search in all countries if not found.
		foreach ( $providers as $group => $list ) {
			if ( isset( $list[ $provider ] ) ) {
				return (string) $list[ $provider ];
			}
		}

		return '';
	}


	/**
	 * Build provider tracking URL.
	 *
	 * Supports custom provider URL (manual) and sprintf placeholders.
	 *
	 * Default placeholders:
	 * - %1$s: tracking number (URL encoded)
	 *
	 * You can customize placeholder values via the filter "Hubgo/Tracking/provider_url_values".
	 *
	 * @since 2.1.0
	 *
	 * @param string $provider Provider name (e.g. "Correios" or "Custom").
	 * @param string $tracking_number Tracking number.
	 * @param string $custom_url Custom URL (used when provider is "Custom").
	 * @param string $country Country/region key (optional, recommended).
	 * @param int    $order_id Order ID (optional, useful for filters).
	 * @return string
	 */
	public static function get_tracking_url( $provider, $tracking_number, $custom_url = '', $country = 'Brazil', $order_id = 0 ) {
		$provider = (string) $provider;

		// Custom provider uses manual URL.
		if ( self::CUSTOM_PROVIDER_KEY === $provider && ! empty( $custom_url ) ) {
			return esc_url( $custom_url );
		}

		$url_format = self::get_provider_url_format( $country, $provider );

		if ( empty( $url_format ) ) {
			return '';
		}

		$values = array(
			1 => urlencode( (string) $tracking_number ),
		);

		/**
		 * Filter placeholder values for provider URL.
		 *
		 * @since 2.1.0
		 *
		 * @param array  $values Placeholder values indexed by position.
		 * @param string $provider Provider name.
		 * @param string $tracking_number Tracking number.
		 * @param string $country Country/region key.
		 * @param int    $order_id Order ID.
		 * @return array
		 */
		$values = apply_filters(
			'Hubgo/Tracking/Provider_Url_Values',
			$values,
			$provider,
			$tracking_number,
			$country,
			(int) $order_id
		);

		// Ensure sequential arguments for sprintf.
		$args = array_values( $values );
		$url = vsprintf( $url_format, $args );

		/**
		 * Filter final tracking URL.
		 *
		 * @since 2.1.0
		 *
		 * @param string $url Final URL.
		 * @param string $provider Provider name.
		 * @param string $tracking_number Tracking number.
		 * @param string $country Country/region key.
		 * @param int    $order_id Order ID.
		 * @return string
		 */
		return apply_filters(
			'Hubgo/Tracking/Tracking_Url',
			$url,
			$provider,
			$tracking_number,
			$country,
			(int) $order_id
		);
	}
}
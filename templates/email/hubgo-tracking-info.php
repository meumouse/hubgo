<?php
/**
 * Email tracking info (HTML).
 *
 * @package Hubgo
 */

defined('ABSPATH') || exit;
?>
<h2><?php esc_html_e( 'Tracking information', 'hubgo' ); ?></h2>

<table cellspacing="0" cellpadding="6" border="1" style="width:100%; border-collapse: collapse; margin-bottom: 20px;" bordercolor="#e5e5e5">
    <thead>
        <tr>
            <th scope="col" style="text-align:left;"><?php esc_html_e( 'Carrier', 'hubgo' ); ?></th>
            <th scope="col" style="text-align:left;"><?php esc_html_e( 'Code', 'hubgo' ); ?></th>
            <th scope="col" style="text-align:left;"><?php esc_html_e( 'Shipping date', 'hubgo' ); ?></th>
            <th scope="col" style="text-align:left;"><?php esc_html_e( 'Link', 'hubgo' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $items as $item ) : ?>
            <tr>
                <td style="text-align:left; vertical-align:middle;"><?php echo esc_html( $item['provider'] ); ?></td>
                <td style="text-align:left; vertical-align:middle;"><strong><?php echo esc_html( $item['tracking_number'] ); ?></strong></td>
                <td style="text-align:left; vertical-align:middle;"><?php echo ! empty( $item['ship_date'] ) ? esc_html( $item['ship_date'] ) : '&ndash;'; ?></td>
                <td style="text-align:left; vertical-align:middle;">
                    <?php if ( ! empty( $item['url'] ) ) : ?>
                        <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Track shipment', 'hubgo' ); ?></a>
                    <?php else : ?>
                        &ndash;
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

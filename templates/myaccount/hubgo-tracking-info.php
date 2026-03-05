<?php
/**
 * My Account tracking info.
 *
 * @package Hubgo
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="hubgo-tracking-section">
    <h2><?php esc_html_e( 'Informacoes de rastreio', 'hubgo' ); ?></h2>

    <?php foreach ( $items as $item ) : ?>
        <p>
            <strong><?php echo esc_html( $item['provider'] ); ?></strong><br>
            <?php esc_html_e( 'Codigo de rastreio:', 'hubgo' ); ?>
            <strong><?php echo esc_html( $item['tracking_number'] ); ?></strong>

            <?php if ( ! empty( $item['ship_date'] ) ) : ?>
                <br><?php esc_html_e( 'Data de envio:', 'hubgo' ); ?>
                <?php echo esc_html( $item['ship_date'] ); ?>
            <?php endif; ?>

            <?php if ( ! empty( $item['url'] ) ) : ?>
                <br><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Rastrear envio', 'hubgo' ); ?>
                </a>
            <?php endif; ?>
        </p>
    <?php endforeach; ?>
</section>

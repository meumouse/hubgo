<?php

namespace MeuMouse\Hubgo\Admin\Views\Settings\Components;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Fields
 *
 * Base class for form field rendering
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Components
 * @author MeuMouse.com
 */
class Fields {

    /**
     * Render field wrapper
     *
     * @since 2.0.0
     * @param string $label Field label
     * @param string $description Field description
     * @param string $content Field content
     * @return void
     */
    public static function wrapper( $label, $description, $content ) {
        ?>
        <tr>
            <th scope="row">
                <?php echo esc_html( $label ); ?>
                <?php if ( ! empty( $description ) ) : ?>
                    <span class="hubgo-field-description"><?php echo esc_html( $description ); ?></span>
                <?php endif; ?>
            </th>
            <td>
                <?php echo $content; ?>
            </td>
        </tr>
        <?php
    }
}
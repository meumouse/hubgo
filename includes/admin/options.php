<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; ?>

<div class="hubgo-shipping-management-wc-general-options mt-5">
  <table class="form-table">
      <tr>
        <th>
           <?php echo esc_html__( 'Ativar calculadora de frete', 'hubgo-shipping-management-wc' ) ?>
           <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__('Ative esta opção para adicionar uma calculadora de frete na página de produto individual.', 'hubgo-shipping-management-wc' ) ?></span>
        </th>
        <td>
           <div class="form-check form-switch">
              <input type="checkbox" class="toggle-switch" id="enable_shipping_calculator" name="enable_shipping_calculator" value="yes" <?php checked( self::get_setting( 'enable_shipping_calculator' ) == 'yes' ); ?> />
           </div>
        </td>
      </tr>
      <tr>
        <th>
           <?php echo esc_html__( 'Ativar cálculo automático de frete', 'hubgo-shipping-management-wc' ) ?>
           <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__('Ative esta opção para que o frete seja calculado de forma automática.', 'hubgo-shipping-management-wc' ) ?></span>
        </th>
        <td>
           <div class="form-check form-switch">
              <input type="checkbox" class="toggle-switch" id="enable_auto_shipping_calculator" name="enable_auto_shipping_calculator" value="yes" <?php checked( self::get_setting( 'enable_auto_shipping_calculator' ) == 'yes' ); ?> />
           </div>
        </td>
      </tr>
      <tr>
        <th>
           <?php echo esc_html__( 'Posição da calculadora de frete', 'hubgo-shipping-management-wc' ) ?>
           <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Selecione onde o gancho que será exibido as formas de pagamento na página de produto individual. Shortcode disponível: [hubgo_shipping_calculator]', 'hubgo-shipping-management-wc' ) ?></span>
       </th>
        <td>
           <select name="hook_display_shipping_calculator" class="form-select">
              <option value="after_cart" <?php echo ( self::get_setting( 'hook_display_shipping_calculator' ) == 'after_cart' ) ? "selected=selected" : ""; ?>><?php echo esc_html__( 'Depois do carrinho (Padrão)', 'hubgo-shipping-management-wc' ) ?></option>
              <option value="before_cart" <?php echo ( self::get_setting( 'hook_display_shipping_calculator' ) == 'before_cart' ) ? "selected=selected" : ""; ?>><?php echo esc_html__( 'Antes do carrinho', 'hubgo-shipping-management-wc' ) ?></option>
              <option value="meta_end" <?php echo ( self::get_setting( 'hook_display_shipping_calculator' ) == 'meta_end' ) ? "selected=selected" : ""; ?>><?php echo esc_html__( 'Final das informações adicionais', 'hubgo-shipping-management-wc' ) ?></option>
              <option value="shortcode" <?php echo ( self::get_setting( 'hook_display_shipping_calculator' ) == 'shortcode' ) ? "selected=selected" : ""; ?>><?php echo esc_html__( 'Shortcode', 'hubgo-shipping-management-wc' ) ?></option>
           </select>
        </td>
     </tr>
     <tr>
         <th>
            <?php echo esc_html__( 'Cor principal', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Selecione a cor principal para botões e outros estilos.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="color" name="primary_main_color" class="form-control-color" value="<?php echo self::get_setting( 'primary_main_color' ) ?>"/>
         </td>
      </tr>
      <tr>
         <th>
            <?php echo esc_html__( 'Texto informativo antes do campo de CEP', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Deixe em branco para não exibir.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="text" class="form-control input-control-wd-20" name="text_info_before_input_shipping_calc" value="<?php echo self::get_setting( 'text_info_before_input_shipping_calc' ) ?>"/>
         </td>
      </tr>
      <tr>
         <th>
            <?php echo esc_html__( 'Texto do botão da calculadora de frete', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Deixe em branco para não exibir.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="text" class="form-control input-control-wd-20" name="text_button_shipping_calc" value="<?php echo self::get_setting( 'text_button_shipping_calc' ) ?>"/>
         </td>
      </tr>
      <tr>
         <th>
            <?php echo esc_html__( 'Texto do cabeçalho das formas de entrega', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Deixe em branco para não exibir.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="text" class="form-control input-control-wd-20" name="text_header_ship" value="<?php echo self::get_setting( 'text_header_ship' ) ?>"/>
         </td>
      </tr>
      <tr>
         <th>
            <?php echo esc_html__( 'Texto do cabeçalho do valor das formas de entrega', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Deixe em branco para não exibir.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="text" class="form-control input-control-wd-20" name="text_header_value" value="<?php echo self::get_setting( 'text_header_value' ) ?>"/>
         </td>
      </tr>
      <tr>
         <th>
            <?php echo esc_html__( 'Texto do espaço reservado do campo de CEP', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Deixe em branco para não exibir.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="text" class="form-control input-control-wd-20" name="text_placeholder_input_shipping_calc" value="<?php echo self::get_setting( 'text_placeholder_input_shipping_calc' ) ?>"/>
         </td>
      </tr>
      <tr>
         <th>
            <?php echo esc_html__( 'Texto de observação inferior das opções de frete', 'hubgo-shipping-management-wc' ) ?>
            <span class="hubgo-shipping-management-wc-description"><?php echo esc_html__( 'Deixe em branco para não exibir.', 'hubgo-shipping-management-wc' ) ?></span>
         </th>
         <td>
            <input type="text" class="form-control input-control-wd-20" name="note_text_bottom_shipping_calc" value="<?php echo self::get_setting( 'note_text_bottom_shipping_calc' ) ?>"/>
         </td>
      </tr>
  </table>
</div>
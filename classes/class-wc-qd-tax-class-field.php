<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

class WC_QD_Tax_Class_Field {

  const TAX_CLASSES = array( '' => '– Not applicable –', 
    'eservice' => 'e-Service', 
    'ebook' => 'e-Book', 
    'saas' => 'SaaS', 
    'consulting' => 'Consulting',
    'standard' => 'Standard', 
    'reduced' => 'Reduced', 
    'exempt' => 'Tax-exempt'
  );

  const DIGITAL_TAX_CLASSES = array('eservice', 'ebook', 'saas');

  /**
   * The setup method
   */
  public function setup() {
    // Product data metabox
    add_action( 'woocommerce_product_options_tax', array( $this, 'quaderno_product_options_tax' ) );
    add_action( 'woocommerce_process_product_meta', array( $this, 'quaderno_save_fields' ), 10, 2 );

    // Quick edition hooks
    add_action( 'woocommerce_product_quick_edit_end', array( $this, 'quaderno_product_quick_edit' ), 10, 2);
    add_action( 'woocommerce_product_quick_edit_save', array( $this, 'quaderno_product_quick_edit_save'), 10, 1);
    add_action( 'manage_product_posts_custom_column', array( $this, 'quaderno_populate_tax_class_columns'), 10, 2);
  } 

  /**
   * Set the tax class meta for products that were created with version 1.x
   */
  public function init_tax_class_meta() {
    $product_id = get_the_ID();
    if ( !metadata_exists('post', $product_id, '_quaderno_tax_class' ) ) {
      $product = wc_get_product( $product_id );

      if ( 'none' === $product->get_tax_status() ) {
        update_post_meta( $product_id, '_quaderno_tax_class', 'exempt' );
      } elseif ( 'yes' === get_post_meta( $product_id, '_ebook', true ) ) {
        update_post_meta( $product_id, '_quaderno_tax_class', 'ebook' );
      } elseif ( $product->is_virtual() ) {
        update_post_meta( $product_id, '_quaderno_tax_class', 'eservice' );
      }
    }
  } 

  /**
   * Show the Quaderno tax class field in the product metadata box
   */
  public function quaderno_product_options_tax(){

    // compatibility with version 1.x
    self::init_tax_class_meta();   
    
    echo '<div class="options_group">';

    woocommerce_wp_select(
      array(
        'id'          => '_quaderno_tax_class',
        'value'       => get_post_meta( get_the_ID(), '_quaderno_tax_class', true ),
        'label'       => __( 'Quaderno tax class', 'woocommerce-quaderno' ),
        'options'     => self::TAX_CLASSES,
        'desc_tip' => true,
        'description' => 'Select an option if you want Quaderno to calculate taxes for this product.'
      )
    );

    echo '</div>';
  }

  /**
   * Save the Quaderno tax class in the product metadata box
   */
  public function quaderno_save_fields( $id, $post ){
   
    if( isset( $_POST['_quaderno_tax_class'] ) ) {
      update_post_meta( $id, '_quaderno_tax_class', $_POST['_quaderno_tax_class'] );
    } 
   
  }

  /**
   * Show the Quaderno tax class field in the quick edition box
   */
  public function quaderno_product_quick_edit() {
    ?>
    <br class="clear" />
    <label class="alignleft">
      <span class="title"><?php esc_html_e('Quaderno tax class', 'woocommerce-quaderno' ); ?></span>
      <span class="input-text-wrap">
        <select class="quaderno_tax_class" name="_quaderno_tax_class">
        <?php
          foreach( self::TAX_CLASSES as $key => $value ) {
            echo "<option value='$key'>$value</option>";
          }
        ?>
        </select>
      </span>
    </label>
    <br class="clear" />
    <?php
  }

  /**
   * Save the Quaderno tax class in the quick edition box
   */
  public function quaderno_product_quick_edit_save( $product ) {
    $product_id = $product->get_id();

    if ( isset( $_REQUEST['_quaderno_tax_class'] ) ) {
      $customFieldDemo = trim(esc_attr( $_REQUEST['_quaderno_tax_class'] ));
      update_post_meta( $product_id, '_quaderno_tax_class', wc_clean( $customFieldDemo ) );
    }
  }

  /*
  * Populate the tax class column in the products list
  */
  public function quaderno_populate_tax_class_columns( $column_name, $post_id ) {
 
    switch( $column_name ) :
      case 'name': {
        ?>
        <div class="hidden quaderno_tax_class_inline" id="quaderno_tax_class_inline_<?php echo $post_id; ?>">
            <div id="quaderno_tax_class"><?php echo get_post_meta($post_id, '_quaderno_tax_class', true); ?></div>
        </div>
      <?php
        break;
      }
    endswitch;
 
  }
}

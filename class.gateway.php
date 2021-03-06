<?php
	if ( !defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( class_exists( 'WC_Payment_Gateway' ) ) {

		class GazChap_WC_PurchaseOrder_Gateway extends WC_Payment_Gateway {

			public $instructions;
			public $status;
			public $ask_po_number;
			public $require_po_number;
			public $ask_address;
			public $use_billing_address;

			public function __construct() {
				$this->id                 = 'gazchap_wc_purchaseordergateway';
				$this->icon               = '';
				$this->has_fields         = true;
				$this->method_title       = __( 'Purchase Order', 'gazchap-wc-purchase-order-gateway' );
				$this->method_description = __( 'Allows customers to request an invoice, and supply a Purchase Order number (with optional invoicing address) at checkout', 'gazchap-wc-purchase-order-gateway' );

				$this->init_form_fields();
				$this->init_settings();

				$this->description  = $this->get_option( 'description' );
				$this->instructions = $this->get_option( 'instructions', $this->description );

				$this->title = $this->get_option( 'title' );
				$this->status = $this->get_option( 'status' );

				$this->ask_po_number = ( $this->get_option( 'ask_po_number' ) == 'yes' ) ? true : false;
				$this->require_po_number = ( $this->get_option( 'require_po_number' ) == 'yes' ) ? true : false;
				$this->ask_address = ( $this->get_option( 'ask_address' ) == 'yes' ) ? true : false;
				$this->use_billing_address = ( $this->get_option( 'use_billing_address' ) == 'yes' ) ? true : false;

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_order_meta' ), 10, 1 );
				add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_instructions' ) );
				add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 3 );
			}

			public function init_form_fields() {
				$this->form_fields = apply_filters( 'wc_offline_form_fields', array(
					'enabled' => array(
						'title'   => __( 'Enable/Disable', 'gazchap-wc-purchase-order-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Purchase Order Payment Gateway for WooCommerce', 'gazchap-wc-purchase-order-gateway' ),
						'default' => 'no'
					),
					'title' => array(
						'title'       => __( 'Title', 'gazchap-wc-purchase-order-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the customer sees during checkout.', 'gazchap-wc-purchase-order-gateway' ),
						'default'     => __( 'Purchase Order', 'gazchap-wc-purchase-order-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'gazchap-wc-purchase-order-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description which the customer sees during checkout.', 'gazchap-wc-purchase-order-gateway' ),
						'default'     => __( 'We will send the invoice for this order to the address you supply.', 'gazchap-wc-purchase-order-gateway' ),
						'desc_tip'    => true,
					),
					'instructions' => array(
						'title'       => __( 'Instructions', 'gazchap-wc-purchase-order-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'Instructions that will be added to the thank you page and emails.', 'gazchap-wc-purchase-order-gateway' ),
						'default'     => __( 'We will send the invoice for this order to the address you supply.', 'gazchap-wc-purchase-order-gateway' ),
						'desc_tip'    => true,
					),
					'status' => array(
						'title'   => __( 'Initial Order Status', 'gazchap-wc-purchase-order-gateway' ),
						'type'    => 'select',
						'options' => array(
							'on-hold' => 'On Hold',
							'processing' => 'Processing'
						 ),
						'description' => __( 'What should an order be marked as when first received?', 'gazchap-wc-purchase-order-gateway' ),
						'desc_tip'    => true,
					),
					'ask_po_number' => array(
						'title'   => __( 'Ask for PO Number', 'gazchap-wc-purchase-order-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Ask the customer for a purchase order number during checkout', 'gazchap-wc-purchase-order-gateway' ),
						'default' => 'yes'
					),
					'require_po_number' => array(
						'title'   => __( 'Require PO Number', 'gazchap-wc-purchase-order-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Require a purchase order number to be input during checkout', 'gazchap-wc-purchase-order-gateway' ),
						'default' => 'yes'
					),
					'ask_address' => array(
						'title'   => __( 'Ask for Address', 'gazchap-wc-purchase-order-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Ask the customer for an address to send the invoice to', 'gazchap-wc-purchase-order-gateway' ),
						'default' => 'no'
					),
					'use_billing_address' => array(
						'title'   => __( 'Use Billing Address', 'gazchap-wc-purchase-order-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Pre-fill the Invoice Address with the customer\'s billing address, if they are logged in', 'gazchap-wc-purchase-order-gateway' ),
						'default' => 'no'
					),
				) );
			}

			public function thankyou_instructions() {
				if ( !empty( $this->instructions ) ) {
					echo wpautop( $this->instructions );
				}
			}

			public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
				if ( !empty( $this->instructions ) && !$sent_to_admin && $order->payment_method == $this->id && $order->has_status( $this->status ) ) {
					if ( $plain_text ) {
						echo strip_tags( $this->instructions ) . PHP_EOL;
					} else {
						echo wpautop( $this->instructions ) . PHP_EOL;
					}
				}
			}

			public function process_payment( $order_id ) {

				$order = wc_get_order( $order_id );
				$order->update_status( $this->status, 'Awaiting invoice payment from purchase order.' );
				wc_reduce_stock_levels( $order_id );

				if ( isset( $_POST['gazchap_purchase_order'] ) && is_array( $_POST['gazchap_purchase_order'] ) ) {
					$meta = array();
					$fields = array( "number", "contact", "company", "address1", "address2", "city", "county", "postcode" );
					foreach( $fields as $field ) {
						if ( !empty( $_POST['gazchap_purchase_order'][$field] ) ) {
							$meta[$field] = sanitize_text_field( $_POST['gazchap_purchase_order'][$field] );
						}
					}

					update_post_meta( $order_id, '_gazchap_purchase_order', $meta );
				}

				WC()->cart->empty_cart();

				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);

			}

			public function payment_fields(){
				$current_user = wp_get_current_user();
				$meta = array();
				$billing_name = "";
				if ( 0 < $current_user->ID ) {
					$meta = get_user_meta( $current_user->ID );
					if ( !empty( $meta['billing_first_name'][0] ) ) $billing_name .= $meta['billing_first_name'][0] . " ";
					if ( !empty( $meta['billing_last_name'][0] ) ) $billing_name .= $meta['billing_last_name'][0] . " ";
					$billing_name = trim( $billing_name );
				}
				?>

			<?php if ( $this->ask_po_number ): ?>
				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-number"><?php esc_html_e('Purchase Order Number', 'gazchap-wc-purchase-order-gateway'); ?><?php if ( $this->require_po_number ): ?> <span class="required">*</span><?php endif; ?></label>
					<input type="text" id="gc-wc-popg-number" name="gazchap_purchase_order[number]" class="input-text">
				</p>
			<?php endif; ?>

			<?php if ( $this->ask_address ): ?>
				<p class="form-row form-row-wide"><strong><?php esc_html_e('Where would you like us to send the invoice?', 'gazchap-wc-purchase-order-gateway'); ?></strong></p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-contact"><?php esc_html_e('Contact Name', 'gazchap-wc-purchase-order-gateway'); ?> <span class="required">*</span></label>
					<input type="text" id="gc-wc-popg-contact" name="gazchap_purchase_order[contact]" class="input-text"<?php if ( $this->use_billing_address && !empty( $billing_name ) ): ?> value="<?php echo esc_attr( $billing_name ); ?>"<?php endif; ?>>
				</p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-company"><?php esc_html_e('Company/Organisation', 'gazchap-wc-purchase-order-gateway'); ?> <span class="required">*</span></label>
					<input type="text" id="gc-wc-popg-company" name="gazchap_purchase_order[company]" class="input-text"<?php if ( $this->use_billing_address &&!empty( $meta['billing_company'][0] ) ): ?> value="<?php echo esc_attr( $meta['billing_company'][0] ); ?>"<?php endif; ?>>
				</p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-address1"><?php esc_html_e('Address (Line 1)', 'gazchap-wc-purchase-order-gateway'); ?> <span class="required">*</span></label>
					<input type="text" id="gc-wc-popg-address1" name="gazchap_purchase_order[address1]" class="input-text"<?php if ( $this->use_billing_address &&!empty( $meta['billing_address_1'][0] ) ): ?> value="<?php echo esc_attr( $meta['billing_address_1'][0] ); ?>"<?php endif; ?>>
				</p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-address2"><?php esc_html_e('Address (Line 2)', 'gazchap-wc-purchase-order-gateway'); ?></label>
					<input type="text" id="gc-wc-popg-address2" name="gazchap_purchase_order[address2]" class="input-text"<?php if ( $this->use_billing_address &&!empty( $meta['billing_address_2'][0] ) ): ?> value="<?php echo esc_attr( $meta['billing_address_2'][0] ); ?>"<?php endif; ?>>
				</p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-city"><?php esc_html_e('City', 'gazchap-wc-purchase-order-gateway'); ?> <span class="required">*</span></label>
					<input type="text" id="gc-wc-popg-city" name="gazchap_purchase_order[city]" class="input-text"<?php if ( $this->use_billing_address &&!empty( $meta['billing_city'][0] ) ): ?> value="<?php echo esc_attr( $meta['billing_city'][0] ); ?>"<?php endif; ?>>
				</p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-county"><?php esc_html_e('County', 'gazchap-wc-purchase-order-gateway'); ?></label>
					<input type="text" id="gc-wc-popg-county" name="gazchap_purchase_order[county]" class="input-text"<?php if ( $this->use_billing_address &&!empty( $meta['billing_state'][0] ) ): ?> value="<?php echo esc_attr( $meta['billing_state'][0] ); ?>"<?php endif; ?>>
				</p>

				<p class="form-row form-row-wide">
					<label for="gc-wc-popg-postcode"><?php esc_html_e('Postcode', 'gazchap-wc-purchase-order-gateway'); ?> <span class="required">*</span></label>
					<input type="text" id="gc-wc-popg-postcode" name="gazchap_purchase_order[postcode]" class="input-text"<?php if ( $this->use_billing_address &&!empty( $meta['billing_postcode'][0] ) ): ?> value="<?php echo esc_attr( $meta['billing_postcode'][0] ); ?>"<?php endif; ?>>
				</p>
			<?php endif; ?>

				<?php
			}

			public function validate_fields() {
				$valid = true;
				if ( empty( $_POST['gazchap_purchase_order'] ) ) {
					wc_add_notice( __( 'Please complete the Purchase Order details.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
					$valid = false;
				} else {
					$data = $_POST['gazchap_purchase_order'];
					if ( $this->ask_po_number && $this->require_po_number && empty( $data['number'] ) ) {
						wc_add_notice( __( 'Please enter a Purchase Order Number.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
						$valid = false;
					}

					if ( $this->ask_address ) {
						if ( empty( $data['contact'] ) ) {
							wc_add_notice( __( 'Please enter a Contact Name for the invoice address.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
							$valid = false;
						}

						if ( empty( $data['company'] ) ) {
							wc_add_notice( __( 'Please enter a Company/Organisation for the invoice address.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
							$valid = false;
						}

						if ( empty( $data['address1'] ) ) {
							wc_add_notice( __( 'Please enter an Address (line 1) for the invoice address.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
							$valid = false;
						}

						if ( empty( $data['city'] ) ) {
							wc_add_notice( __( 'Please enter a City for the invoice address.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
							$valid = false;
						}

						if ( empty( $data['postcode'] ) ) {
							wc_add_notice( __( 'Please enter a Postcode for the invoice address.', 'gazchap-wc-purchase-order-gateway' ), 'error' );
							$valid = false;
						}
					}
				}

				return $valid;
			}

			function display_order_meta() {
				$order_id = get_the_ID();
				if ( 0 < $order_id ) {
					$meta = maybe_unserialize( get_post_meta( $order_id, '_gazchap_purchase_order', true ) );
					if ( !empty( $meta['number'] ) ) {
					?>
						<div class="wp-clearfix"></div>
						<h3><?php esc_html_e('Purchase Order', 'gazchap-wc-purchase-order-gateway' ); ?></h3>
						<p><strong><?php esc_html_e('PO Number', 'gazchap-wc-purchase-order-gateway' ); ?>:</strong> <?php if ( !empty( $meta['number'] ) ) echo esc_html( $meta['number'] ) . "<br>";?></p>

					<?php if ( !empty( $meta['contact'] ) || !empty( $meta['company'] ) || !empty( $meta['address1'] ) || !empty( $meta['city'] ) ): ?>
						<p>
							<strong><?php esc_html_e('Contact Name', 'gazchap-wc-purchase-order-gateway'); ?>:</strong><br>
							<?php if ( !empty( $meta['contact'] ) ) echo esc_html( $meta['contact'] ) . "<br>"; ?>
						</p>
						<p>
							<strong><?php esc_html_e('Company/Organisation', 'gazchap-wc-purchase-order-gateway'); ?>:</strong><br>
							<?php if ( !empty( $meta['company'] ) ) echo esc_html( $meta['company'] ) . "<br>"; ?>
						</p>
						<p>
							<strong><?php esc_html_e('Address', 'gazchap-wc-purchase-order-gateway'); ?>:</strong><br>
							<?php if ( !empty( $meta['address1'] ) ) echo esc_html( $meta['address1'] ) . "<br>"; ?>
							<?php if ( !empty( $meta['address2'] ) ) echo esc_html( $meta['address2'] ) . "<br>"; ?>
							<?php if ( !empty( $meta['city'] ) ) echo esc_html( $meta['city'] ) . "<br>"; ?>
							<?php if ( !empty( $meta['county'] ) ) echo esc_html( $meta['county'] ) . "<br>"; ?>
							<?php if ( !empty( $meta['postcode'] ) ) echo esc_html( $meta['postcode'] ) . "<br>"; ?>
						</p>
					<?php endif; ?>
					<?php
					}
				}
			}
		}
	}
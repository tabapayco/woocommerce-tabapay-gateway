<?php
/**
 * Admin page for mapping WooCommerce products to tax invoice product IDs.
 *
 * @package Tabapay_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabapay_Invoice_Admin
 */
class Tabapay_Invoice_Admin {

	/**
	 * Option name for storing product to invoice product ID mapping.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'tabapay_invoice_product_map';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adds submenu page under WooCommerce.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'شناسه کالا/خدمت فاکتور تاباپی', 'tabapay-gateway' ),
			__( 'فاکتور تاباپی', 'tabapay-gateway' ),
			'manage_woocommerce',
			'tabapay-invoice-products',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers settings and sanitization.
	 */
	public function register_settings() {
		register_setting(
			'tabapay_invoice_products',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_mapping' ),
			)
		);
	}

	/**
	 * Sanitizes the product-invoice mapping before save.
	 *
	 * @param array|mixed $value Raw posted value.
	 * @return array Sanitized rows of [ product_ids => int[], invoice_product_id => string ].
	 */
	public function sanitize_mapping( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$product_ids = isset( $row['product_ids'] ) ? $row['product_ids'] : array();
			if ( ! is_array( $product_ids ) ) {
				$product_ids = array_filter( array_map( 'absint', (array) $product_ids ) );
			} else {
				$product_ids = array_filter( array_map( 'absint', $product_ids ) );
			}
			$invoice_id = isset( $row['invoice_product_id'] ) ? sanitize_text_field( $row['invoice_product_id'] ) : '';
			if ( ! empty( $product_ids ) && '' !== $invoice_id ) {
				$out[] = array(
					'product_ids'       => array_values( $product_ids ),
					'invoice_product_id' => $invoice_id,
				);
			}
		}
		return $out;
	}

	/**
	 * Enqueues admin scripts for product search.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_tabapay-invoice-products' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
	}

	/**
	 * Returns stored mapping (product_ids => invoice_product_id per row).
	 *
	 * @return array Array of [ 'product_ids' => int[], 'invoice_product_id' => string ].
	 */
	public static function get_mapping() {
		$saved = get_option( self::OPTION_NAME, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Returns map of product_id => invoice_product_id for all products.
	 *
	 * @return array Associative array product_id => invoice_product_id.
	 */
	public static function get_product_to_invoice_id_map() {
		$rows   = self::get_mapping();
		$result = array();
		foreach ( $rows as $row ) {
			$invoice_id = isset( $row['invoice_product_id'] ) ? $row['invoice_product_id'] : '';
			if ( '' === $invoice_id ) {
				continue;
			}
			$ids = isset( $row['product_ids'] ) ? $row['product_ids'] : array();
			foreach ( $ids as $pid ) {
				$result[ (int) $pid ] = $invoice_id;
			}
		}
		return $result;
	}

	/**
	 * Renders the admin page.
	 */
	public function render_page() {
		$mapping      = self::get_mapping();
		$all_products = wc_get_products(
			array(
				'status' => array( 'publish' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تنظیمات ذخیره شد.', 'tabapay-gateway' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'برای هر گروه از محصولات یک شناسه کالا/خدمت مالیاتی (invoiceProductId) تعریف کنید. چند محصول می‌توانند یک شناسه مشترک داشته باشند.', 'tabapay-gateway' ); ?></p>
			<form method="post" action="options.php" id="tabapay-invoice-form">
				<?php settings_fields( 'tabapay_invoice_products' ); ?>
				<table class="form-table" id="tabapay-invoice-rows">
					<thead>
						<tr>
							<th><?php esc_html_e( 'محصولات', 'tabapay-gateway' ); ?></th>
							<th><?php esc_html_e( 'شناسه کالا/خدمت (invoiceProductId)', 'tabapay-gateway' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $mapping as $index => $row ) {
							$pids    = isset( $row['product_ids'] ) ? $row['product_ids'] : array();
							$inv_id  = isset( $row['invoice_product_id'] ) ? $row['invoice_product_id'] : '';
							$this->render_row( $index, $pids, $inv_id, $all_products );
						}
						?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="tabapay-add-row"><?php esc_html_e( 'افزودن ردیف', 'tabapay-gateway' ); ?></button>
				</p>
				<?php submit_button( __( 'ذخیره تغییرات', 'tabapay-gateway' ) ); ?>
			</form>
			<style>
				#tabapay-invoice-rows tbody tr.tabapay-invoice-row > td {
					padding: 12px 10px;
				}
				#tabapay-invoice-rows tbody tr.tabapay-invoice-row {
					background: #f9f9f9;
					border: 1px solid #ccd0d4;
				}
				#tabapay-invoice-rows tbody tr.tabapay-invoice-row + tr.tabapay-invoice-row {
					margin-top: 8px;
				}
				.tabapay-product-selector {
					border: 1px solid #ccd0d4;
					border-radius: 4px;
					background: #fff;
					padding: 10px;
					max-width: 520px;
				}
				.tabapay-product-list {
					max-height: 260px;
					overflow-y: auto;
					border-top: 1px solid #eee;
					margin-top: 8px;
					padding-top: 6px;
				}
				.tabapay-product-item {
					display: block;
					margin-bottom: 4px;
				}
			</style>
		</div>
		<script type="text/template" id="tabapay-row-tpl">
			<tr class="tabapay-invoice-row">
				<td>
					<div class="tabapay-product-selector">
						<p>
							<input type="text" class="tabapay-product-search-input" placeholder="<?php esc_attr_e( 'جستجوی محصولات...', 'tabapay-gateway' ); ?>" />
						</p>
						<p>
							<label><input type="checkbox" class="tabapay-select-all" /> <?php esc_html_e( 'انتخاب همه محصولات', 'tabapay-gateway' ); ?></label>
						</p>
						<div class="tabapay-product-list">
							<?php
							foreach ( $all_products as $pid ) {
								$product = wc_get_product( $pid );
								if ( ! $product ) {
									continue;
								}
								$name = $product->get_name() . ' (#' . $pid . ')';
								?>
								<label class="tabapay-product-item" data-name="<?php echo esc_attr( $name ); ?>">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[{{index}}][product_ids][]" value="<?php echo esc_attr( $pid ); ?>" />
									<?php echo esc_html( $name ); ?>
								</label>
								<?php
							}
							?>
						</div>
					</div>
				</td>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[{{index}}][invoice_product_id]" value="" placeholder="<?php esc_attr_e( 'مثال: 12345', 'tabapay-gateway' ); ?>" class="regular-text" />
				</td>
				<td>
					<button type="button" class="button tabapay-remove-row"><?php esc_html_e( 'حذف', 'tabapay-gateway' ); ?></button>
				</td>
			</tr>
		</script>
		<script>
		jQuery(function($) {
			function initRowBehaviors($context) {
				$context.find('.tabapay-product-search-input').off('keyup').on('keyup', function() {
					var term     = $(this).val().toLowerCase();
					var $wrapper = $(this).closest('.tabapay-product-selector');
					$wrapper.find('.tabapay-product-item').each(function() {
						var name = ( $(this).data('name') + '' ).toLowerCase();
						if (!term || name.indexOf(term) !== -1) {
							$(this).show();
						} else {
							$(this).hide();
						}
					});
				});

				$context.find('.tabapay-select-all').off('change').on('change', function() {
					var checked  = $(this).is(':checked');
					var $wrapper = $(this).closest('.tabapay-product-selector');
					$wrapper.find('.tabapay-product-item input[type="checkbox"]').prop('checked', checked);
				});
			}
			$('#tabapay-invoice-rows tbody').on('click', '.tabapay-remove-row', function() {
				$(this).closest('tr').remove();
			});
			$('#tabapay-add-row').on('click', function() {
				var tpl = $('#tabapay-row-tpl').html();
				var index = $('#tabapay-invoice-rows tbody tr').length;
				var html = tpl.replace(/\{\{index\}\}/g, index);
				$('#tabapay-invoice-rows tbody').append(html);
				initRowBehaviors($('#tabapay-invoice-rows tbody tr:last'));
			});
			initRowBehaviors($('#tabapay-invoice-rows'));
		});
		</script>
		<?php
	}

	/**
	 * Renders one mapping row.
	 *
	 * @param int   $index Row index.
	 * @param int[] $product_ids Product IDs.
	 * @param string $invoice_product_id Invoice product ID.
	 * @param int[] $all_products All product IDs to display.
	 */
	private function render_row( $index, $product_ids, $invoice_product_id, $all_products ) {
		$name_base = self::OPTION_NAME . '[' . $index . ']';
		$selected  = array_map( 'absint', (array) $product_ids );
		?>
		<tr class="tabapay-invoice-row">
			<td>
				<div class="tabapay-product-selector">
					<p>
						<input type="text" class="tabapay-product-search-input" placeholder="<?php esc_attr_e( 'جستجوی محصولات...', 'tabapay-gateway' ); ?>" />
					</p>
					<p>
						<label><input type="checkbox" class="tabapay-select-all" /> <?php esc_html_e( 'انتخاب همه محصولات', 'tabapay-gateway' ); ?></label>
					</p>
					<div class="tabapay-product-list">
						<?php
						foreach ( $all_products as $pid ) {
							$product = wc_get_product( $pid );
							if ( ! $product ) {
								continue;
							}
							$name       = $product->get_name() . ' (#' . $pid . ')';
							$is_checked = in_array( $pid, $selected, true );
							?>
							<label class="tabapay-product-item" data-name="<?php echo esc_attr( $name ); ?>">
								<input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[product_ids][]" value="<?php echo esc_attr( $pid ); ?>" <?php checked( $is_checked ); ?> />
								<?php echo esc_html( $name ); ?>
							</label>
							<?php
						}
						?>
					</div>
				</div>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $name_base ); ?>[invoice_product_id]" value="<?php echo esc_attr( $invoice_product_id ); ?>" placeholder="<?php esc_attr_e( 'مثال: 12345', 'tabapay-gateway' ); ?>" class="regular-text" />
			</td>
			<td>
				<button type="button" class="button tabapay-remove-row"><?php esc_html_e( 'حذف', 'tabapay-gateway' ); ?></button>
			</td>
		</tr>
		<?php
	}
}

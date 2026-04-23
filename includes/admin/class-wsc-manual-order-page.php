<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Manual_Order_Page
 * [GEMINI 1.3.3] - بازطراحی کامل صفحه با قابلیت افزودن چند محصول و نمایش نام محصول با Ajax
 */
class WSC_Manual_Order_Page {

	public static function init() {
		add_action( 'wp_ajax_wsc_fetch_product_names_by_sku', array( __CLASS__, 'ajax_fetch_product_names_by_sku' ) );
	}

	public static function ajax_fetch_product_names_by_sku() {
		check_ajax_referer( 'wsc_manual_order_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Access Denied' ), 403 );
		}

		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( $_POST['sku'] ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error();
		}

		$wc_name = '---';
		$snapp_name = '---';

		$product_id = wc_get_product_id_by_sku( $sku );
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$wc_name = $product->get_name();
			}
		}

		$repo = new WSC_Mapping_Repository();
		$mappings = $repo->get_mappings_by_sku( $sku );
		if ( ! empty( $mappings ) ) {
			$snapp_name = $mappings[0]->title;
		}

		wp_send_json_success( array( 'wc_name' => $wc_name, 'snapp_name' => $snapp_name ) );
	}

	public static function render() {
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wsc_manual_order_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['wsc_manual_order_nonce'], 'wsc_create_order' ) ) {
				$result = self::process_order_creation();
				if ( is_wp_error( $result ) ) {
					printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $result->get_error_message() ) );
				} else {
					$order_link = get_edit_post_link( $result->get_id() );
					printf( '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( 'سفارش %s با موفقیت ایجاد شد. <a href="%s" target="_blank">مشاهده سفارش</a>' ) . '</p></div>', esc_html( $result->get_order_number() ), esc_url( $order_link ) );
				}
			}
		}
		
		self::render_form();
	}

	private static function render_form() {
		?>
		<style>
			.wsc-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 1px 20px 20px; margin-top: 20px; }
			.wsc-card h2 { font-size: 1.5em; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
			#wsc-products-repeater .repeater-row { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 15px; padding: 15px 10px; border-bottom: 1px solid #f0f0f1; }
			#wsc-products-repeater .repeater-row:last-child { border-bottom: none; }
			#wsc-products-repeater .repeater-row input { vertical-align: middle; }
			#wsc-products-repeater .sku-input { width: 180px; }
			#wsc-products-repeater .qty-input { width: 80px; }
			.wsc-add-row-btn { margin-top: 15px; }
			.product-names-display { flex-basis: 100%; margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-right: 3px solid #7e8993; font-size: 0.9em; color: #3c434a; }
			.product-names-display span { display: block; }
			.product-names-display .spinner { float: none; }
		</style>

		<div class="wrap">
			<h1><?php _e( 'ثبت سفارش دستی اسنپ شاپ', 'woo-snappshop-connector' ); ?></h1>
			<p><?php _e( 'از این فرم برای ثبت دستی سفارش‌هایی که از اسنپ شاپ دریافت می‌کنید (برای مغایرت‌گیری) استفاده کنید.', 'woo-snappshop-connector' ); ?></p>
			
			<form method="POST" action="">
				<?php wp_nonce_field( 'wsc_create_order', 'wsc_manual_order_nonce' ); ?>
				
				<div class="wsc-card">
					<h2><?php _e( '۱. اطلاعات مشتری و سفارش', 'woo-snappshop-connector' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="customer_name"><?php _e( 'نام مشتری', 'woo-snappshop-connector' ); ?></label></th>
							<td><input type="text" name="customer_name" id="customer_name" class="regular-text" required /></td>
						</tr>
						<tr>
							<th scope="row"><label for="customer_phone"><?php _e( 'تلفن مشتری', 'woo-snappshop-connector' ); ?></label></th>
							<td><input type="tel" name="customer_phone" id="customer_phone" class="regular-text ltr" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="snapp_order_id"><?php _e( 'شناسه سفارش اسنپ شاپ', 'woo-snappshop-connector' ); ?></label></th>
							<td><input type="text" name="snapp_order_id" id="snapp_order_id" class="regular-text" /><p class="description"><?php _e( 'شناسه سفارش در پنل اسنپ شاپ (برای ردیابی).', 'woo-snappshop-connector' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="shipping_method"><?php _e( 'نوع ارسال (مهم)', 'woo-snappshop-connector' ); ?></label></th>
							<td>
								<select name="shipping_method" id="shipping_method" required>
									<option value=""><?php _e( 'انتخاب کنید...', 'woo-snappshop-connector' ); ?></option>
									<option value="peyk"><?php _e( 'ارسال با پیک اسنپ', 'woo-snappshop-connector' ); ?></option>
									<option value="anbar"><?php _e( 'ارسال به انبار اسنپ', 'woo-snappshop-connector' ); ?></option>
								</select>
								<p class="description"><?php _e( 'این فیلد برای تفکیک گزارش‌ها ضروری است.', 'woo-snappshop-connector' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="wsc-card">
					<h2><?php _e( '۲. محصولات سفارش', 'woo-snappshop-connector' ); ?></h2>
					<div id="wsc-products-repeater">
						<div class="repeater-row">
							<div>
								<input type="text" name="products[0][sku]" class="sku-input ltr" placeholder="SKU محصول" required />
								<input type="number" name="products[0][qty]" class="qty-input" value="1" min="1" placeholder="تعداد" required />
								<input type="number" name="products[0][price]" class="regular-text ltr" placeholder="قیمت فروش (تومان)" />
							</div>
							<div class="product-names-display" style="display: none;"></div>
						</div>
					</div>
					<button type="button" id="wsc-add-row-btn" class="button wsc-add-row-btn"><?php _e( 'افزودن محصول', 'woo-snappshop-connector' ); ?></button>
					<p class="description"><?php _e( 'قیمت فروش را برای هر محصول وارد کنید. اگر خالی بگذارید، قیمت فعلی محصول در ووکامرس محاسبه می‌شود.', 'woo-snappshop-connector' ); ?></p>
				</div>

				<?php submit_button( __( 'ایجاد سفارش', 'woo-snappshop-connector' ), 'primary', 'submit', true, array( 'style' => 'font-size: 1.2em; height: auto; padding: 10px 25px; margin-top: 20px;' ) ); ?>
			</form>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				let rowIndex = 1;
				const repeater = $('#wsc-products-repeater');
				const rowTemplate = repeater.find('.repeater-row').first().clone(true);
				let searchTimeout;

				$('#wsc-add-row-btn').on('click', function() {
					let newRow = rowTemplate.clone(true);
					newRow.find('input').each(function() {
						let name = $(this).attr('name');
						$(this).attr('name', name.replace(/\[0\]/, '[' + rowIndex + ']'));
						$(this).val('');
					});
					newRow.find('input[type="number"]').first().val('1');
					newRow.find('.product-names-display').hide().empty();
					if (newRow.find('.wsc-remove-row-btn').length === 0) {
						newRow.find('div').first().append(' <button type="button" class="button wsc-remove-row-btn">×</button>');
					}
					repeater.append(newRow);
					rowIndex++;
				});

				repeater.on('click', '.wsc-remove-row-btn', function() {
					$(this).closest('.repeater-row').remove();
				});

				repeater.on('keyup', '.sku-input', function() {
					const $input = $(this);
					const sku = $input.val();
					const $namesDisplay = $input.closest('.repeater-row').find('.product-names-display');

					clearTimeout(searchTimeout);

					if (sku.length < 3) {
						$namesDisplay.hide();
						return;
					}

					$namesDisplay.show().html('<span class="spinner is-active" style="float:none;"></span>');

					searchTimeout = setTimeout(function() {
						$.post(ajaxurl, {
							action: 'wsc_fetch_product_names_by_sku',
							_ajax_nonce: '<?php echo wp_create_nonce( "wsc_manual_order_nonce" ); ?>',
							sku: sku
						}, function(response) {
							if (response.success) {
								let html = '<span><b>ووکامرس:</b> ' + response.data.wc_name + '</span>';
								html += '<span><b>اسنپ‌شاپ:</b> ' + response.data.snapp_name + '</span>';
								$namesDisplay.html(html);
							} else {
								$namesDisplay.html('<span>محصولی یافت نشد.</span>');
							}
						}).fail(function() {
							$namesDisplay.html('<span>خطای سرور.</span>');
						});
					}, 500);
				});
			});
		</script>
		<?php
	}

	private static function process_order_creation() {
		try {
			// [GEMINI 1.3.6] - متغیر سفارش را در ابتدا null تعریف می‌کنیم تا در صورت خطا قابل بررسی باشد
			$order = null;

			$products_data = isset( $_POST['products'] ) ? $_POST['products'] : array();

			if ( empty( $products_data ) ) {
				throw new Exception( __( 'حداقل یک محصول باید به سفارش اضافه شود.', 'woo-snappshop-connector' ) );
			}
			
			$order = wc_create_order();
			if ( is_wp_error( $order ) ) {
				throw new Exception( __( 'خطا در ایجاد سفارش ووکامرس.', 'woo-snappshop-connector' ) );
			}

			foreach ( $products_data as $product_item ) {
				$product_sku = isset( $product_item['sku'] ) ? sanitize_text_field( $product_item['sku'] ) : '';
				$product_qty = isset( $product_item['qty'] ) ? intval( $product_item['qty'] ) : 0;
				$product_price = isset( $product_item['price'] ) && $product_item['price'] !== '' ? wc_clean( $product_item['price'] ) : null;

				if ( empty( $product_sku ) || $product_qty <= 0 ) {
					continue;
				}

				$product_id = wc_get_product_id_by_sku( $product_sku );
				if ( ! $product_id ) {
					throw new Exception( sprintf( __( 'محصولی با SKU "%s" یافت نشد.', 'woo-snappshop-connector' ), $product_sku ) );
				}
				
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					throw new Exception( sprintf( __( 'خطا در بارگذاری محصول با SKU "%s".', 'woo-snappshop-connector' ), $product_sku ) );
				}

				// [GEMINI 1.3.5] - تغییر منطق قیمت‌گذاری برای اولویت با اسنپ‌شاپ
				$price_to_add = null;
				if ( $product_price !== null ) {
					// اولویت ۱: قیمت دستی وارد شده توسط کاربر
					$price_to_add = $product_price;
				} else {
					// اگر قیمت دستی وارد نشده، قیمت را از دیتابیس اسنپ بخوان
					$repo = new WSC_Mapping_Repository();
					$mappings = $repo->get_mappings_by_sku( $product_sku );
					if ( ! empty( $mappings ) ) {
						$snapp_product = $mappings[0];
						$promotion_data = !empty($snapp_product->promotion) ? json_decode($snapp_product->promotion, true) : null;
						
						// اولویت ۲: قیمت تخفیف اسنپ
						if ( isset($promotion_data['special_price']) && is_numeric($promotion_data['special_price']) && $promotion_data['special_price'] > 0 ) {
							$price_to_add = $promotion_data['special_price'];
						} else {
							// اولویت ۳: قیمت عادی اسنپ
							$price_to_add = $snapp_product->price;
						}
					}
				}
				// اولویت ۴ (Fallback): اگر هیچ قیمتی یافت نشد، از قیمت ووکامرس استفاده کن
				$price_to_add = ( $price_to_add !== null && $price_to_add > 0 ) ? $price_to_add : $product->get_price();
				$args = array(
					'total' => wc_format_decimal( (float)$price_to_add * (int)$product_qty ),
				);
				$order->add_product( $product, $product_qty, $args );
			}

			$address = array(
				'first_name' => sanitize_text_field( $_POST['customer_name'] ),
				'phone'      => sanitize_text_field( $_POST['customer_phone'] ),
			);
			$order->set_address( $address, 'billing' );
			$order->set_address( $address, 'shipping' );

			$snapp_order_id = sanitize_text_field( $_POST['snapp_order_id'] );
			$shipping_method = sanitize_text_field( $_POST['shipping_method'] );
			
			$order->update_meta_data( '_sales_channel', 'snappshop' );
			$order->update_meta_data( '_snappshop_shipping_method', $shipping_method );
			if ( ! empty( $snapp_order_id ) ) {
				$order->update_meta_data( '_snappshop_order_id', $snapp_order_id );
			}
			
			$order->update_meta_data('_snapp_order_just_created', 'yes');

			$order->calculate_totals();
			$order->set_status( 'wc-snapp-pending', __( 'سفارش به صورت دستی برای اسنپ شاپ ثبت شد.', 'woo-snappshop-connector' ), false );
			
			$order->add_order_note( sprintf(
				__( 'سفارش دستی اسنپ شاپ ایجاد شد. شناسه اسنپ: %s، نوع ارسال: %s', 'woo-snappshop-connector' ),
				$snapp_order_id ? $snapp_order_id : '---',
				$shipping_method
			) );

			$order->save();
			
			return $order;

		} catch ( Exception $e ) {
			// اگر سفارش ایجاد شده بود اما پردازش با خطا مواجه شد، سفارش را حذف کن
			if ( $order && is_a($order, 'WC_Order') ) {
				wp_delete_post($order->get_id(), true);
			}
			return new WP_Error( 'order_creation_failed', $e->getMessage() );
		}
	}
}

<?php
// class-wsc-products-page.php

class WSC_Products_Page {

	public static function init() {
		add_action( 'wp_ajax_wsc_search_products', array( __CLASS__, 'ajax_search_products' ) );
		add_action( 'wp_ajax_wsc_update_promotion', array( __CLASS__, 'ajax_update_promotion' ) );
	}

	/**
	 * رندر کردن صفحه اصلی محصولات
	 */
	public static function render() {
		// نیازمندی‌های مدل و ریپوزیتوری را بارگذاری کنید. (فرض می‌شود WSC_Mapping_Repository قبلاً تعریف شده است)
		$repo = new WSC_Mapping_Repository();
		
		// منطق صفحه‌بندی
		$items_per_page = 50;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $items_per_page;

		// منطق مرتب‌سازی (پیش‌فرض)
		$current_orderby = 'wc_sku';
		$current_order = 'ASC';

		// بارگذاری اولیه داده‌ها
		$total_items = $repo->count_products('', false);
		$products = $repo->search_products('', false, $current_orderby, $current_order, $items_per_page, $offset);
		?>
		<style>
			.wsc-products-table { width: 100%; margin-top: 20px; border-collapse: collapse; }
			.wsc-products-table th, .wsc-products-table td { padding: 10px; border: 1px solid #ddd; text-align: right; }
			.wsc-products-table th { background-color: #f7f7f7; }
			.wsc-products-table tbody tr:nth-child(even) { background-color: #f9f9f9; }
			.wsc-products-table .status-active { color: #2271b1; font-weight: bold; }
			.wsc-products-table .status-inactive { color: #d63638; }
			.wsc-table-controls { margin: 20px 0; display: flex; align-items: center; flex-wrap: wrap; }
			.wsc-products-table th.sortable a { text-decoration: none; color: inherit; display: flex; justify-content: space-between; align-items: center; }
			.wsc-products-table th.sortable:hover { background-color: #e0e0e0; cursor: pointer; }
			.wsc-products-table th .dashicons { visibility: hidden; margin-right: 5px; }
			.wsc-products-table th.sorted .dashicons { visibility: visible; }
			.wsc-products-table th.sorted.asc .dashicons-arrow-down { display: none; }
			.wsc-products-table th.sorted.desc .dashicons-arrow-up { display: none; }
			.wsc-column-toggle-container { position: relative; display: inline-block; margin-left: 20px; }
			#wsc-column-toggle-btn { background: #f0f0f1; border-color: #8c8f94; }
			#wsc-column-toggle-list { display: none; position: absolute; background-color: #fff; border: 1px solid #ccc; padding: 10px; z-index: 100; min-width: 180px; left: 0; }
			#wsc-column-toggle-list label { display: block; padding: 5px; }
			.wsc-pagination { margin-top: 20px; }
			.wsc-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; text-decoration: none; margin-left: 4px; }
			.wsc-pagination .page-numbers.current { background-color: #f0f0f0; font-weight: bold; }
			.wsc-sync-status.success { color: green; }
			.wsc-sync-status.error { color: red; }
			.wsc-products-table td { vertical-align: middle; }
			.wsc-operations-cell .spinner { float: none; vertical-align: middle; margin: 0 5px; }
		</style>
		<div class="wrap">
			<h1><?php _e( 'مدیریت محصولات اسنپ شاپ', 'woo-snappshop-connector' ); ?></h1>
			<p><?php _e( 'در این صفحه، تمام محصولات دریافت شده از اسنپ شاپ به همراه جزئیات کامل آن‌ها نمایش داده می‌شود.', 'woo-snappshop-connector' ); ?></p>
			
			<div class="wsc-table-controls">
				<input type="text" id="wsc-product-search" placeholder="<?php _e( 'جستجو بر اساس نام یا SKU...', 'woo-snappshop-connector' ); ?>" style="width: 300px; padding: 8px; vertical-align: middle; margin-left: 20px;">
				
				<label style="margin-right: 15px;">
					<input type="checkbox" id="wsc-filter-buybox" style="vertical-align: middle;">
					<?php _e( 'فقط محصولات با Buy Box کمتر از قیمت فروش', 'woo-snappshop-connector' ); ?>
				</label>
				
				<div class="wsc-column-toggle-container">
					<button id="wsc-column-toggle-btn" class="button"><?php _e( 'مدیریت ستون‌ها', 'woo-snappshop-connector' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
					<div id="wsc-column-toggle-list">
						<label><input type="checkbox" class="wsc-column-toggle" data-column="wc_sku" checked> <?php _e( 'SKU', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="title" checked> <?php _e( 'عنوان محصول', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="price" checked> <?php _e( 'قیمت', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="stock" checked> <?php _e( 'موجودی', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="buy_box" checked> <?php _e( 'قیمت Buy Box', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="active" checked> <?php _e( 'وضعیت', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="promo_price" checked> <?php _e( 'قیمت تخفیف', 'woo-snappshop-connector' ); ?></label>
						<label><input type="checkbox" class="wsc-column-toggle" data-column="operations" checked> <?php _e( 'عملیات', 'woo-snappshop-connector' ); ?></label>
					</div>
				</div>
				<span id="wsc-search-spinner" class="spinner" style="vertical-align: middle;"></span>
			</div>

			<input type="hidden" id="wsc-orderby" value="<?php echo esc_attr($current_orderby); ?>">
			<input type="hidden" id="wsc-order" value="<?php echo esc_attr($current_order); ?>">
			<input type="hidden" id="wsc-paged" value="<?php echo esc_attr($current_page); ?>">

			<div id="wsc-products-table-wrapper">
				<table class="wsc-products-table">
					<thead>
						<tr>
							<?php self::render_sortable_header( 'SKU', 'wc_sku', $current_orderby, $current_order ); ?>
							<?php self::render_sortable_header( 'عنوان محصول', 'title', $current_orderby, $current_order ); ?>
							<?php self::render_sortable_header( 'قیمت (ریال)', 'price', $current_orderby, $current_order ); ?>
							<?php self::render_sortable_header( 'موجودی', 'stock', $current_orderby, $current_order ); ?>
							<?php self::render_sortable_header( 'قیمت Buy Box', 'buy_box', $current_orderby, $current_order ); ?>
							<?php self::render_sortable_header( 'وضعیت', 'active', $current_orderby, $current_order ); ?>
							<?php self::render_sortable_header( 'قیمت تخفیف', 'promo_price', $current_orderby, $current_order ); ?>
							<th data-column="operations"><?php _e( 'عملیات', 'woo-snappshop-connector' ); ?></th>
						</tr>
					</thead>
					<tbody id="wsc-products-tbody">
						<?php if ( empty( $products ) ) : ?>
							<tr><td colspan="8"><?php _e( 'هیچ محصولی برای نمایش یافت نشد.', 'woo-snappshop-connector' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $products as $product ) : ?>
								<?php self::render_product_row( $product ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div id="wsc-pagination-wrapper" class="wsc-pagination">
				<?php
				$base_url = remove_query_arg( 'paged', wp_unslash( $_SERVER['REQUEST_URI'] ) );
				echo paginate_links( array(
					'base' => $base_url . '%_%',
					'format' => '&paged=%#%',
					'current' => $current_page,
					'total' => ceil( $total_items / $items_per_page ),
					'prev_text' => '«',
					'next_text' => '»',
				) );
				?>
			</div>
		</div>
		<?php
		self::add_ajax_script();
	}

	/**
	 * پاسخ Ajax برای جستجو، مرتب‌سازی و صفحه‌بندی محصولات.
	 */
	public static function ajax_search_products() {
		check_ajax_referer( 'wsc_search_products_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Access Denied' ), 403 );
		}
		
		$search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( $_POST['search_term'] ) : '';
		$filter_buybox = isset( $_POST['filter_buybox'] ) && $_POST['filter_buybox'] === 'true';
		$orderby = isset( $_POST['orderby'] ) ? sanitize_key( $_POST['orderby'] ) : 'wc_sku';
		$order = isset( $_POST['order'] ) ? sanitize_key( $_POST['order'] ) : 'ASC';
		$paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
		
		$repo = new WSC_Mapping_Repository();
		$items_per_page = 50;
		$offset = ( $paged - 1 ) * $items_per_page;

		$products = $repo->search_products( $search_term, $filter_buybox, $orderby, $order, $items_per_page, $offset );
		$total_items = $repo->count_products( $search_term, $filter_buybox );

		ob_start();
		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				self::render_product_row( $product );
			}
		} else {
			echo '<tr><td colspan="8">' . __( 'هیچ محصولی با این مشخصات یافت نشد.', 'woo-snappshop-connector' ) . '</td></tr>';
		}
		$html = ob_get_clean();
		
		$base_url = admin_url('admin.php?page=wsc-products'); // URL پایه صفحه محصولات

		$pagination_html = paginate_links( array(
			'base' => $base_url . '%_%',
			'format' => '&paged=%#%',
			'current' => $paged,
			'total' => ceil( $total_items / $items_per_page ),
			'prev_text' => '«',
			'next_text' => '»',
		) );

		wp_send_json_success( array( 'html' => $html, 'pagination' => $pagination_html ) );
	}

	/**
	 * رندر کردن هدر ستون قابل مرتب‌سازی.
	 * * @param string $label برچسب نمایش داده شده برای ستون.
	 * @param string $orderby_key کلید مرتب‌سازی برای ستون.
	 * @param string $current_orderby کلید مرتب‌سازی فعال فعلی.
	 * @param string $current_order جهت مرتب‌سازی فعال فعلی (ASC یا DESC).
	 */
	private static function render_sortable_header( $label, $orderby_key, $current_orderby, $current_order ) {
		$is_sorted = ($orderby_key === $current_orderby);
		$order_class = strtolower($current_order);
		$class = 'sortable';
		if ($is_sorted) {
			$class .= ' sorted ' . $order_class;
		}
		echo '<th class="' . esc_attr($class) . '" data-orderby="' . esc_attr($orderby_key) . '" data-column="' . esc_attr($orderby_key) . '">';
		echo '<a>';
		echo '<span>' . esc_html($label) . '</span>';
		echo '<span class="dashicons dashicons-arrow-up"></span><span class="dashicons dashicons-arrow-down"></span>';
		echo '</a></th>';
	}

	/**
	 * رندر کردن یک سطر از جدول برای یک محصول.
	 * * @param object $product شی محصول.
	 */
	private static function render_product_row( $product ) {
		$promotion_data = !empty($product->promotion) ? json_decode($product->promotion, true) : null;
		$special_price = isset($promotion_data['special_price']) ? $promotion_data['special_price'] : null;
		?>
		<tr data-product-id="<?php echo esc_attr($product->id); ?>">
			<td data-column="wc_sku"><strong><?php echo esc_html( $product->wc_sku ); ?></strong></td>
			<td data-column="title"><?php echo esc_html( $product->title ); ?></td>
			<td data-column="price"><?php echo number_format( (int) $product->price ); ?></td>
			<td data-column="stock"><?php echo (int) $product->stock; ?></td>
			<td data-column="buy_box"><?php echo $product->buy_box ? number_format( (int) $product->buy_box ) : '---'; ?></td>
			<td data-column="active">
				<?php if ( $product->active ) : ?>
					<span class="status-active"><?php _e( 'فعال', 'woo-snappshop-connector' ); ?></span>
				<?php else : ?>
					<span class="status-inactive"><?php _e( 'غیرفعال', 'woo-snappshop-connector' ); ?></span>
				<?php endif; ?>
			</td>
			<td data-column="promo_price"><?php echo $special_price ? number_format( (int) $special_price ) : '---'; ?></td>
			<td data-column="operations">
				<div class="wsc-operations-cell" data-db-id="<?php echo esc_attr($product->id); ?>">
					<input type="number" class="wsc-promo-price-input" placeholder="<?php _e('قیمت تخفیف', 'woo-snappshop-connector'); ?>" value="<?php echo esc_attr($special_price); ?>" style="width: 100px;">
					<button class="button button-primary wsc-save-sync-btn"><?php _e('ذخیره و ارسال', 'woo-snappshop-connector'); ?></button>
					<span class="spinner"></span>
					<div class="wsc-sync-status"></div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * افزودن اسکریپت‌های AJAX برای تعاملات صفحه.
	 */
	private static function add_ajax_script() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var searchTimeout;
				var searchXHR;
				const storageKey = 'wsc_column_visibility';

				/**
				 * اجرای جستجو، مرتب‌سازی و/یا صفحه‌بندی از طریق AJAX.
				 */
				function performSearch() {
					var searchTerm = $('#wsc-product-search').val();
					var filterBuybox = $('#wsc-filter-buybox').is(':checked');
					var orderby = $('#wsc-orderby').val();
					var order = $('#wsc-order').val();
					// همیشه در هر تغییر فیلتر یا مرتب‌سازی به صفحه اول برگردید مگر اینکه از دکمه‌های صفحه‌بندی استفاده شود
					var paged = $('#wsc-paged').val(); 
					var spinner = $('#wsc-search-spinner');
					var tbody = $('#wsc-products-tbody');
					var paginationWrapper = $('#wsc-pagination-wrapper');

					clearTimeout(searchTimeout);
					searchTimeout = setTimeout(function() {
						spinner.addClass('is-active');
						tbody.css('opacity', 0.5);
						paginationWrapper.css('opacity', 0.5);
						
						if (searchXHR) {
							searchXHR.abort(); // لغو درخواست قبلی
						}

						var data = {
							action: 'wsc_search_products',
							_ajax_nonce: '<?php echo wp_create_nonce( "wsc_search_products_nonce" ); ?>',
							search_term: searchTerm,
							filter_buybox: filterBuybox,
							orderby: orderby,
							order: order,
							paged: paged
						};
						
						searchXHR = $.post(ajaxurl, data, function(response) {
							if (response.success) {
								tbody.html(response.data.html);
								paginationWrapper.html(response.data.pagination);
								applyColumnVisibility(); // اعمال وضعیت ستون‌ها پس از بارگذاری مجدد محتوا
							} else {
								tbody.html('<tr><td colspan="8">' + '<?php _e( "خطایی رخ داد.", "woo-snappshop-connector" ); ?>' + '</td></tr>');
							}
						}).fail(function(jqXHR, textStatus) {
							if (textStatus !== 'abort') {
								tbody.html('<tr><td colspan="8">' + '<?php _e( "خطای سرور.", "woo-snappshop-connector" ); ?>' + '</td></tr>');
							}
						}).always(function() {
							spinner.removeClass('is-active');
							tbody.css('opacity', 1);
							paginationWrapper.css('opacity', 1);
						});
					}, 300);
				}

				/**
				 * اعمال وضعیت نمایش ستون‌ها (پنهان/نمایش)
				 */
				function applyColumnVisibility() {
					var visibility = getColumnVisibility();
					$('.wsc-column-toggle').each(function() {
						var column = $(this).data('column');
						var isVisible = visibility[column];
						// پنهان/نمایان کردن هدر ستون و سلول‌های داده متناظر
						$('.wsc-products-table [data-column="' + column + '"]').toggle(isVisible);
					});
				}

				/**
				 * ذخیره وضعیت نمایش ستون‌ها در Local Storage.
				 */
				function saveColumnVisibility() {
					var visibility = {};
					$('.wsc-column-toggle').each(function() {
						var column = $(this).data('column');
						visibility[column] = $(this).is(':checked');
					});
					localStorage.setItem(storageKey, JSON.stringify(visibility));
				}

				/**
				 * دریافت وضعیت نمایش ستون‌ها از Local Storage یا استفاده از پیش‌فرض‌ها.
				 */
				function getColumnVisibility() {
					var stored = localStorage.getItem(storageKey);
					// پیش‌فرض‌ها
					var defaults = { wc_sku: true, title: true, price: true, stock: true, buy_box: true, active: true, promo_price: true, operations: true };
					var loaded = stored ? JSON.parse(stored) : {};
					// ترکیب تنظیمات ذخیره شده با پیش‌فرض‌ها
					return $.extend({}, defaults, loaded);
				}

				/**
				 * بارگذاری وضعیت نمایش ستون‌ها هنگام بارگذاری اولیه صفحه.
				 */
				function loadColumnVisibility() {
					var visibility = getColumnVisibility();
					$('.wsc-column-toggle').each(function() {
						var column = $(this).data('column');
						if (visibility[column] !== undefined) {
							$(this).prop('checked', visibility[column]);
						}
					});
					applyColumnVisibility();
				}

				// --- رویدادها (Event Handlers) ---

				// دکمه ذخیره و ارسال (همگام‌سازی)
				$('#wsc-products-table-wrapper').on('click', '.wsc-save-sync-btn', function() {
					var $btn = $(this);
					var $cell = $btn.closest('.wsc-operations-cell');
					var dbId = $cell.data('db-id');
					var newPrice = $cell.find('.wsc-promo-price-input').val();
					var $spinner = $cell.find('.spinner');
					var $statusDiv = $cell.find('.wsc-sync-status');

					$btn.prop('disabled', true);
					$spinner.addClass('is-active');
					$statusDiv.empty().removeClass('success error');

					$.post(ajaxurl, {
						action: 'wsc_update_promotion',
						_ajax_nonce: '<?php echo wp_create_nonce( "wsc_update_promotion_nonce" ); ?>',
						db_id: dbId,
						new_price: newPrice
					}, function(response) {
						if (response.success) {
							// به‌روزرسانی مقدار نمایش داده شده در ستون قیمت تخفیف
							$cell.closest('tr').find('[data-column="promo_price"]').text(newPrice ? new Intl.NumberFormat().format(newPrice) : '---');

							$statusDiv.html('✔ ' + response.data.message).addClass('success');
						} else {
							$statusDiv.html('✖ ' + response.data.message).addClass('error');
						}
					}).fail(function() {
						$statusDiv.html('✖ <?php _e("خطای سرور", "woo-snappshop-connector"); ?>').addClass('error');
					}).always(function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
						// پاک کردن پیام وضعیت پس از 5 ثانیه
						setTimeout(function() { $statusDiv.empty().removeClass('success error'); }, 5000);
					});
				});
				
				// کلیک روی دکمه‌های صفحه‌بندی
				$('#wsc-pagination-wrapper').on('click', '.page-numbers', function(e) {
					e.preventDefault();
					var href = $(this).attr('href');
					var page = 1;
					if (href) {
						// استخراج شماره صفحه از URL
						var pageStr = href.match(/paged=(\d+)/);
						if (pageStr && pageStr[1]) {
							page = pageStr[1];
						}
					}
					$('#wsc-paged').val(page);
					performSearch();
				});

				// جستجو و فیلتر (با تاخیر برای کاهش بار سرور)
				$('#wsc-product-search, #wsc-filter-buybox').on('keyup change', function() {
					$('#wsc-paged').val(1); // بازنشانی به صفحه ۱
					performSearch();
				});

				// مرتب‌سازی جدول
				$('#wsc-products-table-wrapper').on('click', 'th.sortable', function(e) {
					e.preventDefault();
					var newOrderby = $(this).data('orderby');
					var currentOrderby = $('#wsc-orderby').val();
					var currentOrder = $('#wsc-order').val();
					
					// منطق تعیین جهت مرتب‌سازی جدید
					var newOrder = (newOrderby === currentOrderby && currentOrder === 'ASC') ? 'DESC' : 'ASC';
					
					$('#wsc-orderby').val(newOrderby);
					$('#wsc-order').val(newOrder);
					$('#wsc-paged').val(1); // بازنشانی به صفحه اول
					
					// به‌روزرسانی کلاس‌های هدر جدول برای نمایش فلش مرتب‌سازی
					$('.wsc-products-table th.sortable').removeClass('sorted asc desc');
					$(this).addClass('sorted ' + newOrder.toLowerCase());
					
					performSearch();
				});

				// منطق مدیریت ستون‌ها
				$('#wsc-column-toggle-btn').on('click', function(e) { e.stopPropagation(); $('#wsc-column-toggle-list').toggle(); });
				$(document).on('click', function() { $('#wsc-column-toggle-list').hide(); });
				$('#wsc-column-toggle-list').on('click', function(e) { e.stopPropagation(); });
				$('.wsc-column-toggle').on('change', function() { saveColumnVisibility(); applyColumnVisibility(); });

				// بارگذاری اولیه وضعیت ستون‌ها
				loadColumnVisibility();
			});
		</script>
		<?php
	}
}

// ⚠️ توجه: بریس اضافی که باعث خطا می‌شد حذف شد.

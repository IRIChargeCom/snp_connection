<?php // File: woo-snappshop-connector/includes/wsc-jobs.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [نسخه 1.0.21] - اصلاح نهایی شمارش صفحه بر اساس تست cURL
 */

// ... (تابع wsc_run_single_product_sync_job بدون تغییر باقی می‌ماند) ...
/**
 * [نسخه بهبودیافته]
 * این تابع اکنون با سیستم مدیریت خطای Action Scheduler یکپارچه شده است.
 * در صورت بروز خطا در فراخوانی API، یک Exception ایجاد می‌شود. این کار به Action Scheduler
 * اجازه می‌دهد تا جاب را به عنوان "ناموفق" علامت‌گذاری کرده و به صورت خودکار برای اجرای
 * مجدد آن تلاش کند. جاب‌های ناموفق نهایی در بخش "WooCommerce > Status > Action Scheduler"
 * قابل مشاهده و بررسی هستند.
 */
function wsc_run_single_product_sync_job( $product_id, $options_snapshot = null ) {
	wsc_log_to_db( "--- Starting single product sync job (v2) ---" );
	wsc_log_to_db( "Product ID received: " . $product_id );

	if ( empty( $product_id ) ) {
		wsc_log_to_db( "Error: Product ID is missing. Aborting job." );
		return;
	}
	
	// برای سازگاری با فراخوانی‌های قدیمی یا دستی که اسنپ‌شات را ارسال نمی‌کنند
	if ( $options_snapshot === null ) {
		$options_snapshot = get_option('wsc_settings');
		wsc_log_to_db( "Options snapshot was null, loaded fresh options." );
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		wsc_log_to_db( "Error: Could not find WC_Product with ID: $product_id. Aborting job." );
		return;
	}
	
	$sku = $product->get_sku();
	if ( empty( $sku ) ) {
		wsc_log_to_db( "Info: Product ID $product_id has no SKU. Skipping sync." );
		return;
	}

	$mapping_repo = new WSC_Mapping_Repository();
	$product_mappings = $mapping_repo->get_mappings_by_sku( $sku );
	if ( empty( $product_mappings ) ) {
		wsc_log_to_db( "Info: No mapping found for SKU: $sku. Skipping sync." );
		return;
	}

	$stock = $product->get_stock_quantity();
	if ( $stock === null ) {
		$stock = 0; 
		wsc_log_to_db( "Info: Product ID $product_id does not manage stock. Setting stock to 0." );
	}

	$product_manager = new WSC_Product_Manager();
	$product_manager->options = $options_snapshot; // استفاده از اسنپ‌شات برای ثبات
	$final_price = $product_manager->calculate_final_price( $product );
	
	$products_payload = array();

	foreach ( $product_mappings as $mapping_row ) {
		$payload_item = array(
			'id'    => $mapping_row->snapp_shop_id,
			'price' => $final_price,
			'stock' => (int) $stock,
		);

		$promotion_details = $product_manager->calculate_final_promotion_details( $final_price, $mapping_row );

		if ( $promotion_details !== null ) {
			$payload_item['special_price'] = $promotion_details['special_price'];
			$payload_item['special_price_start_at'] = $promotion_details['start_at'];
			$payload_item['special_price_end_at'] = $promotion_details['end_at'];
			$payload_item['special_price_stock'] = (int) $stock;
			wsc_log_to_db( "Info: Adding promotion for Snapp! ID {$mapping_row->snapp_shop_id}. Special Price: {$promotion_details['special_price']}" );
		} else {
			$payload_item['special_price'] = null;
			wsc_log_to_db( "Info: No promotion to apply for Snapp! ID {$mapping_row->snapp_shop_id}." );
		}

		$products_payload[] = $payload_item;
	}

	if ( empty( $products_payload ) ) {
		wsc_log_to_db( "Error: Payload came out empty for SKU: $sku. Aborting." );
		return;
	}

	wsc_log_to_db( "Final payload for SKU $sku: " . wp_json_encode($products_payload) );

	$api_service = new WSC_API_Service( $options_snapshot );
	$result = $api_service->patch_products( $products_payload );

	if ( is_wp_error( $result ) ) {
		$error_message = 'WSC Sync job FAILED for SKU: ' . $sku . ' Error: ' . $result->get_error_message();
		wsc_log_to_db( "FATAL: " . $error_message );
		throw new Exception( $error_message );
	}
	
	wsc_log_to_db( "SUCCESS: Sync job completed for SKU: $sku." );
}
// [GEMINI BUG FIX] - اصلاح نحوه دریافت آرگومان‌ها
add_action( 'wsc_run_single_product_sync_job', 'wsc_run_single_product_sync_job', 10, 2 );


/**
 * 2. جاب دریافت لیست محصولات از API (برای پر کردن جدول نگاشت)
 */
function wsc_run_id_fetch_job( $args ) {
	
	try {
		// [FIX] Defensive code against environments that corrupt job arguments.
		$page = 1; // Default value
		if ( is_array($args) && isset($args['page']) ) {
			$page = (int) $args['page'];
		} elseif ( is_numeric($args) ) {
			$page = (int) $args;
		}
	
		$options_snapshot = get_option( 'wsc_settings' );
		if ( empty( $options_snapshot ) ) {
			return;
		}
		
		$api_service = new WSC_API_Service( $options_snapshot );
		$result = $api_service->get_products_page( $page );
		
		if ( $result === null || is_wp_error( $result ) ) {
			// Silently fail and let the admin see the stalled process.
			// The error is likely environmental, and retrying won't help.
			return;
		}
		
		$products = isset( $result['data'] ) ? $result['data'] : array();
		
		// Defensive code to handle different possible API response structures for pagination.
		$pagination_data = array();
		if (isset($result['meta']['pagination'])) {
			$pagination_data = $result['meta']['pagination'];
		} elseif (isset($result['meta'])) {
			$pagination_data = $result['meta'];
		}
		$meta = $pagination_data;
		
		$mapping_repo = new WSC_Mapping_Repository();
		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				if ( ! empty( $product['id'] ) ) {
					// [GEMINI 1.1.4] - ارسال مستقیم کل آبجکت محصول
					// تابع جدید insert_or_update_mapping خودش مسئولیت پردازش و ذخیره تمام فیلدها را بر عهده دارد.
					// این کار کد را بسیار تمیزتر و قابل توسعه‌تر می‌کند.
					$mapping_repo->insert_or_update_mapping( $product );
				}
			}
		}
		
		// Determine current and total pages
		$api_current_page = isset( $meta['current_page'] ) ? (int) $meta['current_page'] : $page;
		$total_pages = isset( $meta['total_pages'] ) ? (int) $meta['total_pages'] : $api_current_page; // Corrected typo from 'total_page'
		
		update_option( 'wsc_fetch_status', array( 'current' => $api_current_page, 'total' => $total_pages ) );

		// For the loop logic, we trust our own page counter ($page) to be safe.
		if ( $page < $total_pages ) {
			$next_page = $page + 1;
			$queue_manager = new WSC_Queue_Manager();
			$queue_manager->schedule_id_fetch_job( $next_page );
		} else {
			update_option( 'wsc_fetch_status', 'complete' );
		}

	} catch ( \Throwable $e ) {
		// In case of a fatal error, log it to the status so the user can see it.
		$error_message = $e->getMessage();
		update_option( 'wsc_fetch_status', 'failed: (Fatal Error) ' . $error_message );
	}
}
add_action( 'wsc_run_id_fetch_job', 'wsc_run_id_fetch_job', 10, 1 );


/**
 * 3. Job for testing background PATCH
 */
function wsc_run_test_patch_job( $args ) {
	
	try {
		update_option( 'wsc_test_patch_status', 'starting' );
		$options_snapshot = get_option( 'wsc_settings' ); // [FIX] خواندن مستقیم از دیتابیس

		if ( empty( $options_snapshot ) ) {
			update_option( 'wsc_test_patch_status', 'failed: ' . __( 'Snapshot تنظیمات در جاب پس‌زمینه یافت نشد.', 'woo-snappshop-connector' ) );
			return;
		}
		
		$api_service = new WSC_API_Service( $options_snapshot );
		$products_payload = array(
			array( 'sku' => '101010400', 'price' => 11000, 'stock' => 5 )
		);

		$result = $api_service->patch_products( $products_payload );

		if ( $result === null ) {
			update_option( 'wsc_test_patch_status', 'failed: ' . __( 'پاسخ API (wp_remote_request) null بود. (تداخل افزونه)', 'woo-snappshop-connector' ) );
			return;
		}
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			update_option( 'wsc_test_patch_status', 'failed: ' . $error_message );
		} else {
			$status = isset($result['data'][0]['status']) ? $result['data'][0]['status'] : false;
			if ($status === true) {
				 update_option( 'wsc_test_patch_status', 'success: ' . wp_json_encode( $result ) );
			} else {
				$api_message = isset($result['data'][0]['messages'][0]) ? $result['data'][0]['messages'][0] : 'Unknown API error';
				update_option( 'wsc_test_patch_status', 'failed: (API Error) ' . $api_message );
			}
		}
	
	} catch ( \Throwable $e ) {
		$error_message = $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
		update_option( 'wsc_test_patch_status', 'failed: (Fatal Error) ' . $error_message );
	}
}
add_action( 'wsc_run_test_patch_job', 'wsc_run_test_patch_job', 10, 1 );

<?php // File: woo-snappshop-connector/includes/admin/class-wsc-csv-import-page.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_CSV_Import_Page
 *
 * رندر و مدیریت فرم درون‌ریزی CSV قیمت رقبا.
 */
class WSC_CSV_Import_Page {

	public static function render() {
		// مدیریت فایل آپلود شده
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['competitor_csv'] ) && check_admin_referer( 'wsc_csv_import' ) ) {
			$result = self::process_csv_upload( $_FILES['competitor_csv'] );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . sprintf( __( 'فایل با موفقیت پردازش شد. %d ردیف به‌روزرسانی شد.', 'woo-snappshop-connector' ), $result ) . '</p></div>';
			}
		}
		
		self::render_form();
	}

	/**
	 * رندر فرم آپلود
	 */
	private static function render_form() {
		?>
		<div class="wrap">
			<h1><?php _e( 'درون‌ریزی CSV قیمت رقبا (اسنپ شاپ)', 'woo-snappshop-connector' ); ?></h1>
			<p><?php _e( 'فایل CSV دریافتی از اسنپ شاپ (شامل ستون "کد فروشنده" و "قیمت بای باکس") را در اینجا آپلود کنید.', 'woo-snappshop-connector' ); ?></p>
			
			<form method="POST" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wsc_csv_import' ); ?>
				
				<table class="form-table">
					<tr class="form-field">
						<th scope="row"><label for="competitor_csv"><?php _e( 'فایل CSV', 'woo-snappshop-connector' ); ?></label></th>
						<td><input type="file" name="competitor_csv" id="competitor_csv" accept=".csv" required /></td>
					</tr>
				</table>
				
				<?php submit_button( __( 'درون‌ریزی و به‌روزرسانی قیمت‌ها', 'woo-snappshop-connector' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * پردازش فایل CSV آپلود شده
	 */
	private static function process_csv_upload( $file ) {
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', __( 'خطا در آپلود فایل.', 'woo-snappshop-connector' ) );
		}

		// افزایش محدودیت زمانی برای فایل‌های بزرگ
		@set_time_limit( 300 );

		$file_path = $file['tmp_name'];
		
		// باز کردن فایل CSV
		if ( ( $handle = fopen( $file_path, 'r' ) ) === false ) {
			return new WP_Error( 'file_open_error', __( 'امکان باز کردن فایل CSV وجود ندارد.', 'woo-snappshop-connector' ) );
		}

		$updated_rows = 0;
		$header = fgetcsv( $handle ); // خواندن هدر
		if ( ! $header ) {
			fclose( $handle );
			return new WP_Error( 'csv_read_error', __( 'فایل CSV خالی است یا قابل خواندن نیست.', 'woo-snappshop-connector' ) );
		}

		// پیدا کردن ایندکس ستون‌های مورد نیاز
		// بر اساس فایل نمونه: "کد فروشنده" و "قیمت بای باکس"
		$sku_index = array_search( 'کد فروشنده', $header );
		$price_index = array_search( 'قیمت بای باکس', $header );
		
		// اگر "قیمت بای باکس" وجود نداشت، شاید "قیمت مرجع" باشد
		if ( $price_index === false ) {
			$price_index = array_search( 'قیمت مرجع(تومان)', $header );
		}
		
		if ( $sku_index === false || $price_index === false ) {
			fclose( $handle );
			// لاگ کردن هدر برای دیباگ
			error_log( 'WSC CSV Error: Columns not found. Header: ' . implode( ',', $header ) );
			return new WP_Error( 'csv_column_error', __( 'ستون‌های مورد نیاز ("کد فروشنده" یا "قیمت بای باکس" / "قیمت مرجع") در فایل CSV یافت نشد.', 'woo-snappshop-connector' ) );
		}

		// خواندن ردیف به ردیف
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$sku = isset( $row[ $sku_index ] ) ? trim( $row[ $sku_index ] ) : '';
			$lowest_price = isset( $row[ $price_index ] ) ? trim( $row[ $price_index ] ) : '';

			if ( empty( $sku ) || ! is_numeric( $lowest_price ) || $lowest_price <= 0 ) {
				continue; // رد کردن ردیف‌های نامعتبر
			}

			// پیدا کردن محصول یا متغیر بر اساس SKU
			$product_id = wc_get_product_id_by_sku( $sku );
			
			if ( $product_id > 0 ) {
				// ذخیره قیمت بای باکس به عنوان متادیتا
				update_post_meta( $product_id, '_competitor_lowest_price', (int) $lowest_price );
				$updated_rows++;
			} else {
				// لاگ: 'CSV Import: SKU not found in WooCommerce: ' . $sku
			}
		}

		fclose( $handle );
		return $updated_rows;
	}
}

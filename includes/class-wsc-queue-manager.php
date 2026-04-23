<?php // File: woo-snappshop-connector/includes/class-wsc-queue-manager.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [نسخه 1.0.21] - افزودن تأخیر برای Rate Limit
 */
class WSC_Queue_Manager {

	// ... (تابع schedule_product_sync بدون تغییر) ...
	public function schedule_product_sync( $product_id, $options_snapshot, $priority = 'batch' ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$hook = 'wsc_run_single_product_sync_job';
		// [GEMINI BUG FIX] - آرگومان‌ها باید به صورت اندیس‌دار ارسال شوند تا با امضای جدید تابع جاب مطابقت داشته باشند
		$args = array( 
			$product_id,
			$options_snapshot
		);
		if ( $this->is_job_scheduled( $hook, $args ) ) {
			return;
		}
		if ( $priority === 'realtime' ) {
			as_enqueue_async_action( $hook, $args, 'wsc-realtime-sync' );
		} else {
			as_schedule_single_action( time() + 60, $hook, $args, 'wsc-batch-sync' );
		}
	}

	/**
	 * زمان‌bندی یک کار برای دریافت لیست محصولات از API
	 */
	public function schedule_id_fetch_job( $page = 1 ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		
		$hook = 'wsc_run_id_fetch_job';
		$args = array( 
			'page' => $page
		);

		// [MODIFIED] - برای جلوگیری از ایجاد کارهای تکراری، همیشه بررسی کن
		// اگر کاری با همین صفحه در حالت انتظار بود، دوباره اضافه نکن
		if ( $this->is_job_scheduled( $hook, $args, 'wsc-api-fetch' ) ) {
			return;
		}

		// ⭐️⭐️⭐️ [رفع باگ تعداد درخواست زیاد] ⭐️⭐️⭐️
		// افزودن تأخیر برای جلوگیری از خطای "Rate Limit"
		$delay_in_seconds = ( $page === 1 ) ? 0 : 5; 

		// تغییر از async (فوری) به scheduled (زمان‌bندی شده)
		as_schedule_single_action( time() + $delay_in_seconds, $hook, $args, 'wsc-api-fetch' );
	}

	/**
	 * بررسی اینکه آیا کاری با هوک و آرگومان‌های مشخص در صف است یا خیر
	 */
	private function is_job_scheduled( $hook, $args, $group = '' ) {
		$params = array(
			'hook'   => $hook,
			'args'   => $args,
			'status' => 'pending',
		);
		if ( ! empty( $group ) ) {
			$params['group'] = $group;
		}
		$jobs = as_get_scheduled_actions( $params );
		return ! empty( $jobs );
	}
}

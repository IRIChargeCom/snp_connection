<?php // File: woo-snappshop-connector/includes/class-wsc-order-manager.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Order_Manager
 *
 * مسئول تمام منطق مربوط به سفارش‌ها برای مغایرت‌گیری:
 * 1. ثبت وضعیت‌های سفارشی (Pending, Shipped, Cancelled, Returned)
 * 2. افزودن یادداشت خودکار (Audit Trail) هنگام تغییر وضعیت
 */
class WSC_Order_Manager {

	public function __construct() {
		// افزودن وضعیت‌های سفارشی به لیست وضعیت‌های ووکامرس
		add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses_to_list' ) );
		
		// افزودن یادداشت خودکار هنگام تغییر وضعیت
		add_action( 'woocommerce_order_status_changed', array( $this, 'add_audit_note_on_status_change' ), 10, 4 );
	}

	/**
	 * 1. ثبت وضعیت‌های سفارش سفارشی در وردپرس
	 */
	public function register_custom_order_statuses() {
		register_post_status( 'wc-snapp-pending', array(
			'label'                     => __( 'در انتظار (اسنپ شاپ)', 'woo-snappshop-connector' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'در انتظار (اسنپ شاپ) <span class="count">(%s)</span>', 'در انتظار (اسنپ شاپ) <span class="count">(%s)</span>', 'woo-snappshop-connector' ),
		) );
		register_post_status( 'wc-snapp-shipped', array(
			'label'                     => __( 'ارسال شده (اسنپ شاپ)', 'woo-snappshop-connector' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'ارسال شده (اسنپ شاپ) <span class="count">(%s)</span>', 'ارسال شده (اسنپ شاپ) <span class="count">(%s)</span>', 'woo-snappshop-connector' ),
		) );
		register_post_status( 'wc-snapp-cancelled', array(
			'label'                     => __( 'لغو شده (اسنپ شاپ)', 'woo-snappshop-connector' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'لغو شده (اسنپ شاپ) <span class="count">(%s)</span>', 'لغو شده (اسنپ شاپ) <span class="count">(%s)</span>', 'woo-snappshop-connector' ),
		) );
		register_post_status( 'wc-snapp-returned', array(
			'label'                     => __( 'مرجوعی (اسنپ شاپ)', 'woo-snappshop-connector' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'مرجوعی (اسنپ شاپ) <span class="count">(%s)</span>', 'مرجوعی (اسنپ شاپ) <span class="count">(%s)</span>', 'woo-snappshop-connector' ),
		) );
	}

	/**
	 * 2. افزودن وضعیت‌های سفارشی به لیست کشویی ووکامرس
	 */
	public function add_custom_statuses_to_list( $order_statuses ) {
		// اضافه کردن وضعیت‌ها بعد از 'wc-processing'
		$new_statuses = array();
		foreach ( $order_statuses as $key => $status ) {
			$new_statuses[ $key ] = $status;
			if ( 'wc-processing' === $key ) {
				$new_statuses['wc-snapp-pending'] = __( 'در انتظار (اسنپ شاپ)', 'woo-snappshop-connector' );
				$new_statuses['wc-snapp-shipped'] = __( 'ارسال شده (اسنپ شاپ)', 'woo-snappshop-connector' );
				$new_statuses['wc-snapp-cancelled'] = __( 'لغو شده (اسنپ شاپ)', 'woo-snappshop-connector' );
				$new_statuses['wc-snapp-returned'] = __( 'مرجوعی (اسنپ شاپ)', 'woo-snappshop-connector' );
			}
		}
		return $new_statuses;
	}

	/**
	 * 3. افزودن یادداشت خودکار (Audit Trail)
	 * @param int $order_id
	 * @param string $old_status
	 * @param string $new_status
	 * @param WC_Order $order
	 */
	public function add_audit_note_on_status_change( $order_id, $old_status, $new_status, $order ) {
		$snapp_statuses = array(
			'wc-snapp-pending',
			'wc-snapp-shipped',
			'wc-snapp-cancelled',
			'wc-snapp-returned',
		);

		// اگر وضعیت جدید یکی از وضعیت‌های اسنپ شاپ است
		if ( in_array( $new_status, $snapp_statuses ) ) {
			// اگر وضعیت قبلی هم یکی از وضعیت‌های اسنپ بود، برای جلوگیری از یادداشت تکراری در زمان ایجاد، چک می‌کنیم
			if ( $old_status === $new_status && $order->get_meta('_snapp_order_just_created') === 'yes' ) {
				$order->delete_meta_data('_snapp_order_just_created');
				return;
			}

			$status_label = wc_get_order_status_name( $new_status );
			$note = sprintf(
				__( 'وضعیت سفارش به %s تغییر یافت. (تغییر از %s)', 'woo-snappshop-connector' ),
				$status_label,
				wc_get_order_status_name( $old_status )
			);
			
			// افزودن یادداشت خصوصی به سفارش
			$order->add_order_note( $note, false, false ); // is_customer_note = false, added_by_user = false
		}
	}
}

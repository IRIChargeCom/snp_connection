<?php // File: woo-snappshop-connector/includes/class-wsc-product-hooks.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Product_Hooks
 *
 * [نسخه 1.0.4] - ارسال تنظیمات به جاب همگام‌سازی تک محصول
 */
class WSC_Product_Hooks {

	/**
	 * @var WSC_Queue_Manager
	 */
	private $queue_manager;
	private $options_snapshot; // [NEW]

	public function __construct( WSC_Queue_Manager $queue_manager ) {
		$this->queue_manager = $queue_manager;
		// [NEW] - تنظیمات را یک بار در زمان بارگذاری می‌خوانیم
		$this->options_snapshot = get_option( 'wsc_settings' ); 

		// 1. همگام‌سازی لحظه‌ای:
		add_action( 'woocommerce_reduce_stock', array( $this, 'handle_stock_reduction' ), 10, 1 );

		// 2. همگام‌سازی دسته‌ای (موجودی):
		add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_change' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_stock_change' ), 10, 1 );

		// 3. همگام‌سازی دسته‌ای (قیمت):
		add_action( 'woocommerce_update_product_variation', array( $this, 'handle_variation_update' ), 10, 1 ); 
		
		// 4. همگام‌سازی (محصول ساده):
		add_action( 'woocommerce_update_product', array( $this, 'handle_simple_product_update' ), 10, 1 );
	}

	/**
	 * متد کمکی برای ارسال به صف
	 */
	private function schedule_sync( $product_id, $priority ) {
		// [MODIFIED] - اطمینان حاصل می‌کنیم که تنظیمات ذخیره شده‌اند
		if ( empty( $this->options_snapshot['api_token'] ) ) {
			// اگر تنظیمات ذخیره نشده باشند، هیچ کاری در صف قرار نده
			return; 
		}
		
		// [MODIFIED] - ارسال اسنپ‌شات تنظیمات به جاب
		$this->queue_manager->schedule_product_sync( $product_id, $this->options_snapshot, $priority );
	}

	/**
	 * 1. مدیریت همگام‌سازی لحظه‌ای هنگام فروش
	 */
	public function handle_stock_reduction( $order_item ) {
		$product = $order_item->get_product();
		if ( ! $product ) {
			return;
		}
		$product_id = $product->is_type( 'variation' ) ? $product->get_id() : $product->get_id();
		$this->schedule_sync( $product_id, 'realtime' ); // [MODIFIED]
	}

	/**
	 * 2. مدیریت همگام‌سازی هنگام تغییر موجودی
	 */
	public function handle_stock_change( $product ) {
		if ( ! $product ) {
			return;
		}
		$this->schedule_sync( $product->get_id(), 'batch' ); // [MODIFIED]
	}

	/**
	 * 3. مدیریت همگام‌سازی هنگام آپدیت متغیر
	 */
	public function handle_variation_update( $variation_id ) {
		$this->schedule_sync( $variation_id, 'batch' ); // [MODIFIED]
	}
	
	/**
	 * 4. مدیریت همگام‌سازی محصول ساده
	 */
	public function handle_simple_product_update( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variable' ) ) {
			return;
		}
		$this->schedule_sync( $product_id, 'batch' ); // [MODIFIED]
	}
}

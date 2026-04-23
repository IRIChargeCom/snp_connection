<?php // File: woo-snappshop-connector/includes/admin/class-wsc-product-admin.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Product_Admin
 *
 * مسئول افزودن بخش‌های UI به صفحات محصول ووکامرس:
 * 1. متاباکس قیمت دستی اسنپ شاپ (در صفحه ویرایش محصول/متغیر)
 * 2. ستون هشدار قیمت رقبا (در لیست محصولات)
 */
class WSC_Product_Admin {

	public function __construct() {
		// 1. افزودن متاباکس برای قیمت دستی (برای محصولات ساده و متغیر)
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_manual_price_field_simple' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'add_manual_price_field_variable' ), 10, 3 );
		
		// 2. ذخیره فیلدهای متاباکس
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_manual_price_field_simple' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_manual_price_field_variable' ), 10, 2 );
		
		// 3. افزودن ستون هشدار قیمت
		add_filter( 'manage_product_posts_columns', array( $this, 'add_competitor_price_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_competitor_price_column' ), 10, 2 );
	}

	/**
	 * 1. افزودن فیلد قیمت دستی برای محصولات ساده
	 */
	public function add_manual_price_field_simple() {
		woocommerce_wp_text_input( array(
			'id'          => '_snappshop_manual_price',
			'label'       => __( 'قیمت دستی اسنپ شاپ (تومان)', 'woo-snappshop-connector' ),
			'description' => __( 'این قیمت، درصد افزایش سراسری را لغو (Override) می‌کند. خالی بگذارید تا از تنظیمات سراسری استفاده شود.', 'woo-snappshop-connector' ),
			'desc_tip'    => true,
			'data_type'   => 'price',
		) );
	}

	/**
	 * 1. افزودن فیلد قیمت دستی برای متغیرها
	 */
	public function add_manual_price_field_variable( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input( array(
			'id'          => "_snappshop_manual_price[{$loop}]",
			'name'        => "_snappshop_manual_price[{$loop}]",
			'label'       => __( 'قیمت دستی اسنپ شاپ (تومان)', 'woo-snappshop-connector' ),
			'wrapper_class' => 'form-row form-row-first',
			'value'       => get_post_meta( $variation->ID, '_snappshop_manual_price', true ),
			'data_type'   => 'price',
			'desc_tip'    => true,
			'description' => __( 'این قیمت، درصد افزایش سراسری را لغو (Override) می‌کند.', 'woo-snappshop-connector' ),
		) );
	}

	/**
	 * 2. ذخیره فیلد محصول ساده
	 */
	public function save_manual_price_field_simple( $post_id ) {
		$price = isset( $_POST['_snappshop_manual_price'] ) ? wc_clean( $_POST['_snappshop_manual_price'] ) : '';
		update_post_meta( $post_id, '_snappshop_manual_price', $price );
	}

	/**
	 * 2. ذخیره فیلد متغیر
	 */
	public function save_manual_price_field_variable( $variation_id, $i ) {
		$price = isset( $_POST['_snappshop_manual_price'][ $i ] ) ? wc_clean( $_POST['_snappshop_manual_price'][ $i ] ) : '';
		update_post_meta( $variation_id, '_snappshop_manual_price', $price );
	}
	
	/**
	 * 3. افزودن ستون هشدار قیمت به لیست محصولات
	 */
	public function add_competitor_price_column( $columns ) {
		// اضافه کردن ستون قبل از ستون 'date'
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( $key === 'date' ) {
				$new_columns['competitor_price'] = __( 'وضعیت رقابت (اسنپ)', 'woo-snappshop-connector' );
			}
			$new_columns[ $key ] = $value;
		}
		return $new_columns;
	}

	/**
	 * 3. رندر کردن محتوای ستون هشدار قیمت
	 */
	public function render_competitor_price_column( $column, $post_id ) {
		if ( $column === 'competitor_price' ) {
			$product = wc_get_product( $post_id );
			
			// این منطق برای محصولات متغیر باید پیچیده‌تر شود،
			// اما برای نمونه، قیمت محصول اصلی یا اولین متغیر را بررسی می‌کنیم.
			if ( $product->is_type( 'variable' ) ) {
				echo '<small>' . __( '— (محصول متغیر)', 'woo-snappshop-connector' ) . '</small>';
				// در نسخه کامل، باید روی متغیرها لوپ بزنیم یا خلاصه‌ای نشان دهیم
				return;
			}

			// دریافت قیمت بای باکس (که از CSV درون‌ریزی شده)
			$lowest_price = (int) $product->get_meta( '_competitor_lowest_price' );
			if ( empty( $lowest_price ) ) {
				echo '<small>' . __( 'فاقد داده رقبا', 'woo-snappshop-connector' ) . '</small>';
				return;
			}

			// دریافت قیمت نهایی محاسبه شده ما
			$product_manager = new WSC_Product_Manager();
			$our_price = $product_manager->calculate_final_price( $product );

			// مقایسه
			if ( $our_price > $lowest_price ) {
				$diff = $our_price - $lowest_price;
				printf(
					'<span style="color: red; font-weight: bold;" title="%s">🔻 %s</span><br><small>شما: %s | رقبا: %s</small>',
					__( 'قیمت شما از کمترین قیمت رقبا بالاتر است!', 'woo-snappshop-connector' ),
					wc_price( $diff ),
					wc_price( $our_price ),
					wc_price( $lowest_price )
				);
			} else {
				printf(
					'<span style="color: green;">✅ %s</span><br><small>رقبا: %s</small>',
					wc_price( $our_price ),
					wc_price( $lowest_price )
				);
			}
		}
	}
}

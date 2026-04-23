<?php // File: woo-snappshop-connector/includes/class-wsc-product-manager.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Product_Manager
 *
 * این کلاس "موتور" منطق تجاری ما است.
 * مسئول محاسبه قیمت نهایی بر اساس قوانین (قیمت دستی، درصد سراسری) است.
 */
class WSC_Product_Manager {

	public $options;

	public function __construct() {
		$this->options = get_option( 'wsc_settings', array() );
	}

	/**
	 * [GEMINI 1.2.2] - محاسبه قیمت نهایی محصول برای ارسال به اسنپ شاپ
	 *
	 * @param WC_Product $product
	 * @return int
	 */
	public function calculate_final_price( $product ) {
		// اولویت ۱: قیمت دستی مخصوص اسنپ شاپ
		$manual_price = $product->get_meta( '_snappshop_manual_price' );
		if ( ! empty( $manual_price ) && is_numeric( $manual_price ) && $manual_price > 0 ) {
			return (int) $manual_price;
		}

		// اولویت ۲: قیمت عادی محصول (یا قیمت فروش ویژه، اگر وجود دارد)
		$regular_price = $product->get_price();
		if ( empty( $regular_price ) ) {
			$regular_price = 0;
		}
		
		// اولویت ۳: اعمال درصد افزایش سراسری
		$increase_percent = isset( $this->options['default_price_percent'] ) ? floatval( $this->options['default_price_percent'] ) : 0;
		if ( $increase_percent > 0 ) {
			$calculated_price = $regular_price * ( 1 + ( $increase_percent / 100 ) );
			return (int) round($calculated_price);
		}

		// اولویت آخر: قیمت عادی محصول
		return (int) $regular_price;
	}

	/**
	 * [GEMINI 1.2.2] - محاسبه جزئیات نهایی تخفیف برای ارسال به اسنپ شاپ
	 *
	 * @param int   $final_price قیمت نهایی محصول (برای محاسبه درصد تخفیف)
	 * @param array $db_row ردیف اطلاعات محصول از جدول دیتابیس ما
	 * @return array|null
	 */
	public function calculate_final_promotion_details( $final_price, $db_row ) {
		$special_price = null;
		$existing_promotion = ( ! empty( $db_row->promotion ) ) ? json_decode( $db_row->promotion, true ) : null;

		// اولویت ۱: قیمت تخفیف دستی که قبلاً در آبجکت promotion ذخیره شده
		if ( isset( $existing_promotion['special_price'] ) && is_numeric( $existing_promotion['special_price'] ) ) {
			// اگر یک قیمت معتبر وجود داشت، آن را به عنوان کاندید در نظر بگیر
			// این قیمت می‌تواند توسط کاربر وارد شده باشد یا از قبل از اسنپ آمده باشد
			$special_price = (int) $existing_promotion['special_price'];
		}
		
		// اولویت ۲: اعمال درصد تخفیف سراسری (فقط اگر قیمت دستی وجود نداشته باشد)
		// برای جلوگیری از محاسبه دوباره، چک می‌کنیم که آیا special_price هنوز null است
		if ( $special_price === null ) {
			$discount_percent = isset( $this->options['global_discount_percent'] ) ? floatval( $this->options['global_discount_percent'] ) : 0;
			if ( $discount_percent > 0 && $final_price > 0 ) {
				$special_price = $final_price * ( 1 - ( $discount_percent / 100 ) );
			}
		}

		// اگر پس از بررسی تمام قوانین، هیچ تخفیفی اعمال نشده بود، خارج شو
		if ( $special_price === null ) {
			return null;
		}

		// اگر تخفیف داشتیم، تاریخ‌های سراسری را به آن اضافه می‌کنیم
		$start_at = ! empty( $this->options['global_start_date'] ) ? $this->options['global_start_date'] : null;
		$end_at = ! empty( $this->options['global_end_date'] ) ? $this->options['global_end_date'] : null;

		// اگر تاریخ‌ها معتبر نبودند، تخفیف را ارسال نکن
		if ( empty($start_at) || empty($end_at) ) {
			return null;
		}

		return [
			'special_price' => (int) round($special_price),
			'start_at'      => $start_at,
			'end_at'        => $end_at,
		];
	}
}

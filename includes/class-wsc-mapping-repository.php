<?php // File: woo-snappshop-connector/includes/class-wsc-mapping-repository.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Mapping_Repository
 *
 * این کلاس تمام تعاملات با جدول سفارشی دیتابیس `wp_wsc_snappshop_mapping` را مدیریت می‌کند.
 * [نسخه 1.0.1] - بازنویسی شده برای پشتیبانی از عنوان و SKU خالی.
 */
class WSC_Mapping_Repository {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . WSC_MAPPING_TABLE;
	}

	/**
	 * [GEMINI 1.1.4] - بازنویسی کامل برای ذخیره تمام اطلاعات محصول
	 * این متد یک آرایه کامل از اطلاعات محصول را دریافت کرده و در دیتابیس ذخیره می‌کند.
	 *
	 * @param array $product_data آرایه‌ای حاوی تمام اطلاعات محصول از API
	 */
	public function insert_or_update_mapping( $product_data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		// مقادیر پیش‌فرض برای فیلدهایی که ممکن است در پاسخ API وجود نداشته باشند
		$defaults = array(
			'id' => '', 'sku' => '', 'product_number' => 0, 'parent_product_number' => 0,
			'title' => '', 'title_en' => null, 'thumbnail' => null, 'active' => false,
			'is_blacklist' => false, 'price' => 0, 'stock' => 0, 'warehouse_stock' => 0,
			'buy_box' => null, 'warranty' => null, 'promotion' => null, 'discount' => null,
			'variation_attributes' => null, 'reference_price' => null, 'capacity' => null,
			'created_at' => null
		);
		$data = wp_parse_args( $product_data, $defaults );

		// فیلدهایی که به صورت JSON ذخیره می‌شوند را encode می‌کنیم
		$warranty_json = is_array($data['warranty']) ? wp_json_encode($data['warranty']) : null;
		$promotion_json = is_array($data['promotion']) ? wp_json_encode($data['promotion']) : null;
		$discount_json = is_array($data['discount']) ? wp_json_encode($data['discount']) : null;
		$attributes_json = is_array($data['variation_attributes']) ? wp_json_encode($data['variation_attributes']) : null;
		$ref_price_json = is_array($data['reference_price']) ? wp_json_encode($data['reference_price']) : null;

		// کوئری INSERT ... ON DUPLICATE KEY UPDATE برای کارایی بالا
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $this->table_name (
				snapp_shop_id, wc_sku, product_number, parent_product_number, title, title_en, thumbnail, active, is_blacklist,
				price, stock, warehouse_stock, buy_box, warranty, promotion, discount, variation_attributes, reference_price,
				capacity, created_at, last_synced
			) VALUES (
				%s, %s, %d, %d, %s, %s, %s, %d, %d,
				%d, %d, %d, %d, %s, %s, %s, %s, %s,
				%s, %s, %s
			)
			ON DUPLICATE KEY UPDATE
				wc_sku = VALUES(wc_sku),
				product_number = VALUES(product_number),
				parent_product_number = VALUES(parent_product_number),
				title = VALUES(title),
				title_en = VALUES(title_en),
				thumbnail = VALUES(thumbnail),
				active = VALUES(active),
				is_blacklist = VALUES(is_blacklist),
				price = VALUES(price),
				stock = VALUES(stock),
				warehouse_stock = VALUES(warehouse_stock),
				buy_box = VALUES(buy_box),
				warranty = VALUES(warranty),
				promotion = VALUES(promotion),
				discount = VALUES(discount),
				variation_attributes = VALUES(variation_attributes),
				reference_price = VALUES(reference_price),
				capacity = VALUES(capacity),
				created_at = VALUES(created_at),
				last_synced = VALUES(last_synced)",
			$data['id'], trim($data['sku']), $data['product_number'], $data['parent_product_number'], $data['title'], $data['title_en'], $data['thumbnail'], $data['active'], $data['is_blacklist'],
			$data['price'], $data['stock'], $data['warehouse_stock'], $data['buy_box'], $warranty_json, $promotion_json, $discount_json, $attributes_json, $ref_price_json,
			$data['capacity'], $data['created_at'], $now
		) );
	}

	/**
	 * [GEMINI 1.1.6] - تابع جدید برای خواندن تمام محصولات
	 * این تابع تمام رکوردهای ذخیره شده در جدول نگاشت را برای نمایش در صفحه "محصولات" برمی‌گرداند.
	 *
	 * @return array|null
	 */
	public function get_all_products() {
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT * FROM $this->table_name ORDER BY wc_sku ASC"
		);
		return $results;
	}

	    public function search_products( $search_term = '', $filter_buybox = false, $orderby = 'wc_sku', $order = 'ASC', $limit = 50, $offset = 0 ) {

	        global $wpdb;

	        $sql = "SELECT * FROM $this->table_name";

	        $where_clauses = array();

	        $params = array();

	

	        if ( ! empty( $search_term ) ) {

	            $where_clauses[] = "(title LIKE %s OR wc_sku LIKE %s)";

	            $params[] = '%' . $wpdb->esc_like( $search_term ) . '%';

	            $params[] = '%' . $wpdb->esc_like( $search_term ) . '%';

	        }

	

	        if ( $filter_buybox ) {

	            $where_clauses[] = "(buy_box > 0 AND buy_box < price)";

	        }

	

	        if ( ! empty( $where_clauses ) ) {

	            $sql .= " WHERE " . implode( ' AND ', $where_clauses );

	        }

	

	        // Sanitize orderby

	        $allowed_orderby = array( 'wc_sku', 'title', 'price', 'stock', 'buy_box', 'active', 'promo_price' );

	        if ( ! in_array( $orderby, $allowed_orderby ) ) {

	            $orderby = 'wc_sku';

	        }

	        // Sanitize order

	        $order = ( strtoupper( $order ) === 'ASC' ) ? 'ASC' : 'DESC';

	

	        $sql .= " ORDER BY $orderby $order";

	

	        // Add pagination

	        $sql .= " LIMIT %d OFFSET %d";

	        $params[] = $limit;

	        $params[] = $offset;

	

	        if ( ! empty( $params ) ) {

	            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	        } else {

	            $results = $wpdb->get_results( $sql );

	        }

	        return $results;

	    }

	

	    /**

	     * [GEMINI 1.3.1] - شمارش تعداد کل محصولات برای صفحه‌بندی

	     */

	    public function count_products( $search_term = '', $filter_buybox = false ) {

	        global $wpdb;

	        $sql = "SELECT COUNT(*) FROM $this->table_name";

	        $where_clauses = array();

	        $params = array();

	

	        if ( ! empty( $search_term ) ) {

	            $where_clauses[] = "(title LIKE %s OR wc_sku LIKE %s)";

	            $params[] = '%' . $wpdb->esc_like( $search_term ) . '%';

	            $params[] = '%' . $wpdb->esc_like( $search_term ) . '%';

	        }

	

	        if ( $filter_buybox ) {

	            $where_clauses[] = "(buy_box > 0 AND buy_box < price)";

	        }

	

	        if ( ! empty( $where_clauses ) ) {

	            $sql .= " WHERE " . implode( ' AND ', $where_clauses );

	        }

	

	        if ( ! empty( $params ) ) {

	            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

	        } else {

	            return (int) $wpdb->get_var( $sql );

	        }

	    }

	

	    /**

	     * یک محصول یا متغیر را در جدول نگاشت درج یا آپدیت می‌کند

	     * [نسخه 1.1.4] - این تابع اکنون کل آبجکت محصول را می‌پذیرد

	
	 */
	public function get_product_by_db_id( $db_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $db_id ) );
	}

	/**
	 * [GEMINI 1.2.3] - تمام رکوردهای نگاشت مربوط به یک SKU را برمی‌گرداند
	 *
	 * @param string $sku
	 * @return array|null
	 */
	public function get_mappings_by_sku( $sku ) {
		global $wpdb;
		if ( empty($sku) ) {
			return null;
		}
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $this->table_name WHERE wc_sku = %s",
			$sku
		) );
		return $results;
	}

	/**
	 * [GEMINI 1.2.6] - قیمت تخفیف دستی را در دیتابیس آپدیت می‌کند
	 *
	 * @param int $db_id ID ردیف در جدول نگاشت
	 * @param int|null $new_price قیمت جدید یا null برای حذف
	 * @return bool
	 */
	public function update_manual_promotion_price( $db_id, $new_price ) {
		global $wpdb;

		// ۱. رکورد فعلی را بخوان
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $db_id ) );
		if ( ! $row ) {
			return false;
		}

		// ۲. آبجکت promotion را decode کن
		$promotion_data = !empty($row->promotion) ? json_decode($row->promotion, true) : array();
		if ( ! is_array($promotion_data) ) {
			$promotion_data = array();
		}

		// ۳. قیمت جدید را تنظیم کن
		if ( $new_price !== null && is_numeric($new_price) && $new_price > 0 ) {
			$promotion_data['special_price'] = (int) $new_price;
		} else {
			// اگر قیمت جدید نامعتبر یا null بود، آن را از آبجکت حذف کن
			unset( $promotion_data['special_price'] );
		}

		// ۴. آبجکت را دوباره encode کرده و در دیتابیس ذخیره کن
		$new_promotion_json = !empty($promotion_data) ? wp_json_encode( $promotion_data ) : null;

		$result = $wpdb->update(
			$this->table_name,
			array( 'promotion' => $new_promotion_json ),
			array( 'id' => $db_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * دریافت تمام شناسه‌های اسنپ شاپ (Snapp IDs) مرتبط با یک SKU
	 * این تابع، قلب حل مشکل SKU تکراری است.
	 *
	 * @param string $sku
	 * @return array لیستی از ID های اسنپ شاپ
	 */
	public function get_snapp_ids_for_sku( $sku ) {
		global $wpdb;
		
		// [MODIFIED] - اطمینان از اینکه SKU خالی را جستجو نمی‌کنیم
		if ( empty($sku) ) {
			return array();
		}

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT snapp_shop_id FROM $this->table_name WHERE wc_variation_sku = %s",
			$sku
		) );

		return $results; // مثال: ['a84GyL', 'O319KL']
	}

	/**
	 * پاک کردن تمام رکوردهای نگاشت (مثلاً قبل از همگام‌سازی مجدد)
	 */
	public function clear_all_mappings() {
		global $wpdb;
		// به جای TRUNCATE، جدول را کاملاً حذف و دوباره ایجاد می‌کنیم.
		// این تضمین می‌کند که ساختار جدول همیشه ۱۰۰٪ صحیح است (با UNIQUE KEY).
		$wpdb->query( "DROP TABLE IF EXISTS $this->table_name" );

		// فراخوانی مجدد منطق ایجاد جدول از کلاس فعال‌ساز
		if ( ! class_exists( 'WSC_Activator' ) ) {
			require_once WSC_PLUGIN_DIR . 'includes/class-wsc-activator.php';
		}
		// ما یک متد عمومی برای فراخوانی متد خصوصی ایجاد جدول اضافه خواهیم کرد
		WSC_Activator::run_creator();
	}
	
	// [MODIFIED CODE START] - متد جدید برای صفحه گزارش جامع
	/**
	 * دریافت تمام رکوردهای نگاشت به همراه وضعیت آن‌ها در ووکامرس
	 * @return array
	 */
	public function get_all_mappings_with_woo_status() {
		global $wpdb;
		
		// 1. دریافت تمام SKU های موجود در ووکامرس
		$woo_skus_results = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
			 WHERE meta_key = '_sku' AND meta_value != ''"
		);
		$woo_skus_lookup = array_flip( $woo_skus_results );

		// 2. [GEMINI 1.1.11] - اصلاح کوئری برای استفاده از نام‌های جدید ستون
		$sql = "SELECT wc_sku, snapp_shop_id, title, variation_attributes 
				FROM $this->table_name 
				ORDER BY wc_sku ASC, last_synced DESC";
		$all_mappings = $wpdb->get_results( $sql );
		
		$report_data = array(
			'mapped' => array(),
			'woo_missing' => array(),
			'snapp_sku_missing' => array()
		);
		
		if ( $all_mappings ) {
			foreach ( $all_mappings as $row ) {
				// [GEMINI 1.1.11] - پردازش ویژگی‌ها از JSON
				$attributes_string = '';
				$variation_attributes = json_decode($row->variation_attributes, true);
				if ( ! empty( $variation_attributes ) && is_array( $variation_attributes ) ) {
					$attrs_parts = array();
					foreach ( $variation_attributes as $attr_group ) {
						if ( isset( $attr_group['attribute']['title'] ) && isset( $attr_group['value']['title'] ) ) {
							$attrs_parts[] = $attr_group['attribute']['title'] . ': ' . $attr_group['value']['title'];
						}
					}
					$attributes_string = implode( ', ', $attrs_parts );
				}
				// یک فیلد جدید به آبجکت اضافه می‌کنیم تا در صفحه گزارش استفاده شود
				$row->snapp_shop_attributes_rendered = $attributes_string;


				// [GEMINI 1.1.11] - استفاده از نام‌های جدید پراپرتی
				$snapp_sku = $row->wc_sku;
				
				if ( empty( $snapp_sku ) ) {
					$report_data['snapp_sku_missing'][] = $row;
				} else {
					if ( isset( $woo_skus_lookup[ $snapp_sku ] ) ) {
						$report_data['mapped'][] = $row;
					} else {
						$report_data['woo_missing'][] = $row;
					}
				}
			}
		}
		
		return $report_data;
	}
	// [MODIFIED CODE END]
}

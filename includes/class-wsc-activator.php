<?php // File: woo-snappshop-connector/includes/class-wsc-activator.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Activator
 *
 * این کلاس وظیفه اجرای عملیات در زمان فعال‌سازی افزونه را دارد.
 * اصلی‌ترین وظیفه آن، ایجاد و ارتقای جدول نگاشت سفارشی ما است.
 */
class WSC_Activator {

	/**
	 * متد اصلی فعال‌سازی
	 */
	public static function activate() {
		self::create_mapping_table();
		
		// ذخیره نسخه دیتابیس
		if ( ! get_option( 'wsc_db_version' ) ) {
			add_option( 'wsc_db_version', '1.0.1' ); // [MODIFIED] - شروع از نسخه جدید
		}
	}

	public static function run_creator() {
		self::create_mapping_table();
	}

	/**
	 * [GEMINI 1.1.3] - بازسازی کامل جدول برای ذخیره تمام اطلاعات محصول
	 * این متد ساختار جدول اصلی افزونه را ایجاد یا به‌روزرسانی می‌کند.
	 */
	private static function create_mapping_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . WSC_MAPPING_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		// ستون‌های جدید بر اساس پاسخ کامل API اسنپ‌شاپ
		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			
			-- شناسه های کلیدی
			snapp_shop_id VARCHAR(50) NOT NULL,
			wc_sku VARCHAR(100) DEFAULT '' NOT NULL,
			product_number BIGINT(20) DEFAULT 0,
			parent_product_number BIGINT(20) DEFAULT 0,
			
			-- اطلاعات اصلی محصول
			title TEXT NOT NULL,
			title_en TEXT,
			thumbnail TEXT,
			active BOOLEAN DEFAULT FALSE,
			is_blacklist BOOLEAN DEFAULT FALSE,
			
			-- اطلاعات قیمت و موجودی
			price INT(11) DEFAULT 0,
			stock INT(11) DEFAULT 0,
			warehouse_stock INT(11) DEFAULT 0,
			buy_box INT(11) NULL DEFAULT NULL,
			
			-- اطلاعات پیچیده (ذخیره به صورت JSON)
			warranty TEXT,
			promotion TEXT,
			discount TEXT,
			variation_attributes TEXT,
			reference_price TEXT,
			
			-- متادیتا
			capacity VARCHAR(100),
			created_at DATETIME,
			last_synced DATETIME NOT NULL,
			
			PRIMARY KEY  (id),
			UNIQUE KEY snapp_shop_id (snapp_shop_id),
			KEY wc_sku (wc_sku)
		) $charset_collate;";

		// از dbDelta برای ایجاد یا آپدیت امن جدول استفاده می‌کنیم
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	// [NEW CODE START] - تابع ارتقا برای کاربرانی که افزونه را نصب دارند
	/**
	 * ارتقای دیتابیس به نسخه 1.0.1
	 * (افزودن ستون snapp_shop_title)
	 */
	public static function upgrade_database_v_1_0_1() {
		global $wpdb;
		$table_name = $wpdb->prefix . WSC_MAPPING_TABLE;

		// بررسی وجود ستون
		$column_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
			DB_NAME, $table_name, 'snapp_shop_title'
		) );

		if ( ! $column_exists ) {
			// ستون وجود ندارد، آن را اضافه کن
			$wpdb->query( "ALTER TABLE $table_name ADD snapp_shop_title TEXT NOT NULL AFTER snapp_shop_id" );
		}

		// [NEW] افزودن ستون ویژگی‌ها برای سازگاری با نسخه‌های قدیمی
		$attrs_column_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
			DB_NAME, $table_name, 'snapp_shop_attributes'
		) );

		if ( ! $attrs_column_exists ) {
			$wpdb->query( "ALTER TABLE $table_name ADD snapp_shop_attributes TEXT NOT NULL AFTER snapp_shop_title" );
		}
		
		// همچنین کلید منحصر به فرد را اضافه می‌کنیم (اگر از قبل وجود نداشته باشد)
		$index_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
			DB_NAME, $table_name, 'idx_snapp_id'
		) );
		
		if ( ! $index_exists ) {
			// ممکن است کلید قبلی 'idx_snapp_id' از نوع KEY بوده باشد، ابتدا آن را حذف می‌کنیم (در صورت وجود)
			// این دستور اگر ایندکس وجود نداشته باشد خطا نمی‌دهد (در اکثر SQL ها)
			// @ $wpdb->query( "DROP INDEX idx_snapp_id ON $table_name" );
			
			// و کلید منحصر به فرد را اضافه می‌کنیم
			// توجه: اگر داده تکراری وجود داشته باشد، این دستور شکست می‌خورد.
			// برای سادگی، فرض می‌کنیم داده تکراری وجود ندارد.
			$wpdb->query( "ALTER TABLE $table_name ADD UNIQUE KEY idx_snapp_id (snapp_shop_id)" );
		}
	}
	// [NEW CODE END]
}

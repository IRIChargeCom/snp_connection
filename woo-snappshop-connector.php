<?php // File: woo-snappshop-connector/woo-snappshop-connector.php
/**
 * Plugin Name:       Snapp!Shop Connector
 * Plugin URI:        https://1dafe.com
 * Description:       افزونه اتصال ووکامرس به اسنپ شاپ برای همگام‌سازی قیمت، موجودی و مدیریت سفارش‌ها.
 * Version:           1.0.0
 * Author:            علی احمدزاده
 * Author URI:        https://1dafe.ir
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-snappshop-connector
 * Domain Path:       /languages
 *
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// اگر فایل مستقیماً فراخوانی شد، خارج شو
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// تعریف ثابت‌های اصلی افزونه
define( 'WSC_VERSION', '1.0.0' );
define( 'WSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSC_PLUGIN_FILE', __FILE__ );
define( 'WSC_MAPPING_TABLE', 'wsc_snappshop_mapping' ); // نام جدول نگاشت سفارشی ما


/**
 * بارگذاری کلاس اصلی افزونه.
 */
function wsc_run_plugin() {
	// ابتدا مطمئن شویم که ووکامرس فعال است
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wsc_woocommerce_missing_notice' );
		return;
	}

	// بارگذاری فایل‌های اصلی
	require_once WSC_PLUGIN_DIR . 'includes/class-wsc-plugin.php';
	
	// اجرای کلاس اصلی
	WSC_Plugin::get_instance();
}

// اجرای افزونه پس از بارگذاری تمام افزونه‌ها
add_action( 'plugins_loaded', 'wsc_run_plugin' );

/**
 * نمایش اخطار در صورت عدم نصب ووکامرس
 */
function wsc_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php _e( 'افزونه اتصال به اسنپ شاپ برای کار کردن نیاز به نصب و فعال‌سازی ووکامرس دارد.', 'woo-snappshop-connector' ); ?></p>
	</div>
	<?php
}

/**
 * ثبت هوک فعال‌سازی (برای ایجاد جدول)
 */
register_activation_hook( __FILE__, array( 'WSC_Activator', 'activate' ) );

/**
 * بارگذاری فایل فعال‌ساز
 * ما نمی‌توانیم این فایل را در `init` بارگذاری کنیم چون هوک فعال‌سازی زودتر اجرا می‌شود.
 */
require_once WSC_PLUGIN_DIR . 'includes/class-wsc-activator.php';

/**
 * تزریق استایل‌های سفارشی برای ستون "وضعیت رقابت (اسنپ)" در لیست محصولات ووکامرس.
 */
function wsc_custom_competitor_price_column_styles() {
    // اطمینان حاصل می‌کنیم که فقط در صفحه لیست محصولات مدیریت (edit.php) این کد اجرا شود.
    // و همچنین تنها در بخش مدیریت (admin)
    global $typenow;
    if ( is_admin() && 'product' === $typenow ) {
        echo '<style type="text/css">';
        // تنظیم عرض ثابت برای ستون هدر و سلول‌های داده
        echo 'th.column-competitor_price, td.column-competitor_price {';
        echo '    width: 150px; /* عرض ستون را اینجا تنظیم کنید */';
        echo '    min-width: 150px; /* حداقل عرض */';
        echo '    box-sizing: border-box; /* اطمینان از محاسبه صحیح عرض */';
        echo '    word-wrap: break-word; /* اجازه شکستن کلمات طولانی */';
        echo '    white-space: normal; /* اجازه پیچیدن متن به حالت عادی */';
        echo '    vertical-align: top; /* محتوا از بالا تراز شود */';
        echo '}';
        // استایل برای متن کوچک داخل ستون (مانند "شما: X | رقبا: Y")
        echo 'td.column-competitor_price small {';
        echo '    white-space: nowrap; /* از پیچیدن این متن جلوگیری می‌کند */';
        echo '    display: block; /* برای اینکه white-space: nowrap به درستی کار کند */';
        echo '    overflow: hidden; /* پنهان کردن متن اضافی اگر خیلی طولانی شد */';
        echo '    text-overflow: ellipsis; /* نمایش ... برای متن‌های بریده شده */';
        echo '}';
        echo '</style>';
    }
}
add_action('admin_head', 'wsc_custom_competitor_price_column_styles');

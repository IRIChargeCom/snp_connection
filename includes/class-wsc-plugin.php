<?php // File: woo-snappshop-connector/includes/class-wsc-plugin.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس اصلی افزونه (الگوی Singleton)
 *
 * [نسخه 1.0.7] - بازگرداندن فایل جاب‌ها به load_dependencies
 */
final class WSC_Plugin {

	private static $instance = null;
	public $settings;
	public $api_service;
	public $product_hooks;
	public $order_manager;
	public $queue_manager;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'check_database_upgrade' ) );
		
		$this->load_dependencies();

		// مقداردهی اولیه کلاس‌های اصلی
		$this->settings      = new WSC_Settings();
		$options_snapshot    = $this->settings->get_options(); 
		$this->api_service   = new WSC_API_Service( $options_snapshot );
		
		$this->queue_manager = new WSC_Queue_Manager();
		$this->order_manager = new WSC_Order_Manager();
		$this->product_hooks = new WSC_Product_Hooks( $this->queue_manager );

		if ( is_admin() ) {
			new WSC_Admin_Menus();
			new WSC_Product_Admin();
			new WSC_Manual_Order_Page();
			new WSC_CSV_Import_Page();
			new WSC_Mapping_List_Page();

			// [GEMINI 1.1.7] - مقداردهی اولیه صفحه جدید محصولات و ثبت هوک Ajax آن
			require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-products-page.php';
			WSC_Products_Page::init();
		}

		add_action( 'init', array( $this, 'init' ) );
		// [MODIFIED] - این هوک دیگر نیازی نیست چون فایل جاب‌ها در load_dependencies بارگذاری می‌شود
		// add_action( 'plugins_loaded', array( $this, 'load_jobs' ) );
	}

	private function load_dependencies() {
		// کلاس‌های اصلی
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-settings.php';
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-api-service.php';
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-product-hooks.php';
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-product-manager.php';
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-mapping-repository.php';
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-order-manager.php';
		require_once WSC_PLUGIN_DIR . 'includes/class-wsc-queue-manager.php';

		// بخش‌های ادمین
		require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-admin-menus.php';
		require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-product-admin.php';
		require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-manual-order-page.php';
		require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-csv-import-page.php';
		require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-mapping-list-page.php'; 
		
		// [MODIFIED] - بازگرداندن فایل جاب‌ها به اینجا
		// این تضمین می‌کند که کلاس‌هایی مانند WSC_API_Service قبل از این فایل بارگذاری شده‌اند
		require_once WSC_PLUGIN_DIR . 'includes/wsc-jobs.php';
		
        if ( ! class_exists( 'ActionScheduler' ) && defined('WC_ABSPATH') ) {
            $action_scheduler_path = WC_ABSPATH . 'packages/action-scheduler/action-scheduler.php';
            if ( file_exists( $action_scheduler_path ) ) {
                require_once $action_scheduler_path;
            }
        }
	}
	
	public function load_jobs() {
		// [DEPRECATED]
	}

	public function init() {
		load_plugin_textdomain( 'woo-snappshop-connector', false, dirname( plugin_basename( WSC_PLUGIN_FILE ) ) . '/languages' );
		$this->order_manager->register_custom_order_statuses();
	}
	
	public function check_database_upgrade() {
		$current_db_version = get_option( 'wsc_db_version', '1.0.0' );
		
		if ( version_compare( $current_db_version, '1.0.1', '<' ) ) {
			if ( ! class_exists( 'WSC_Activator' ) ) {
				require_once WSC_PLUGIN_DIR . 'includes/class-wsc-activator.php';
			}
			WSC_Activator::upgrade_database_v_1_0_1();
			update_option( 'wsc_db_version', '1.0.1' );
		}
	}

	public function get_settings_options() {
		return $this->settings->get_options();
	}
}

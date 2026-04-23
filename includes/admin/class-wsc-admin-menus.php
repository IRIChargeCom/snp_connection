<?php // File: woo-snappshop-connector/includes/admin/class-wsc-admin-menus.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [نسخه 1.0.21] - جداسازی منطق POST به admin_init
 */
class WSC_Admin_Menus {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_dashboard_actions' ) );
		add_action( 'admin_notices', array( $this, 'show_fetch_status_notice' ) );

		// [GEMINI 1.3.4] - ثبت اکشن‌های ایجکس برای صفحه سفارش دستی
		WSC_Manual_Order_Page::init();
	}

	/**
	 * [جدید] - این تابع تمام پردازش‌های POST داشبورد را مدیریت می‌کند
	 */
		public function handle_dashboard_actions() {
			if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wsc-main-page' || ! isset( $_POST['wsc_action'] ) ) {
				return;
			}
			wsc_log_to_db('handle_dashboard_actions triggered.');
	    
			// مدیریت دکme پاک‌سازی (حالا قبل از نمایش پیام‌ها اجرا می‌شود)
			if ( $_POST['wsc_action'] === 'clear_mappings' ) {
				if ( check_admin_referer( 'wsc_clear_mappings_action' ) ) {
					wsc_log_to_db('Clear mappings action started.');
					$repo = new WSC_Mapping_Repository();
					$repo->clear_all_mappings();
					
					// [FIX] پاک کردن پیام وضعیت "در حال اجرا" که گیر کرده
					delete_option( 'wsc_fetch_status' );
					
					add_settings_error( 'wsc-notices', 'mappings-cleared', __( 'جدول نگاشت محصولات با موفقیت پاک‌سازی شد. **پیام وضعیت "در حال اجرا" نیز ریست شد.**', 'woo-snappshop-connector' ), 'success' );
					wsc_log_to_db('Clear mappings action finished.');
				}
			} 
			
			// مدیریت دکمه دریافت (GET)
			elseif ( $_POST['wsc_action'] === 'fetch_ids' ) {
				if ( check_admin_referer( 'wsc_fetch_ids_action' ) ) {
					wsc_log_to_db('Fetch IDs action started.');
					$saved_options = get_option( 'wsc_settings' );
					if ( empty( $saved_options['api_token'] ) || empty( $saved_options['api_user_agent'] ) || empty( $saved_options['api_vendor_id'] ) ) {
						add_settings_error( 'wsc-notices', 'settings-missing', __( 'خطا: اطلاعات API (توکن، User-Agent، Vendor ID) در صفحه «تنظیمات» ذخیره نشده‌اند. لطفاً ابتدا آن‌ها را ذخیره کرده و سپس مجدداً تلاش کنید.', 'woo-snappshop-connector' ), 'error' );
					}
					elseif ( is_array( get_option( 'wsc_fetch_status' ) ) ) {
						add_settings_error( 'wsc-notices', 'job-running', __( 'عملیات همگام‌سازی دیگری در حال اجرا است. لطفاً منتظر بمانید تا تمام شود.', 'woo-snappshop-connector' ), 'warning' );
					}
					else {
						// [NEW] Clear the old debug log before starting a new run.
						delete_option( 'wsc_debug_log' );
						wsc_log_to_db('Old debug log cleared.');
	
						wsc_log_to_db('Before clear_all_mappings...');
						$repo = new WSC_Mapping_Repository();
						$repo->clear_all_mappings(); 
						wsc_log_to_db('After clear_all_mappings.');
	
						update_option( 'wsc_fetch_status', 'starting' );
						wsc_log_to_db('Fetch status set to "starting".');
						
						$hook_name = 'wsc_run_id_fetch_job';
						$hook_args = array( 'page' => 1 );
	
						$queue = new WSC_Queue_Manager();
						$queue->schedule_id_fetch_job( 1 );
						wsc_log_to_db('Scheduled job for page 1.');
						
						set_transient( 'wsc_last_queued_job_debug', array('hook' => $hook_name, 'args' => $hook_args), 30 );
						wp_redirect( admin_url( 'admin.php?page=wsc-main-page&status=started' ) );
						exit;
					}
				}
			} 
			
			// مدیریت دکمه تست (PATCH)
			elseif ( $_POST['wsc_action'] === 'test_patch_job' ) {			if ( check_admin_referer( 'wsc_test_patch_job_action' ) ) {
                $saved_options = get_option( 'wsc_settings' );
                if ( empty( $saved_options['api_token'] ) ) {
					add_settings_error( 'wsc-notices', 'settings-missing', __( 'خطا: ابتدا تنظیمات را در صفحه «تنظیمات» ذخیره کنید.', 'woo-snappshop-connector' ), 'error' );
                } else {
                    $hook_name = 'wsc_run_test_patch_job';
                    $hook_args = array(); 
                    
                    as_enqueue_async_action( $hook_name, $hook_args, 'wsc-batch-sync' );
                    set_transient( 'wsc_last_queued_job_debug', array('hook' => $hook_name, 'args' => $hook_args), 30 );
					wp_redirect( admin_url( 'admin.php?page=wsc-main-page&status=test_patch_started' ) );
					exit;
                }
            }
		}
	}

	/**
	 * افزودن منوهای افزونه
	 */
	 
	public function add_plugin_menu() {
		// ... (کد منو بدون تغییر) ...
		add_menu_page(__( 'اسنپ شاپ', 'woo-snappshop-connector' ),__( 'اسنپ شاپ', 'woo-snappshop-connector' ),'manage_woocommerce','wsc-main-page',array( $this, 'render_main_page' ),'dashicons-store',56);
		add_submenu_page('wsc-main-page',__( 'داشبورد', 'woo-snappshop-connector' ),__( 'داشبورد', 'woo-snappshop-connector' ),'manage_woocommerce','wsc-main-page',array( $this, 'render_main_page' ));
		add_submenu_page('wsc-main-page',__( 'تنظیمات اسنپ شاپ', 'woo-snappshop-connector' ),__( 'تنظیمات', 'woo-snappshop-connector' ),'manage_options','wsc-settings-page',array( $this, 'render_settings_page' ));
		add_submenu_page('wsc-main-page',__( 'ثبت سفارش دستی اسنپ شاپ', 'woo-snappshop-connector' ),__( 'ثبت سفارش دستی', 'woo-snappshop-connector' ),'edit_shop_orders','wsc-manual-order',array( 'WSC_Manual_Order_Page', 'render' ));
		add_submenu_page('wsc-main-page',__( 'درون‌ریزی قیمت رقبا', 'woo-snappshop-connector' ),__( 'درون‌ریزی CSV', 'woo-snappshop-connector' ),'manage_woocommerce','wsc-csv-import',array( 'WSC_CSV_Import_Page', 'render' ));
		add_submenu_page('wsc-main-page',__( 'گزارش همگام‌سازی محصولات', 'woo-snappshop-connector' ),__( 'گزارش همگام‌سازی', 'woo-snappshop-connector' ),'manage_woocommerce','wsc-mapped-products',array( 'WSC_Mapping_List_Page', 'render' ));
		
		// [GEMINI 1.1.5] - افزودن صفحه جدید "محصولات"
		add_submenu_page('wsc-main-page',__( 'محصولات اسنپ شاپ', 'woo-snappshop-connector' ),__( 'محصولات', 'woo-snappshop-connector' ),'manage_woocommerce','wsc-products-page',array( $this, 'render_products_page' ));
	}
	

	/**
	 * پیام وضعیت همگام‌سازی را در بالای پنل مدیریت نمایش می‌دهد
	 */
	public function show_fetch_status_notice() {
		
		settings_errors( 'wsc-notices' );
		
		// ... (کد نمایش خطای جاب تست PATCH بدون تغییر) ...
		$patch_status = get_option( 'wsc_test_patch_status' );
		if ( ! empty( $patch_status ) ) {
			if ( strpos( $patch_status, 'failed' ) === 0 ) {
				$error_detail = esc_html( str_replace( 'failed: ', '', $patch_status ) );
				$message = sprintf(__( 'خطا: **جاب تست PATCH** ناموفق بود. **علت:** %s', 'woo-snappshop-connector' ),'<strong>' . $error_detail . '</strong>');
				echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
			} elseif ( strpos( $patch_status, 'success' ) === 0 ) {
				$success_detail = esc_html( str_replace( 'success: ', '', $patch_status ) );
				$message = sprintf(__( 'موفقیت: **جاب تست PATCH** با موفقیت اجرا شد. **پاسخ API:** %s', 'woo-snappshop-connector' ),'<strong>' . $success_detail . '</strong>');
				echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
			} elseif ( $patch_status === 'starting' ) {
				$message = __( 'جاب تست PATCH در حال اجرا است...', 'woo-snappshop-connector' );
				echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
			}
			delete_option( 'wsc_test_patch_status' );
		}
		
		$current_screen = get_current_screen();
		if ( ! $current_screen || strpos( $current_screen->id, 'wsc-' ) === false ) {
			return;
		}
		$status = get_option( 'wsc_fetch_status' );
		if ( empty( $status ) ) {
			return;
		}
		if ( is_array( $status ) && isset( $status['current'] ) && isset( $status['total'] ) ) {
			$percent = ( $status['total'] > 0 ) ? ( $status['current'] / $status['total'] ) * 100 : 0;
			$message = sprintf(
				__( 'همگام‌سازی محصولات اسنپ شاپ در حال اجرا است... **صفحه %d از %d** (%.0f%% کامل شده). لطفاً این صفحه را رفرش کنید تا پیشرفت را ببینید.', 'woo-snappshop-connector' ),
				$status['current'],
				$status['total'],
				$percent
			);
			echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
		} elseif ( $status === 'starting' ) {
			$message = __( 'عملیات دریافت محصولات در پس‌زمینه آغاز شد... در حال محاسبه تعداد کل محصولات.', 'woo-snappshop-connector' );
			echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
		} elseif ( $status === 'complete' ) {
			$message = __( 'عملیات همگام‌سازی محصولات اسنپ شاپ با موفقیت تمام شد. می‌توانید نتیجه را در صفحه «گزارش همگام‌سازی» ببینید.', 'woo-snappshop-connector' );
			echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
			delete_option( 'wsc_fetch_status' );
		} elseif ( strpos( $status, 'failed' ) === 0 ) {
			$error_detail = esc_html( str_replace( 'failed: ', '', $status ) );
			if ( $error_detail === 'failed' || empty($error_detail) ) {
				 $error_detail = __( 'خطای نامشخص. لطفاً لاگ‌های Action Scheduler را بررسی کنید.', 'woo-snappshop-connector' );
			}
			$message = sprintf(
				__( 'خطا: عملیات همگام‌سازی محصولات اسنپ شاپ ناموفق بود. **علت:** %s', 'woo-snappshop-connector' ),
				'<strong>' . $error_detail . '</strong>'
			);
			echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
			delete_option( 'wsc_fetch_status' );
		}
	}


	/**
	 * رندر صفحه اصلی (داشبورد)
	 */
	public function render_main_page() {
		
		?>
		<div class="wrap">
			<h1><?php _e( 'داشبورد اتصال به اسنپ شاپ', 'woo-snappshop-connector' ); ?></h1>

			<?php
			// ... (کدهای دیباگ داشبورد بدون تغییر) ...
			$last_job = get_transient( 'wsc_last_queued_job_debug' );
			if ( $last_job ) {
				delete_transient( 'wsc_last_queued_job_debug' );
				echo '<h2>' . __( 'دیباگ: آخرین جاب ارسال شده به صف', 'woo-snappshop-connector' ) . '</h2>';
				echo '<div class="notice notice-info" style="padding: 10px; border-left-color: #00a0d2;">';
				echo '<p>' . __( 'درخواست شما با موفقیت به صف (Action Scheduler) ارسال شد. این اطلاعات دقیقاً همان چیزی است که به جاب پس‌زمینه ارسال گردید:', 'woo-snappshop-connector' ) . '</p>';
				echo '<h3>' . __( 'نام هوک (Hook):', 'woo-snappshop-connector' ) . '</h3>';
				echo '<pre style="direction: ltr; background: #fff; padding: 5px;">' . esc_html( $last_job['hook'] ) . '</pre>';
				echo '<h3>' . __( 'آرگومان‌های ارسالی (Args):', 'woo-snappshop-connector' ) . '</h3>';
				echo '<pre style="direction: ltr; background: #fff; padding: 10px; border: 1px solid #ccc; overflow-x: auto; max-height: 300px;">';
				print_r( $last_job['args'] );
				echo '</pre>';
				echo '</div>';
			}
			$saved_options_debug = get_option( 'wsc_settings' );
			if ( ! empty( $saved_options_debug ) ) {
				echo '<h2>' . __( 'دیباگ: اطلاعات ذخیره شده در دیتابیس (get_option)', 'woo-snappshop-connector' ) . '</h2>';
				echo '<div class="notice notice-warning" style="padding: 10px;">';
				echo '<p>' . __( 'این اطلاعاتی است که در حال حاضر در دیتابیس شما ذخیره شده است (جاب باید از این اطلاعات استفاده کند):', 'woo-snappshop-connector' ) . '</p>';
				echo '<pre style="direction: ltr; background: #f9f9f9; padding: 10px; border: 1px solid #ccc; overflow-x: auto;">';
				print_r( $saved_options_debug );
				echo '</pre>';
				echo '</div>';
			}
			?>
			
			<p><?php _e( 'به افزونه اتصال ووکامرس به اسنپ شاپ خوش آمدید.', 'woo-snappshop-connector' ); ?></p>
			
			<h2><?php _e( 'عملیات‌های همگام‌سازی', 'woo-snappshop-connector' ); ?></h2>
			<div class="card">
				<h3 class="title"><?php _e( 'دریافت محصولات از اسنپ شاپ (GET)', 'woo-snappshop-connector' ); ?></h3>
				<p><?php _e( 'برای شروع، لیست محصولات خود در اسنپ شاپ را دریافت کنید تا جدول نگاشت (ارتباط SKU با ID اسنپ) ساخته شود.', 'woo-snappshop-connector' ); ?></p>
				<p><strong><?php _e( 'هشدار: اجرای این عملیات، تمام رکوردهای نگاشت قبلی را پاک کرده و از نو می‌سازد.', 'woo-snappshop-connector' ); ?></strong></p>
				
				<form method="post" action="" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').textContent = '<?php _e( 'در حال ارسال به صف...', 'woo-snappshop-connector' ); ?>';">
					<?php wp_nonce_field( 'wsc_fetch_ids_action' ); ?>
					<input type="hidden" name="wsc_action" value="fetch_ids" />
					<button type="submit" class="button button-primary"><?php _e( 'شروع دریافت و بازسازی نگاشت محصولات', 'woo-snappshop-connector' ); ?></button>
					<p class="description">
						<?php _e( 'این عملیات در پس‌زمینه اجرا می‌شود. شما می‌توانید پیشرفت را از پیام‌های بالای صفحه دنبال کنید.', 'woo-snappshop-connector' ); ?>
					</p>
				</form>
			</div>

			<div class="card" style="border-color: #f0b849;">
				<h3 class="title"><?php _e( 'تست همگام‌سازی (PATCH در پس‌زمینه)', 'woo-snappshop-connector' ); ?></h3>
				<p><?php _e( 'این دکمه یک جاب (Job) پس‌زمینه (درست مانند جاب "دریافت") اجرا می‌کند تا یک محصول را با SKU 101010400، قیمت 11000 و موجودی 5 آپدیت کند.', 'woo-snappshop-connector' ); ?></p>
				<p><strong><?php _e( 'این یک تست حیاتی است:', 'woo-snappshop-connector' ); ?></strong> <?php _e( 'اگر این تست موفق شود ولی تست "دریافت" شکست بخورد، مشکل فقط از درخواست GET است. اگر این تست هم شکست بخvrd، مشکل از کل محیط پس‌زمینه (WP Rocket/Cron) است.', 'woo-snappshop-connector' ); ?></p>
				
				<form method="post" action="">
					<?php wp_nonce_field( 'wsc_test_patch_job_action' ); ?>
					<button type="submit" name="wsc_action" value="test_patch_job" class="button button-primary" style="background-color: #f0b849; border-color: #f0b849;"><?php _e( 'شروع تست آپدیت (PATCH) در پس‌زمینه', 'woo-snappshop-connector' ); ?></button>
				</form>
			</div>
			
			<div class="card">
				<h3 class="title"><?php _e( 'وضعیت صف (Queue)', 'woo-snappshop-connector' ); ?></h3>
				<p><?php _e( 'این بخش، یک نمای فنی از کارهای در حال اجرای پس‌زمینه را نشان می‌دهد (نه لیست محصولات).', 'woo-snappshop-connector' ); ?></p>
				<?php
				if ( function_exists( 'as_get_scheduled_actions' ) ) {
					$pending_count = as_get_scheduled_actions( array( 'status' => 'pending' ), 'count' );
					$failed_count = as_get_scheduled_actions( array( 'status' => 'failed' ), 'count' );
					
					echo '<p>' . sprintf( __( 'تعداد %d کار در انتظار اجرا.', 'woo-snappshop-connector' ), $pending_count ) . '</p>';
					if ( $failed_count > 0 ) {
						echo '<p style="color: red; font-weight: bold;">' . sprintf( __( 'تعداد %d کار ناموفق وجود دارد.', 'woo-snappshop-connector' ), $failed_count ) . '</p>';
					}
				} else {
					echo '<p style="color: red;">' . __( 'کتابخانه Action Scheduler یافت نشد. لطفاً مطمئن شوید ووکامرس فعال است.', 'woo-snappshop-connector' ) . '</p>';
				}
				?>
				<a href="<?php echo admin_url('admin.php?page=wc-status&tab=action-scheduler'); ?>" class="button"><?php _e( 'مشاهده جزئیات صف پردازش (فنی)', 'woo-snappshop-connector' ); ?></a>
			</div>
			
			<div class="card" style="border-color: #dc3232;">
				<h3 class="title"><?php _e( 'عملیات‌های خطرناک', 'woo-snappshop-connector' ); ?></h3>
				<p><?php _e( 'از این دکمه برای پاک کردن کامل جدول نگاشت محصولات استفاده کنید. (برای زمانی که تست شما ناقص مانده)', 'woo-snappshop-connector' ); ?></p>
				<form method="post" action="" onsubmit="return confirm('<?php _e( 'آیا مطمئن هستید؟ این کار تمام اطلاعات جدول نگاشت را حذف می‌کند.', 'woo-snappshop-connector' ); ?>');">
					<?php wp_nonce_field( 'wsc_clear_mappings_action' ); ?>
					<button type="submit" name="wsc_action" value="clear_mappings" class="button button-secondary" style="color: #dc3232; border-color: #dc3232;"><?php _e( 'پاک‌سازی کامل جدول نگاشت', 'woo-snappshop-connector' ); ?></button>
				</form>
			</div>

			<?php
			// [GEMINI 1.2.7] - بازگرداندن بخش لاگ دیباگ زنده
			if ( get_option('wsc_settings')['debug_mode'] ?? false ) :
				$debug_log = get_option( 'wsc_debug_log', array() );
				if ( ! empty( $debug_log ) && is_array( $debug_log ) ) :
					?>
					<div class="card">
						<h3 class="title"><?php _e( 'لاگ دیباگ زنده', 'woo-snappshop-connector' ); ?></h3>
						<p><?php _e( 'این لاگ‌ها به صورت زنده از اجرای جاب‌های پس‌زمینه ارسال می‌شوند. جدیدترین لاگ در بالا نمایش داده می‌شود.', 'woo-snappshop-connector' ); ?></p>
						<pre style="background: #f1f1f1; border: 1px solid #ccc; padding: 10px; max-height: 400px; overflow-y: scroll; direction: ltr; text-align: left;">
							<?php
							// نمایش لاگ‌ها از جدید به قدیم
							$reversed_logs = array_reverse( $debug_log );
							foreach ( $reversed_logs as $log_entry ) {
								echo esc_html( $log_entry ) . "\n";
							}
							?>
						</pre>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
		</div>
		<?php
	}

	/**
	 * رندر صفحه تنظیمات
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'تنظیمات اتصال به اسنپ شاپ', 'woo-snappshop-connector' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				// خروجی فیلدهای ثبت شده
				settings_fields( 'wsc_settings_group' );
				do_settings_sections( 'wsc-settings-page' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * [GEMINI 1.1.5] - رندر صفحه جدید "محصولات"
	 */
	public function render_products_page() {
		// فایل کلاس صفحه جدید را فراخوانی می‌کنیم
		require_once WSC_PLUGIN_DIR . 'includes/admin/class-wsc-products-page.php';
		// متد استاتیک رندر را برای نمایش محتوا اجرا می‌کنیم
		WSC_Products_Page::render();
	}
}

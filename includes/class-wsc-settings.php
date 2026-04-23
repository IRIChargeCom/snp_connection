<?php // File: woo-snappshop-connector/includes/class-wsc-settings.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Settings
 *
 * [نسخه 1.0.7] - افزودن دکمه تست PATCH
 */
class WSC_Settings {

	public $options;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_wsc_test_connection', array( $this, 'ajax_test_connection' ) );
		// [NEW CODE] - افزودن هوک AJAX برای تست PATCH
		add_action( 'wp_ajax_wsc_test_patch', array( $this, 'ajax_test_patch_request' ) );
	}

	/**
	 * دریافت تنظیمات ذخیره شده
	 */
	public function get_options() {
		if ( ! empty( $this->options ) ) {
			return $this->options;
		}
		
		$this->options = get_option( 'wsc_settings' );
		return $this->options;
	}

	/**
	 * ثبت تنظیمات، بخش‌ها و فیلدها
	 */
	public function register_settings() {
		register_setting(
			'wsc_settings_group',
			'wsc_settings',
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'wsc_api_section',
			__( 'تنظیمات API اسنپ شاپ', 'woo-snappshop-connector' ),
			array( $this, 'print_api_section_info' ),
			'wsc-settings-page'
		);

		add_settings_field(
			'api_user_agent',
			__( 'کد یکتای شناسایی (User-Agent)', 'woo-snappshop-connector' ),
			array( $this, 'render_text_field' ),
			'wsc-settings-page',
			'wsc_api_section',
			array( 'id' => 'api_user_agent', 'desc' => __( 'این کد برای شناسایی شما در API اسنپ شاپ ضروری است.', 'woo-snappshop-connector' ) )
		);

		add_settings_field(
			'api_token',
			__( 'توکن دسترسی (Bearer Token)', 'woo-snappshop-connector' ),
			array( $this, 'render_password_field' ),
			'wsc-settings-page',
			'wsc_api_section',
			array( 'id' => 'api_token', 'desc' => __( 'توکن دریافتی از پنل فروشندگان اسنپ شاپ.', 'woo-snappshop-connector' ) )
		);

		add_settings_field(
			'api_vendor_id',
			__( 'شناسه فروشگاه (Vendor ID)', 'woo-snappshop-connector' ),
			array( $this, 'render_text_field' ),
			'wsc-settings-page',
			'wsc_api_section',
			array( 'id' => 'api_vendor_id', 'desc' => __( 'شناسه فروشگاه شما در اسنپ شاپ (مثال: gRppvW).', 'woo-snappshop-connector' ) )
		);
		
		add_settings_field(
			'api_test_button',
			__( '۱. تست اتصال (GET)', 'woo-snappshop-connector' ), // [MODIFIED] - تغییر عنوان
			array( $this, 'render_test_connection_button' ),
			'wsc-settings-page',
			'wsc_api_section'
		);

		// [NEW CODE START] - افزودن دکمه تست PATCH
		add_settings_field(
			'api_test_patch_button',
			__( '۲. تست آپدیت (PATCH)', 'woo-snappshop-connector' ),
			array( $this, 'render_test_patch_button' ),
			'wsc-settings-page',
			'wsc_api_section'
		);
		// [NEW CODE END]
		
		add_settings_section(
			'wsc_sync_section',
			__( 'تنظیمات همگام‌سازی', 'woo-snappshop-connector' ),
			null,
			'wsc-settings-page'
		);
		
		add_settings_field(
			'default_price_percent',
			__( 'درصد افزایش قیمت سراسری', 'woo-snappshop-connector' ),
			array( $this, 'render_number_field' ),
			'wsc-settings-page',
			'wsc_sync_section',
			array( 'id' => 'default_price_percent', 'desc' => __( 'یک درصد برای افزایش خودکار قیمت‌ها نسبت به ووکامرس (مثلاً 5). خالی بگذارید تا اعمال نشود.', 'woo-snappshop-connector' ) )
		);

		// [GEMINI 1.1.1] - افزودن فیلد حالت اشکال‌زدایی
		add_settings_field(
			'debug_mode',
			__( 'حالت اشکال‌زدایی (Debug Mode)', 'woo-snappshop-connector' ),
			array( $this, 'render_checkbox_field' ),
			'wsc-settings-page',
			'wsc_sync_section',
			array( 'id' => 'debug_mode', 'desc' => __( 'با فعال کردن این گزینه، تمام عملیات‌ها در یک لاگ اختصاصی ثبت می‌شوند تا بررسی و اشکال‌زدایی آسان‌تر شود.', 'woo-snappshop-connector' ) )
		);

		// [GEMINI 1.2.0] - افزودن فیلدهای سراسری تخفیف
		add_settings_field(
			'global_discount_percent',
			__( 'درصد تخفیف سراسری', 'woo-snappshop-connector' ),
			array( $this, 'render_number_field' ),
			'wsc-settings-page',
			'wsc_sync_section',
			array( 'id' => 'global_discount_percent', 'desc' => __( 'یک درصد برای اعمال تخفیف سراسری روی محصولات (مثلاً 15). این درصد از قیمت نهایی (بعد از درصد افزایش) کسر می‌شود.', 'woo-snappshop-connector' ) )
		);

		add_settings_field(
			'global_start_date',
			__( 'تاریخ شروع تخفیف سراسری', 'woo-snappshop-connector' ),
			array( $this, 'render_text_field' ),
			'wsc-settings-page',
			'wsc_sync_section',
			array( 'id' => 'global_start_date', 'desc' => __( 'تاریخ شروع اعمال تخفیف‌ها در اسنپ. فرمت: YYYY-MM-DD (مثال: 2024-05-21)', 'woo-snappshop-connector' ) )
		);

		add_settings_field(
			'global_end_date',
			__( 'تاریخ پایان تخفیف سراسری', 'woo-snappshop-connector' ),
			array( $this, 'render_text_field' ),
			'wsc-settings-page',
			'wsc_sync_section',
			array( 'id' => 'global_end_date', 'desc' => __( 'تاریخ پایان اعمال تخفیف‌ها در اسنپ. فرمت: YYYY-MM-DD (مثال: 2024-06-21)', 'woo-snappshop-connector' ) )
		);
	}

	/**
	 * توابع رندر کردن فیلدها
	 */
	public function render_text_field( $args ) {
		$options = $this->get_options();
		$value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		printf(
			'<input type="text" id="%s" name="wsc_settings[%s]" value="%s" class="regular-text ltr" /><p class="description">%s</p>',
			esc_attr( $args['id'] ),
			esc_attr( $args['id'] ),
			esc_attr( $value ),
			esc_html( $args['desc'] )
		);
	}

	// [GEMINI 1.1.1] - تابع جدید برای رندر کردن چک‌باکس
	public function render_checkbox_field( $args ) {
		$options = $this->get_options();
		$checked = isset( $options[ $args['id'] ] ) && $options[ $args['id'] ] == 1 ? 'checked' : '';
		printf(
			'<input type="checkbox" id="%s" name="wsc_settings[%s]" value="1" %s /> <label for="%s">%s</label>',
			esc_attr( $args['id'] ),
			esc_attr( $args['id'] ),
			$checked,
			esc_attr( $args['id'] ),
			esc_html( $args['desc'] )
		);
	}

	public function render_password_field( $args ) {
		$options = $this->get_options();
		$value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		printf(
			'<input type="password" id="%s" name="wsc_settings[%s]" value="%s" class="regular-text ltr" /><p class="description">%s</p>',
			esc_attr( $args['id'] ),
			esc_attr( $args['id'] ),
			esc_attr( $value ),
			esc_html( $args['desc'] )
		);
	}
	
	public function render_number_field( $args ) {
		$options = $this->get_options();
		$value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		printf(
			'<input type="number" id="%s" name="wsc_settings[%s]" value="%s" class="small-text" step="0.1" min="0" /><p class="description">%s</p>',
			esc_attr( $args['id'] ),
			esc_attr( $args['id'] ),
			esc_attr( $value ),
			esc_html( $args['desc'] )
		);
	}

	public function print_api_section_info() {
		echo '<p>' . __( 'اطلاعات مورد نیاز برای احراز هویت در API اسنپ شاپ را وارد کنید.', 'woo-snappshop-connector' ) . '</p>';
	}

	public function sanitize_settings( $input ) {
		$sanitized_input = array();
		if ( isset( $input['api_user_agent'] ) ) {
			$sanitized_input['api_user_agent'] = sanitize_text_field( $input['api_user_agent'] );
		}
		if ( isset( $input['api_token'] ) ) {
			$sanitized_input['api_token'] = trim( $input['api_token'] );
		}
		if ( isset( $input['api_vendor_id'] ) ) {
			$sanitized_input['api_vendor_id'] = sanitize_text_field( $input['api_vendor_id'] );
		}
		if ( isset( $input['default_price_percent'] ) ) {
			$sanitized_input['default_price_percent'] = (float) $input['default_price_percent'];
		}

		// [GEMINI 1.1.1] - افزودن منطق ذخیره‌سازی برای حالت اشکال‌زدایی
		$sanitized_input['debug_mode'] = ! empty( $input['debug_mode'] ) ? 1 : 0;

		// [GEMINI 1.2.0] - افزودن منطق ذخیره‌سازی برای فیلدهای تخفیف
		if ( isset( $input['global_discount_percent'] ) ) {
			$sanitized_input['global_discount_percent'] = (float) $input['global_discount_percent'];
		}
		if ( isset( $input['global_start_date'] ) ) {
			$sanitized_input['global_start_date'] = sanitize_text_field( $input['global_start_date'] );
		}
		if ( isset( $input['global_end_date'] ) ) {
			$sanitized_input['global_end_date'] = sanitize_text_field( $input['global_end_date'] );
		}

		return $sanitized_input;
	}
	
	/**
	 * رندر دکمه تست اتصال (GET)
	 */
	public function render_test_connection_button() {
		?>
		<button type="button" id="wsc-test-connection-btn" class="button">
			<?php _e( 'تست اتصال (GET)', 'woo-snappshop-connector' ); ?>
		</button>
		<span id="wsc-test-connection-spinner" class="spinner"></span>
		<div id="wsc-test-connection-result" style="margin-top: 10px; font-weight: bold; display: inline-block;"></div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wsc-test-connection-btn').on('click', function(e) {
					e.preventDefault();
					
					var $btn = $(this);
					var $spinner = $('#wsc-test-connection-spinner');
					var $resultDiv = $('#wsc-test-connection-result');
					
					$btn.prop('disabled', true);
					$spinner.addClass('is-active');
					$resultDiv.empty().removeClass('notice notice-success notice-error').hide();

					var data = {
						action: 'wsc_test_connection',
						_ajax_nonce: '<?php echo wp_create_nonce( "wsc_test_connection_nonce" ); ?>',
						api_user_agent: $('#api_user_agent').val(),
						api_token: $('#api_token').val(),
						api_vendor_id: $('#api_vendor_id').val()
					};

					$.post(ajaxurl, data, function(response) {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
						
						if (response.success) {
							$resultDiv.html('<p>' + response.data.message + '</p>').addClass('notice notice-success').show();
						} else {
							$resultDiv.html('<p>' + response.data.message + '</p>').addClass('notice notice-error').show();
						}
					});
				});
			});
		</script>
		<?php
	}

	// [NEW CODE START] - دکمه و اسکریپت تست PATCH
	/**
	 * رندر دکمه تست آپدیت (PATCH)
	 */
	public function render_test_patch_button() {
		?>
		<button type="button" id="wsc-test-patch-btn" class="button button-primary">
			<?php _e( 'تست آپدیت محصول (PATCH)', 'woo-snappshop-connector' ); ?>
		</button>
		<span id="wsc-test-patch-spinner" class="spinner"></span>
		<div id="wsc-test-patch-result" style="margin-top: 10px; font-weight: bold; display: inline-block;"></div>
		<p class="description"><?php _e( 'هشدار: این دکمه یک درخواست واقعی برای آپدیت SKU 101010400 به قیمت 222 و موجودی 2 ارسال می‌کند.', 'woo-snappshop-connector' ); ?></p>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wsc-test-patch-btn').on('click', function(e) {
					e.preventDefault();
					
					var $btn = $(this);
					var $spinner = $('#wsc-test-patch-spinner');
					var $resultDiv = $('#wsc-test-patch-result');
					
					if ( ! confirm("<?php _e( 'آیا مطمئن هستید؟ این یک آپدیت واقعی روی محصول با SKU 101010400 انجام خواهد داد.', 'woo-snappshop-connector' ); ?>") ) {
						return;
					}

					$btn.prop('disabled', true);
					$spinner.addClass('is-active');
					$resultDiv.empty().removeClass('notice notice-success notice-error').hide();

					var data = {
						action: 'wsc_test_patch',
						_ajax_nonce: '<?php echo wp_create_nonce( "wsc_test_patch_nonce" ); ?>',
						// ما اطلاعات احراز هویت را از فیلدها می‌خوانیم
						api_user_agent: $('#api_user_agent').val(),
						api_token: $('#api_token').val(),
						api_vendor_id: $('#api_vendor_id').val()
					};

					$.post(ajaxurl, data, function(response) {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
						
						if (response.success) {
							$resultDiv.html('<p>' + response.data.message + '</p>').addClass('notice notice-success').show();
						} else {
							$resultDiv.html('<p>' + response.data.message + '</p>').addClass('notice notice-error').show();
						}
					});
				});
			});
		</script>
		<?php
	}
	// [NEW CODE END]

	/**
	 * مدیریت درخواست AJAX برای تست اتصال (GET)
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'wsc_test_connection_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-snappshop-connector' ) ), 403 );
		}

		$test_options = array(
			'api_user_agent' => sanitize_text_field( $_POST['api_user_agent'] ),
			'api_token'      => trim( $_POST['api_token'] ),
			'api_vendor_id'  => sanitize_text_field( $_POST['api_vendor_id'] ),
		);
		
		$api_service = new WSC_API_Service( $test_options );
		
		$result = $api_service->test_connection();

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			wp_send_json_error( array( 'message' => '❌ ' . $error_message ), 400 );
		} else {
			$store_title = isset($result['data']['title']) ? $result['data']['title'] : 'ناشناخته';
			$message = sprintf(
				__( 'اتصال با موفقیت برقرار شد. ✅ (نام فروشگاه: %s)', 'woo-snappshop-connector' ),
				esc_html( $store_title )
			);
			wp_send_json_success( array( 'message' => $message ) );
		}
	}

	// [NEW CODE START] - تابع مدیریت AJAX برای تست PATCH
	/**
	 * مدیریت درخواست AJAX برای تست آپدیت (PATCH)
	 */
	public function ajax_test_patch_request() {
		check_ajax_referer( 'wsc_test_patch_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-snappshop-connector' ) ), 403 );
		}

		$test_options = array(
			'api_user_agent' => sanitize_text_field( $_POST['api_user_agent'] ),
			'api_token'      => trim( $_POST['api_token'] ),
			'api_vendor_id'  => sanitize_text_field( $_POST['api_vendor_id'] ),
		);
		
		// ایجاد سرویس API با اطلاعات تست
		$api_service = new WSC_API_Service( $test_options );
		
		// فراخوانی متد جدید تست PATCH
		$result = $api_service->test_patch_request(); // متد جدید

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			wp_send_json_error( array( 'message' => '❌ ' . $error_message ), 400 );
		} else {
			// بررسی پاسخ واقعی از API
			$status = isset($result['data'][0]['status']) ? $result['data'][0]['status'] : false;
			if ($status === true) {
				$message = __( 'تست آپدیت (PATCH) با موفقیت انجام شد. ✅ محصول در اسنپ شاپ آپدیت شد.', 'woo-snappshop-connector' );
				wp_send_json_success( array( 'message' => $message ) );
			} else {
				$api_message = isset($result['data'][0]['messages'][0]) ? $result['data'][0]['messages'][0] : 'خطای ناشناخته';
				$message = sprintf( __( 'تست (PATCH) ناموفق بود. API خطا برگرداند: %s', 'woo-snappshop-connector' ), $api_message );
				wp_send_json_error( array( 'message' => '❌ ' . $message ), 400 );
			}
		}
	}
	// [NEW CODE END]
}

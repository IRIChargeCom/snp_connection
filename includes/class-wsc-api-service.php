<?php // File: woo-snappshop-connector/includes/class-wsc-api-service.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_API_Service
 *
 * [نسخه 1.0.7] - افزودن متد test_patch_request
 */
class WSC_API_Service {

	private $base_url = 'https://apix.snappshop.ir/automation/v1';
	private $options; // این اکنون آرایه تنظیمات است

	/**
	 * @param array $options_snapshot
	 */
	public function __construct( $options_snapshot ) {
		$this->options = $options_snapshot;
	}

	/**
	 * دریافت هدرهای لازم برای احراز هویت
	 * @return array|false
	 */
	private function get_headers() {
		$token = isset( $this->options['api_token'] ) ? $this->options['api_token'] : '';
		$user_agent = isset( $this->options['api_user_agent'] ) ? $this->options['api_user_agent'] : '';

		if ( empty( $token ) || empty( $user_agent ) ) {
			error_log( 'WSC Error: Snapp! Shop API Token or User-Agent is missing.' );
			return false;
		}

		return array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => $user_agent,
			'Authorization' => 'Bearer ' . $token,
		);
	}
	
	/**
	 * تست اتصال به API (GET)
	 * @return array|WP_Error
	 */
	public function test_connection() {
		$headers = $this->get_headers();
		if ( ! $headers ) {
			return new WP_Error( 'auth_failed', __( 'اطلاعات احراز هویت (توکن یا User-Agent) در تنظیمات افزونه وارد نشده است.', 'woo-snappshop-connector' ) );
		}

		$vendor_id = isset( $this->options['api_vendor_id'] ) ? $this->options['api_vendor_id'] : '';
		if ( empty( $vendor_id ) ) {
			return new WP_Error( 'no_vendor_id', __( 'شناسه فروشگاه (Vendor ID) اسنپ شاپ تنظیم نشده.', 'woo-snappshop-connector' ) );
		}
		
		$url = "{$this->base_url}/vendors/{$vendor_id}";

		$response = wp_remote_get( $url, array(
			'headers' => $headers,
			'timeout' => 20,
		) );

		return $this->handle_response( $response );
	}

	// [NEW CODE START] - متد تست PATCH
	/**
	 * تست آپدیت به API (PATCH) با یک محصول ثابت
	 * @return array|WP_Error
	 
	public function test_patch_request() {
		$headers = $this->get_headers();
		if ( ! $headers ) {
			return new WP_Error( 'auth_failed', __( 'اطلاعات احراز هویت اسنپ شاپ کامل نیست.', 'woo-snappshop-connector' ) );
		}

		$vendor_id = isset( $this->options['api_vendor_id'] ) ? $this->options['api_vendor_id'] : '';
		if ( empty( $vendor_id ) ) {
			return new WP_Error( 'no_vendor_id', __( 'شناسه فروشگاه (Vendor ID) اسنپ شاپ تنظیم نشده.', 'woo-snappshop-connector' ) );
		}
		
		$url = "{$this->base_url}/vendors/{$vendor_id}/products";

		// پیلود (Payload) ثابت طبق درخواست شما
		$test_payload = array(
			'products' => array(
				array(
					'sku'   => '101010400',
					'stock' => 2,
					'price' => 222,
				)
			)
		);
		
		$body = wp_json_encode( $test_payload );

		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 45,
		) );

		return $this->handle_response( $response );
	}*/
	// [NEW CODE END]
    /**
	 * تست آپدیت به API (PATCH) با یک محصول ثابت
	 * @return array|WP_Error
	 */
	public function test_patch_request() {
		
		// [MODIFIED CODE START] - ایجاد هدرها به صورت دستی (بدون User-Agent)
		// مطابق با فرضیه شما، ما هدر User-Agent را در این تست خاص حذف می‌کنیم
		// تا دقیقاً مانند افزونه تست (snv-api-tester.php) عمل کنیم.
		$token = isset( $this->options['api_token'] ) ? $this->options['api_token'] : '';
		if ( empty( $token ) ) {
			return new WP_Error( 'auth_failed', __( 'اطلاعات احراز هویت اسنپ شاپ کامل نیست (توکن وجود ندارد).', 'woo-snappshop-connector' ) );
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			// 'User-Agent'    => $user_agent, // <-- این خط عمداً حذف شد
		);
		// [MODIFIED CODE END]


		$vendor_id = isset( $this->options['api_vendor_id'] ) ? $this->options['api_vendor_id'] : '';
		if ( empty( $vendor_id ) ) {
			return new WP_Error( 'no_vendor_id', __( 'شناسه فروشگاه (Vendor ID) اسنپ شاپ تنظیم نشده.', 'woo-snappshop-connector' ) );
		}
		
		$url = "{$this->base_url}/vendors/{$vendor_id}/products";

		// پیلود (Payload) ثابت طبق درخواست شما
		$test_payload = array(
			'products' => array(
				array(
					'id'    => 'a84GyL',
					'stock' => 2,
					'price' => 222000,
				)
			)
		);
		
		$body = wp_json_encode( $test_payload );

		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => $headers, // <-- هدرهای جدید (بدون User-Agent)
			'body'    => $body,
			'timeout' => 45,
		) );

		return $this->handle_response( $response );
	}

	/**
	 * دریافت لیست محصولات از اسنپ شاپ
	 * @param int $page شماره صفحه
	 * @return array|WP_Error
	 */
	public function get_products_page( $page = 1 ) {
		$headers = $this->get_headers();
		if ( ! $headers ) {
			return new WP_Error( 'auth_failed', __( 'اطلاعات احراز هویت اسنپ شاپ کامل نیست.', 'woo-snappshop-connector' ) );
		}

		$vendor_id = isset( $this->options['api_vendor_id'] ) ? $this->options['api_vendor_id'] : '';
		if ( empty( $vendor_id ) ) {
			return new WP_Error( 'no_vendor_id', __( 'شناسه فروشگاه (Vendor ID) اسنپ شاپ تنظیم نشده.', 'woo-snappshop-connector' ) );
		}
		
		$url = "{$this->base_url}/vendors/{$vendor_id}/products?page={$page}";

		$response = wp_remote_get( $url, array(
			'headers' => $headers,
			'timeout' => 30,
		) );

		return $this->handle_response( $response );
	}

	/**
	 * ارسال آپدیت دسته‌ای قیمت و موجودی
	 * @param array $products_payload
	 * @return array|WP_Error
	 */
	public function patch_products( $products_payload ) {
		$headers = $this->get_headers();
		if ( ! $headers ) {
			return new WP_Error( 'auth_failed', __( 'اطلاعات احراز هویت اسنپ شاپ کامل نیست.', 'woo-snappshop-connector' ) );
		}

		$vendor_id = isset( $this->options['api_vendor_id'] ) ? $this->options['api_vendor_id'] : '';
		
		$url = "{$this->base_url}/vendors/{$vendor_id}/products";
		
		$body = wp_json_encode( array(
			'products' => $products_payload,
		) );

		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 45,
		) );

		return $this->handle_response( $response );
	}

	/**
	 * مدیریت پاسخ‌های API
	 * @param array|WP_Error $response
	 * @return array|WP_Error
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( 'WSC WP_Error: ' . $error_message );
			return new WP_Error('http_request_failed', sprintf( __( 'خطای اتصال وردپرس: %s', 'woo-snappshop-connector' ), $error_message ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $http_code >= 200 && $http_code < 300 ) {
			// Temporary debug log to see the exact response in background jobs
			error_log( 'WSC Background Job Response Body: ' . $body );
			return $data;
		}
		
		$message = isset( $data['message'] ) ? $data['message'] : __( 'خطای ناشناخته از API اسنپ شاپ', 'woo-snappshop-connector' );
		
		if ( $http_code === 401 ) {
			$message = __( 'خطای احراز هویت (401). توکن، User-Agent یا Vendor ID شما نامعتبر است.', 'woo-snappshop-connector' );
		}
		if ( $http_code === 404 ) {
			$message = __( 'خطای (404). شناسه فروشگاه (Vendor ID) شما یافت نشد یا آدرس API اشتباه است.', 'woo-snappshop-connector' );
		}
		
		error_log( "WSC Snapp! Shop API Error (HTTP $http_code): " . $message );
		return new WP_Error( 'api_error_' . $http_code, $message, $data );
	}
}

<?php // File: woo-snappshop-connector/includes/admin/class-wsc-mapping-list-page.php
// خروج در صورت دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس WSC_Mapping_List_Page
 *
 * [نسخه 1.0.1] - بازطراحی شده به عنوان "گزارش جامع همگام‌سازی"
 */
class WSC_Mapping_List_Page {

	public static function render() {
		$mapping_repo = new WSC_Mapping_Repository();
		// ما از متد جدید که هر سه حالت را برمی‌گرداند، استفاده می‌کنیم
		$report_data = $mapping_repo->get_all_mappings_with_woo_status();
		
		$mapped_count = count($report_data['mapped']);
		$woo_missing_count = count($report_data['woo_missing']);
		$snapp_sku_missing_count = count($report_data['snapp_sku_missing']);
		
		?>
		<style>
			.wsc-report-table {
				margin-top: 1em;
				border-collapse: collapse;
				width: 100%;
			}
			.wsc-report-table th, .wsc-report-table td {
				padding: 12px 15px;
				text-align: right;
				border-bottom: 1px solid #e0e0e0;
			}
			.wsc-report-table th {
				background-color: #f9f9f9;
				font-weight: bold;
			}
			.wsc-report-table tbody tr:hover {
				background-color: #f1f1f1;
			}
			.wsc-report-table strong {
				font-family: monospace;
				font-size: 1.1em;
			}
			.wsc-report-table .attributes-col {
				color: #555;
				font-style: italic;
			}
			/* Responsive Styles */
			@media screen and (max-width: 782px) {
				.wsc-report-table thead {
					display: none;
				}
				.wsc-report-table, .wsc-report-table tbody, .wsc-report-table tr, .wsc-report-table td {
					display: block;
					width: 100%;
				}
				.wsc-report-table tr {
					margin-bottom: 15px;
					border: 1px solid #ddd;
				}
				.wsc-report-table td {
					text-align: left;
					padding-left: 50%;
					position: relative;
					border-bottom: none;
				}
				.wsc-report-table td::before {
					content: attr(data-label);
					position: absolute;
					left: 10px;
					width: 45%;
					padding-right: 10px;
					font-weight: bold;
					text-align: right;
				}
			}
		</style>
		<div class="wrap">
			<h1><?php _e( 'گزارش همگام‌سازی محصولات', 'woo-snappshop-connector' ); ?></h1>
			<p><?php _e( 'این گزارش وضعیت تمام محصولات دریافت شده از API اسنپ شاپ و ارتباط آن‌ها با محصولات ووکامرس شما را نشان می‌دهد.', 'woo-snappshop-connector' ); ?></p>
			
			<!-- بخش ۱: محصولات همگام شده (موفق) -->
			<h2><?php printf( __( '۱. محصولات همگام شده (%d مورد)', 'woo-snappshop-connector' ), $mapped_count ); ?></h2>
			<p><?php _e( 'این محصولات هم در اسنپ شاپ و هم در ووکامرس (بر اساس SKU) یافت شده‌اند و به درستی همگام‌سازی می‌شوند.', 'woo-snappshop-connector' ); ?></p>
			<table class="wp-list-table widefat fixed striped wsc-report-table">
				<thead>
					<tr>
						<th scope="col" style="width: 25%;"><?php _e( 'SKU (مشترک)', 'woo-snappshop-connector' ); ?></th>
						<th scope="col"><?php _e( 'عنوان محصول (در اسنپ شاپ)', 'woo-snappshop-connector' ); ?></th>
						<th scope="col" style="width: 20%;"><?php _e( 'ویژگی‌ها', 'woo-snappshop-connector' ); ?></th>
						<th scope="col" style="width: 20%;"><?php _e( 'شناسه اسنپ شاپ', 'woo-snappshop-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $report_data['mapped'] ) ) : ?>
						<tr><td colspan="4"><?php _e( 'هیچ محصول همگام شده‌ای یافت نشد.', 'woo-snappshop-connector' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $report_data['mapped'] as $row ) : ?>
							<tr>
								<td data-label="SKU"><strong><?php echo esc_html( $row->wc_sku ); ?></strong></td>
								<td data-label="عنوان"><?php echo esc_html( $row->title ); ?></td>
								<td data-label="ویژگی‌ها" class="attributes-col"><?php echo esc_html( $row->snapp_shop_attributes_rendered ); ?></td>
								<td data-label="شناسه اسنپ"><?php echo esc_html( $row->snapp_shop_id ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- بخش ۲: محصولات موجود در اسنپ، گمشده در ووکامرس (خطا) -->
			<h2 style="margin-top: 30px;"><?php printf( __( '۲. خطاهای همگام‌سازی: SKU در ووکامرس یافت نشد (%d مورد)', 'woo-snappshop-connector' ), $woo_missing_count ); ?></h2>
			<p style="color: red;"><?php _e( 'هشدار: این محصولات در اسنپ شاپ SKU دارند، اما SKU آن‌ها در ووکامرس یافت نشد. این موارد همگام‌سازی نخواهند شد.', 'woo-snappshop-connector' ); ?></p>
			<table class="wp-list-table widefat fixed striped wsc-report-table">
				<thead>
					<tr>
						<th scope="col" style="width: 25%;"><?php _e( 'SKU (تعریف شده در اسنپ)', 'woo-snappshop-connector' ); ?></th>
						<th scope="col"><?php _e( 'عنوان محصول (در اسنپ شاپ)', 'woo-snappshop-connector' ); ?></th>
						<th scope="col" style="width: 20%;"><?php _e( 'ویژگی‌ها', 'woo-snappshop-connector' ); ?></th>
						<th scope="col" style="width: 20%;"><?php _e( 'شناسه اسنپ شاپ', 'woo-snappshop-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $report_data['woo_missing'] ) ) : ?>
						<tr><td colspan="4"><?php _e( 'موردی یافت نشد.', 'woo-snappshop-connector' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $report_data['woo_missing'] as $row ) : ?>
							<tr style="background-color: #fef2f2;">
								<td data-label="SKU"><strong><?php echo esc_html( $row->wc_sku ); ?></strong></td>
								<td data-label="عنوان"><?php echo esc_html( $row->title ); ?></td>
								<td data-label="ویژگی‌ها" class="attributes-col"><?php echo esc_html( $row->snapp_shop_attributes_rendered ); ?></td>
								<td data-label="شناسه اسنپ"><?php echo esc_html( $row->snapp_shop_id ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- بخش ۳: محصولات فاقد SKU در اسنپ (خطا) -->
			<h2 style="margin-top: 30px;"><?php printf( __( '۳. خطاهای تعریف محصول: فاقد SKU در اسنپ شاپ (%d مورد)', 'woo-snappshop-connector' ), $snapp_sku_missing_count ); ?></h2>
			<p style="color: red;"><?php _e( 'هشدار: این محصولات در اسنپ شاپ تعریف شده‌اند اما هیچ SKU به آن‌ها اختصاص داده نشده است. این موارد هرگز همگام‌سازی نخواهند شد.', 'woo-snappshop-connector' ); ?></p>
			<table class="wp-list-table widefat fixed striped wsc-report-table">
				<thead>
					<tr>
						<th scope="col" style="width: 25%;"><?php _e( 'SKU (در اسنپ شاپ)', 'woo-snappshop-connector' ); ?></th>
						<th scope="col"><?php _e( 'عنوان محصول (در اسنپ شاپ)', 'woo-snappshop-connector' ); ?></th>
						<th scope="col" style="width: 20%;"><?php _e( 'ویژگی‌ها', 'woo-snappshop-connector' ); ?></th>
						<th scope="col" style="width: 20%;"><?php _e( 'شناسه اسنپ شاپ', 'woo-snappshop-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $report_data['snapp_sku_missing'] ) ) : ?>
						<tr><td colspan="4"><?php _e( 'موردی یافت نشد.', 'woo-snappshop-connector' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $report_data['snapp_sku_missing'] as $row ) : ?>
							<tr style="background-color: #fef2f2;">
								<td data-label="SKU"><strong style="color: #999;"><?php _e( '[فاقد SKU]', 'woo-snappshop-connector' ); ?></strong></td>
								<td data-label="عنوان"><?php echo esc_html( $row->title ); ?></td>
								<td data-label="ویژگی‌ها" class="attributes-col"><?php echo esc_html( $row->snapp_shop_attributes_rendered ); ?></td>
								<td data-label="شناسه اسنپ"><?php echo esc_html( $row->snapp_shop_id ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

		</div>
		<?php
	}
}

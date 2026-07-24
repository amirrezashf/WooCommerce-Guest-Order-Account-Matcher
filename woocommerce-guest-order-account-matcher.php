<?php
/**
 * Plugin Name:       WooCommerce Guest Order Account Matcher
 * Plugin URI:        https://github.com/amirrezashf/WooCommerce-Guest-Order-Account-Matcher
 * Description:       Matches eligible guest WooCommerce orders to existing customer accounts using normalized Iranian mobile numbers and configurable name similarity checks.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Amirreza Shayesteh Far
 * Author URI:        https://amirrezaa.ir/
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woocommerce-guest-order-account-matcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_Guest_Order_Account_Matcher {
	private const CACHE_PREFIX = 'wc_goam_users_';
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	public function declare_hpos_compatibility(): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	public function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_guest_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_customer_to_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_order_note' ), 10, 3 );
	}

	private function english_digits( $value ): string {
		return str_replace(
			array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩' ),
			array( '0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9' ),
			(string) $value
		);
	}

	private function normalize_phone( $phone ): string {
		$phone = preg_replace( '/[^0-9]/', '', $this->english_digits( $phone ) );
		if ( ! is_string( $phone ) || '' === $phone ) {
			return '';
		}
		if ( preg_match( '/^00989\d{9}$/', $phone ) ) {
			return '0' . substr( $phone, 4 );
		}
		if ( preg_match( '/^989\d{9}$/', $phone ) ) {
			return '0' . substr( $phone, 2 );
		}
		if ( preg_match( '/^9\d{9}$/', $phone ) ) {
			return '0' . $phone;
		}
		return preg_match( '/^09\d{9}$/', $phone ) ? $phone : '';
	}

	private function normalize_text( $text ): string {
		$text = wp_strip_all_tags( (string) $text );
		$text = str_replace(
			array( 'ي', 'ك', 'ۀ', 'ة', 'ؤ', 'أ', 'إ', '‌' ),
			array( 'ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', ' ' ),
			$text
		);
		$text = $this->english_digits( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	private function match_percent( $checkout_value, $account_value, bool $compact = false ): float {
		$checkout = $this->normalize_text( $checkout_value );
		$account  = $this->normalize_text( $account_value );
		if ( $compact ) {
			$checkout = str_replace( ' ', '', $checkout );
			$account  = str_replace( ' ', '', $account );
			if ( '' === $checkout || '' === $account ) {
				return 0.0;
			}
			if ( $checkout === $account ) {
				return 100.0;
			}
			similar_text( $checkout, $account, $percent );
			return (float) $percent;
		}

		$checkout_tokens = array_values( array_unique( array_filter( explode( ' ', $checkout ) ) ) );
		$account_tokens  = array_values( array_unique( array_filter( explode( ' ', $account ) ) ) );
		if ( empty( $checkout_tokens ) || empty( $account_tokens ) ) {
			return 0.0;
		}
		$matched = count( array_intersect( $checkout_tokens, $account_tokens ) );
		if ( $matched <= 0 ) {
			return 0.0;
		}
		return ( ( $matched / count( $checkout_tokens ) ) * 100 + ( $matched / count( $account_tokens ) ) * 100 ) / 2;
	}

	private function get_user_name_parts( int $user_id ): array {
		$first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
		if ( '' === $first ) {
			$first = trim( (string) get_user_meta( $user_id, 'billing_first_name', true ) );
		}
		if ( '' === $last ) {
			$last = trim( (string) get_user_meta( $user_id, 'billing_last_name', true ) );
		}
		return array( 'first_name' => $first, 'last_name' => $last );
	}

	private function get_user_display_name( int $user_id ): string {
		$parts = $this->get_user_name_parts( $user_id );
		$name  = trim( $parts['first_name'] . ' ' . $parts['last_name'] );
		if ( '' !== $name ) {
			return $name;
		}
		$user = get_userdata( $user_id );
		return $user instanceof WP_User && $user->display_name ? $user->display_name : 'کاربر #' . $user_id;
	}

	private function find_users_by_phone( string $phone ): array {
		$phone = $this->normalize_phone( $phone );
		if ( '' === $phone ) {
			return array();
		}
		$key    = self::CACHE_PREFIX . md5( $phone );
		$cached = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			return array_map( 'intval', $cached );
		}

		$ids = array_map( 'intval', (array) ( new WP_User_Query(
			array(
				'fields'     => 'ID',
				'number'     => 50,
				'meta_key'   => 'billing_phone',
				'meta_value' => $phone,
			)
		) )->get_results() );

		if ( empty( $ids ) ) {
			$last_ten = substr( $phone, -10 );
			$possible = (array) ( new WP_User_Query(
				array(
					'fields'     => 'ID',
					'number'     => 200,
					'meta_query' => array(
						array(
							'key'     => 'billing_phone',
							'value'   => $last_ten,
							'compare' => 'LIKE',
						),
					),
				)
			) )->get_results();

			foreach ( $possible as $user_id ) {
				if ( $this->normalize_phone( get_user_meta( $user_id, 'billing_phone', true ) ) === $phone ) {
					$ids[] = (int) $user_id;
				}
			}
		}

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		set_transient( $key, $ids, 10 * MINUTE_IN_SECONDS );
		return $ids;
	}

	private function validate_identity( $phone, $first_name, $last_name ): array {
		$normalized_phone = $this->normalize_phone( $phone );
		$base = array(
			'allowed' => false, 'user_id' => 0, 'user_name' => '', 'reason' => '',
			'normalized_phone' => $normalized_phone, 'first_name_percent' => 0,
			'last_name_percent' => 0, 'combined_percent' => 0,
			'checkout_first_name' => (string) $first_name, 'checkout_last_name' => (string) $last_name,
			'account_first_name' => '', 'account_last_name' => '', 'matched_user_count' => 0,
		);

		if ( '' === $normalized_phone ) {
			$base['reason'] = 'invalid_phone';
			return $base;
		}

		$user_ids = $this->find_users_by_phone( $normalized_phone );
		if ( empty( $user_ids ) ) {
			$base['reason'] = 'no_account';
			return $base;
		}

		$candidates = array();
		foreach ( $user_ids as $user_id ) {
			$parts = $this->get_user_name_parts( $user_id );
			$first = $this->match_percent( $first_name, $parts['first_name'], true );
			$last  = $this->match_percent( $last_name, $parts['last_name'] );
			$candidates[] = array(
				'user_id' => $user_id,
				'user_name' => $this->get_user_display_name( $user_id ),
				'first_name_percent' => $first,
				'last_name_percent' => $last,
				'combined_percent' => ( $first + $last ) / 2,
				'account_first_name' => $parts['first_name'],
				'account_last_name' => $parts['last_name'],
			);
		}

		usort( $candidates, static function ( array $a, array $b ): int {
			return $b['combined_percent'] <=> $a['combined_percent'];
		} );

		$best = $candidates[0];
		$result = array_merge( $base, $best, array( 'matched_user_count' => count( $user_ids ) ) );
		$threshold = (float) apply_filters( 'wc_goam_name_match_threshold', 80.0 );

		if ( $best['first_name_percent'] < $threshold || $best['last_name_percent'] < $threshold ) {
			$result['reason'] = 'name_mismatch';
			return $result;
		}

		if ( count( $candidates ) > 1 ) {
			$second = $candidates[1];
			$margin = (float) apply_filters( 'wc_goam_duplicate_candidate_margin', 5.0 );
			if (
				abs( $best['combined_percent'] - $second['combined_percent'] ) < $margin &&
				abs( $best['first_name_percent'] - $second['first_name_percent'] ) < $margin &&
				abs( $best['last_name_percent'] - $second['last_name_percent'] ) < $margin
			) {
				$result['user_id'] = 0;
				$result['user_name'] = '';
				$result['reason'] = 'ambiguous_duplicate_phone';
				return $result;
			}
		}

		$result['allowed'] = true;
		$result['reason']  = count( $user_ids ) > 1 ? 'matched_from_duplicates' : 'matched';
		return $result;
	}

	public function validate_guest_checkout( $data, $errors ): void {
		if ( is_user_logged_in() || ! is_array( $data ) || ! $errors instanceof WP_Error ) {
			return;
		}
		$result = $this->validate_identity(
			$data['billing_phone'] ?? '',
			$data['billing_first_name'] ?? '',
			$data['billing_last_name'] ?? ''
		);

		$messages = array(
			'invalid_phone' => 'شماره موبایل واردشده صحیح نیست.',
			'no_account' => 'برای ادامه خرید، این شماره موبایل باید از قبل در سایت ثبت شده باشد. لطفاً ابتدا ثبت‌نام کنید یا وارد حساب کاربری خود شوید.',
			'ambiguous_duplicate_phone' => 'برای این شماره موبایل چند حساب مشابه پیدا شد و تشخیص قطعی ممکن نیست. لطفاً وارد حساب کاربری خود شوید.',
			'name_mismatch' => 'اطلاعات نام و نام خانوادگی با حساب موجود مطابقت کافی ندارد. لطفاً وارد حساب کاربری خود شوید.',
		);
		if ( isset( $messages[ $result['reason'] ] ) ) {
			$errors->add( 'wc_goam_' . $result['reason'], $messages[ $result['reason'] ] );
		}
	}

	public function attach_customer_to_order( $order, $data ): void {
		if ( is_user_logged_in() || ! $order instanceof WC_Order || ! is_array( $data ) ) {
			return;
		}
		$result = $this->validate_identity(
			$data['billing_phone'] ?? '',
			$data['billing_first_name'] ?? '',
			$data['billing_last_name'] ?? ''
		);
		$this->save_audit_log( $order, $result );
		if ( empty( $result['allowed'] ) || empty( $result['user_id'] ) ) {
			return;
		}
		$order->set_customer_id( (int) $result['user_id'] );
		$order->set_billing_phone( $result['normalized_phone'] );
	}

	private function save_audit_log( WC_Order $order, array $result ): void {
		$order->update_meta_data( '_wc_goam_status', ! empty( $result['allowed'] ) ? 'matched' : 'rejected' );
		foreach ( array(
			'reason','normalized_phone','user_name','checkout_first_name','checkout_last_name','account_first_name','account_last_name'
		) as $key ) {
			$order->update_meta_data( '_wc_goam_' . $key, $result[ $key ] ?? '' );
		}
		$order->update_meta_data( '_wc_goam_user_id', (int) ( $result['user_id'] ?? 0 ) );
		$order->update_meta_data( '_wc_goam_first_name_percent', round( (float) ( $result['first_name_percent'] ?? 0 ), 2 ) );
		$order->update_meta_data( '_wc_goam_last_name_percent', round( (float) ( $result['last_name_percent'] ?? 0 ), 2 ) );
		$order->update_meta_data( '_wc_goam_combined_percent', round( (float) ( $result['combined_percent'] ?? 0 ), 2 ) );
		$order->update_meta_data( '_wc_goam_matched_user_count', (int) ( $result['matched_user_count'] ?? 0 ) );
		$order->update_meta_data( '_wc_goam_checked_at', current_time( 'mysql' ) );
	}

	public function add_order_note( $order_id, $posted_data, $order ): void {
		unset( $posted_data );
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$status = (string) $order->get_meta( '_wc_goam_status', true );
		if ( '' === $status ) {
			return;
		}
		$reason = (string) $order->get_meta( '_wc_goam_reason', true );
		$user_name = (string) $order->get_meta( '_wc_goam_user_name', true );
		$phone = (string) $order->get_meta( '_wc_goam_normalized_phone', true );
		$count = (int) $order->get_meta( '_wc_goam_matched_user_count', true );
		$first = (float) $order->get_meta( '_wc_goam_first_name_percent', true );
		$last = (float) $order->get_meta( '_wc_goam_last_name_percent', true );
		$combined = (float) $order->get_meta( '_wc_goam_combined_percent', true );

		if ( 'matched' === $status ) {
			$order->add_order_note( sprintf(
				'این سفارش مهمان به حساب «%1$s» متصل شد. شماره نرمال‌شده: %2$s | تعداد حساب‌های پیدا شده: %3$d | تطابق نام: %4$s%% | تطابق نام خانوادگی: %5$s%% | میانگین: %6$s%%',
				$user_name,
				$phone,
				$count,
				number_format( $first, 2, '.', '' ),
				number_format( $last, 2, '.', '' ),
				number_format( $combined, 2, '.', '' )
			) );
			return;
		}

		$labels = array(
			'invalid_phone' => 'شماره موبایل نامعتبر',
			'no_account' => 'حساب متناظر پیدا نشد',
			'ambiguous_duplicate_phone' => 'چند حساب مشابه پیدا شد',
			'name_mismatch' => 'نام یا نام خانوادگی تطابق کافی نداشت',
		);
		$order->add_order_note( sprintf(
			'نتیجه بررسی سفارش مهمان: رد شد. دلیل: %1$s | شماره نرمال‌شده: %2$s | تعداد حساب‌های پیدا شده: %3$d | تطابق نام: %4$s%% | تطابق نام خانوادگی: %5$s%% | میانگین: %6$s%%',
			$labels[ $reason ] ?? 'نامشخص',
			$phone,
			$count,
			number_format( $first, 2, '.', '' ),
			number_format( $last, 2, '.', '' ),
			number_format( $combined, 2, '.', '' )
		) );
	}
}

WC_Guest_Order_Account_Matcher::instance();

<?php
/**
 * Plugin Name: Zwembadforum Advertentie Statistieken
 * Description: Meet impressies en kliks op forumadvertenties en toont resultaten in WordPress en op het hoofddashboard.
 * Version: 1.3.0
 * Author: Zwembadforum.eu
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: zwembadforum-advertentie-statistieken
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ZF_FORUM_AD_STATS_VERSION' ) ) {
	define( 'ZF_FORUM_AD_STATS_VERSION', '1.3.0' );
}

if ( ! defined( 'ZF_FORUM_AD_STATS_OPTION' ) ) {
	define( 'ZF_FORUM_AD_STATS_OPTION', 'zf_forum_ad_stats_version' );
}

if ( ! defined( 'ZF_FORUM_AD_STATS_DATA_OPTION' ) ) {
	define( 'ZF_FORUM_AD_STATS_DATA_OPTION', 'zf_forum_ad_stats_data_version' );
}

if ( ! defined( 'ZF_FORUM_AD_STATS_RETENTION_DAYS' ) ) {
	define( 'ZF_FORUM_AD_STATS_RETENTION_DAYS', 365 );
}

if ( ! defined( 'ZF_FORUM_AD_STATS_MAX_ROWS' ) ) {
	define( 'ZF_FORUM_AD_STATS_MAX_ROWS', 100000 );
}

if ( ! function_exists( 'zf_forum_ad_stats_table_name' ) ) {
	function zf_forum_ad_stats_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'zf_forum_ad_stats';
	}
}

if ( ! function_exists( 'zf_forum_ad_stats_maybe_create_table' ) ) {
	function zf_forum_ad_stats_maybe_create_table() {
		$current_version = get_option( ZF_FORUM_AD_STATS_OPTION );

		if ( ZF_FORUM_AD_STATS_VERSION === $current_version ) {
			return;
		}

		global $wpdb;

		$table_name      = zf_forum_ad_stats_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_date date NOT NULL,
			event_type varchar(20) NOT NULL,
			device_type varchar(20) NOT NULL DEFAULT 'unknown',
			topic_id bigint(20) unsigned NOT NULL DEFAULT 0,
			topic_title varchar(191) NOT NULL DEFAULT '',
			forum_id bigint(20) unsigned NOT NULL DEFAULT 0,
			forum_title varchar(191) NOT NULL DEFAULT '',
			destination_url text NOT NULL,
			destination_hash char(32) NOT NULL,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY daily_event_unique (event_date,event_type,device_type,topic_id,forum_id,destination_hash),
			KEY event_date (event_date),
			KEY event_type (event_type),
			KEY device_type (device_type),
			KEY topic_id (topic_id),
			KEY forum_id (forum_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( ZF_FORUM_AD_STATS_OPTION, ZF_FORUM_AD_STATS_VERSION, false );
	}
}
add_action( 'init', 'zf_forum_ad_stats_maybe_create_table', 5 );

if ( ! function_exists( 'zf_forum_ad_stats_maybe_migrate_data' ) ) {
	function zf_forum_ad_stats_maybe_migrate_data() {
		global $wpdb;

		$data_version = (int) get_option( ZF_FORUM_AD_STATS_DATA_OPTION, 1 );

		if ( $data_version >= 3 ) {
			return;
		}

		$table_name = zf_forum_ad_stats_table_name();

		$migration_result = $wpdb->query(
			"INSERT INTO {$table_name}
				(event_date, event_type, device_type, topic_id, topic_title, forum_id, forum_title, destination_url, destination_hash, hits, created_at, updated_at)
			SELECT
				event_date,
				'view_promotion',
				'unknown',
				0,
				'',
				0,
				'',
				MAX(destination_url),
				destination_hash,
				SUM(hits),
				MIN(created_at),
				MAX(updated_at)
			FROM {$table_name}
			WHERE event_type = 'view_promotion'
				AND (topic_id <> 0 OR forum_id <> 0)
			GROUP BY event_date, destination_hash
			ON DUPLICATE KEY UPDATE
				hits = hits + VALUES(hits),
				updated_at = GREATEST(updated_at, VALUES(updated_at)),
				destination_url = VALUES(destination_url)"
		);

		if ( false === $migration_result ) {
			return;
		}

		$wpdb->query(
			"DELETE FROM {$table_name}
			WHERE event_type = 'view_promotion'
				AND (topic_id <> 0 OR forum_id <> 0)"
		);

		$wpdb->query(
			"UPDATE {$table_name}
			SET device_type = 'unknown'
			WHERE device_type = ''"
		);

		update_option( ZF_FORUM_AD_STATS_DATA_OPTION, 3, false );
	}
}
add_action( 'init', 'zf_forum_ad_stats_maybe_migrate_data', 6 );

if ( ! function_exists( 'zf_forum_ad_stats_cleanup' ) ) {
	function zf_forum_ad_stats_cleanup() {
		global $wpdb;

		$table_name = zf_forum_ad_stats_table_name();
		$retention  = max( 30, (int) ZF_FORUM_AD_STATS_RETENTION_DAYS );
		$max_rows   = max( 1000, (int) ZF_FORUM_AD_STATS_MAX_ROWS );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name}
				WHERE event_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
				$retention
			)
		);

		$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		if ( $row_count > $max_rows ) {
			$delete_count = min( $row_count - $max_rows, 10000 );

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name}
					ORDER BY event_date ASC, id ASC
					LIMIT %d",
					$delete_count
				)
			);
		}
	}
}
add_action( 'zf_forum_ad_stats_cleanup', 'zf_forum_ad_stats_cleanup' );

if ( ! function_exists( 'zf_forum_ad_stats_maybe_schedule_cleanup' ) ) {
	function zf_forum_ad_stats_maybe_schedule_cleanup() {
		if ( ! wp_next_scheduled( 'zf_forum_ad_stats_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'zf_forum_ad_stats_cleanup' );
		}
	}
}
add_action( 'init', 'zf_forum_ad_stats_maybe_schedule_cleanup', 7 );

if ( ! function_exists( 'zf_forum_ad_stats_activate' ) ) {
	function zf_forum_ad_stats_activate() {
		delete_option( ZF_FORUM_AD_STATS_OPTION );
		zf_forum_ad_stats_maybe_create_table();
		zf_forum_ad_stats_maybe_migrate_data();
		zf_forum_ad_stats_cleanup();
		zf_forum_ad_stats_maybe_schedule_cleanup();
	}
}
register_activation_hook( __FILE__, 'zf_forum_ad_stats_activate' );

if ( ! function_exists( 'zf_forum_ad_stats_deactivate' ) ) {
	function zf_forum_ad_stats_deactivate() {
		wp_clear_scheduled_hook( 'zf_forum_ad_stats_cleanup' );
	}
}
register_deactivation_hook( __FILE__, 'zf_forum_ad_stats_deactivate' );

if ( ! function_exists( 'zf_forum_ad_stats_track_event' ) ) {
	function zf_forum_ad_stats_track_event( $payload ) {
		global $wpdb;

		$event_type = '';
		if ( isset( $payload['event_type'] ) ) {
			$event_type = sanitize_key( $payload['event_type'] );
		}

		if ( ! in_array( $event_type, array( 'view_promotion', 'select_promotion', 'select_signature' ), true ) ) {
			return false;
		}

		$destination_url = '';
		if ( isset( $payload['destination_url'] ) ) {
			$destination_url = esc_url_raw( $payload['destination_url'] );
		}

		if ( empty( $destination_url ) ) {
			return false;
		}

		$topic_id    = isset( $payload['topic_id'] ) ? absint( $payload['topic_id'] ) : 0;
		$forum_id    = isset( $payload['forum_id'] ) ? absint( $payload['forum_id'] ) : 0;
		$topic_title = isset( $payload['topic_title'] ) ? sanitize_text_field( $payload['topic_title'] ) : '';
		$forum_title = isset( $payload['forum_title'] ) ? sanitize_text_field( $payload['forum_title'] ) : '';
		$device_type = isset( $payload['device_type'] ) ? sanitize_key( $payload['device_type'] ) : 'unknown';

		if ( ! in_array( $device_type, array( 'desktop', 'mobile', 'unknown' ), true ) ) {
			$device_type = 'unknown';
		}

		if ( 'view_promotion' === $event_type ) {
			$topic_id    = 0;
			$forum_id    = 0;
			$topic_title = '';
			$forum_title = '';
		}

		$event_date  = current_time( 'Y-m-d' );
		$now         = current_time( 'mysql' );
		$table_name  = zf_forum_ad_stats_table_name();
		$url_hash    = md5( $destination_url );

		$sql = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(event_date, event_type, device_type, topic_id, topic_title, forum_id, forum_title, destination_url, destination_hash, hits, created_at, updated_at)
			VALUES
				(%s, %s, %s, %d, %s, %d, %s, %s, %s, 1, %s, %s)
			ON DUPLICATE KEY UPDATE
				hits = hits + 1,
				updated_at = VALUES(updated_at),
				topic_title = VALUES(topic_title),
				forum_title = VALUES(forum_title),
				device_type = VALUES(device_type),
				destination_url = VALUES(destination_url)",
			$event_date,
			$event_type,
			$device_type,
			$topic_id,
			$topic_title,
			$forum_id,
			$forum_title,
			$destination_url,
			$url_hash,
			$now,
			$now
		);

		return false !== $wpdb->query( $sql );
	}
}

if ( ! function_exists( 'zf_forum_ad_stats_handle_ajax' ) ) {
	function zf_forum_ad_stats_handle_ajax() {
		if ( ! check_ajax_referer( 'zf_forum_ad_stats', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'invalid_nonce' ), 403 );
		}

		$payload = array(
			'event_type'      => isset( $_POST['event_type'] ) ? wp_unslash( $_POST['event_type'] ) : '',
			'device_type'     => isset( $_POST['device_type'] ) ? wp_unslash( $_POST['device_type'] ) : 'unknown',
			'destination_url' => isset( $_POST['destination_url'] ) ? wp_unslash( $_POST['destination_url'] ) : '',
			'topic_id'        => isset( $_POST['topic_id'] ) ? wp_unslash( $_POST['topic_id'] ) : 0,
			'topic_title'     => isset( $_POST['topic_title'] ) ? wp_unslash( $_POST['topic_title'] ) : '',
			'forum_id'        => isset( $_POST['forum_id'] ) ? wp_unslash( $_POST['forum_id'] ) : 0,
			'forum_title'     => isset( $_POST['forum_title'] ) ? wp_unslash( $_POST['forum_title'] ) : '',
		);

		if ( ! zf_forum_ad_stats_track_event( $payload ) ) {
			wp_send_json_error( array( 'message' => 'save_failed' ), 400 );
		}

		wp_send_json_success( array( 'saved' => true ) );
	}
}
add_action( 'wp_ajax_zf_forum_ad_stats_track', 'zf_forum_ad_stats_handle_ajax' );
add_action( 'wp_ajax_nopriv_zf_forum_ad_stats_track', 'zf_forum_ad_stats_handle_ajax' );

if ( ! function_exists( 'zf_forum_ad_stats_render_tracker' ) ) {
	function zf_forum_ad_stats_render_tracker() {
		$topic_id    = 0;
		$forum_id    = 0;
		$topic_title = '';
		$forum_title = '';
		$script      = '';

		if ( ! function_exists( 'bbp_is_single_topic' ) || ! bbp_is_single_topic() ) {
			return;
		}

		if ( function_exists( 'bbp_get_topic_id' ) ) {
			$topic_id = (int) bbp_get_topic_id();
		}

		if ( function_exists( 'bbp_get_topic_forum_id' ) ) {
			$forum_id = (int) bbp_get_topic_forum_id( $topic_id );
		}

		if ( $topic_id ) {
			$topic_title = wp_strip_all_tags( get_the_title( $topic_id ) );
		}

		if ( $forum_id ) {
			$forum_title = wp_strip_all_tags( get_the_title( $forum_id ) );
		}

		$script .= '(function () {';
		$script .= 'var adSelectors = [".banner-desktop a", ".banner-mobile a", ".zf-managed-topic-ad a"];';
		$script .= 'var signatureSelectors = [".forum-signature a", ".bbp-reply-signature a", ".bbp-topic-signature a", ".bbp-signature a", ".user-signature a", ".signature a"];';
		$script .= 'var adLinks = [];';
		$script .= 'var signatureLinks = [];';
		$script .= 'adSelectors.forEach(function (selector) {';
		$script .= 'document.querySelectorAll(selector).forEach(function (node) {';
		$script .= 'if (adLinks.indexOf(node) === -1) { adLinks.push(node); }';
		$script .= '});';
		$script .= '});';
		$script .= 'signatureSelectors.forEach(function (selector) {';
		$script .= 'document.querySelectorAll(selector).forEach(function (node) {';
		$script .= 'if (adLinks.indexOf(node) === -1 && signatureLinks.indexOf(node) === -1) { signatureLinks.push(node); }';
		$script .= '});';
		$script .= '});';
		$script .= 'if (!adLinks.length && !signatureLinks.length) { return; }';
		$script .= 'var ajaxUrl = ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';';
		$script .= 'var basePayload = {';
		$script .= 'action: "zf_forum_ad_stats_track",';
		$script .= 'nonce: ' . wp_json_encode( wp_create_nonce( 'zf_forum_ad_stats' ) ) . ',';
		$script .= 'topic_id: ' . wp_json_encode( (string) $topic_id ) . ',';
		$script .= 'topic_title: ' . wp_json_encode( $topic_title ) . ',';
		$script .= 'forum_id: ' . wp_json_encode( (string) $forum_id ) . ',';
		$script .= 'forum_title: ' . wp_json_encode( $forum_title );
		$script .= '};';
		$script .= 'function resolveDeviceType(link) {';
		$script .= 'if (link.closest(".banner-mobile")) { return "mobile"; }';
		$script .= 'if (link.closest(".banner-desktop")) { return "desktop"; }';
		$script .= 'if (window.matchMedia && window.matchMedia("(max-width: 782px)").matches) { return "mobile"; }';
		$script .= 'return "desktop";';
		$script .= '}';
		$script .= 'function sendEvent(eventType, destinationUrl, deviceType) {';
		$script .= 'var body = new URLSearchParams(Object.assign({}, basePayload, { event_type: eventType, device_type: deviceType, destination_url: destinationUrl }));';
		$script .= 'fetch(ajaxUrl, {';
		$script .= 'method: "POST",';
		$script .= 'credentials: "same-origin",';
		$script .= 'headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },';
		$script .= 'body: body.toString(),';
		$script .= 'keepalive: eventType !== "view_promotion"';
		$script .= '}).catch(function () { return null; });';
		$script .= '}';
		$script .= 'function canTrackImpression(link) {';
		$script .= 'var now = Date.now();';
		$script .= 'var windowMs = 30 * 60 * 1000;';
		$script .= 'var storageKey = "zf_forum_ad_view_" + basePayload.topic_id + "_" + encodeURIComponent(link.href);';
		$script .= 'try {';
		$script .= 'var previous = parseInt(window.localStorage.getItem(storageKey) || "0", 10);';
		$script .= 'if (previous && now - previous < windowMs) { return false; }';
		$script .= 'window.localStorage.setItem(storageKey, String(now));';
		$script .= '} catch (error) {';
		$script .= 'return true;';
		$script .= '}';
		$script .= 'return true;';
		$script .= '}';
		$script .= 'function markImpression(link) {';
		$script .= 'if (link.dataset.zfForumAdSeen === "1") { return; }';
		$script .= 'link.dataset.zfForumAdSeen = "1";';
		$script .= 'if (!canTrackImpression(link)) { return; }';
		$script .= 'sendEvent("view_promotion", link.href, resolveDeviceType(link));';
		$script .= '}';
		$script .= 'if ("IntersectionObserver" in window) {';
		$script .= 'var observer = new IntersectionObserver(function (entries) {';
		$script .= 'entries.forEach(function (entry) {';
		$script .= 'if (entry.isIntersecting) { markImpression(entry.target); observer.unobserve(entry.target); }';
		$script .= '});';
		$script .= '}, { threshold: 0.5 });';
		$script .= 'adLinks.forEach(function (link) { observer.observe(link); });';
		$script .= '} else {';
		$script .= 'adLinks.forEach(markImpression);';
		$script .= '}';
		$script .= 'adLinks.forEach(function (link) {';
		$script .= 'link.addEventListener("click", function () { sendEvent("select_promotion", link.href, resolveDeviceType(link)); }, { passive: true });';
		$script .= '});';
		$script .= 'signatureLinks.forEach(function (link) {';
		$script .= 'link.addEventListener("click", function () { sendEvent("select_signature", link.href, resolveDeviceType(link)); }, { passive: true });';
		$script .= '});';
		$script .= '})();';

		echo '<script>' . $script . '</script>';
	}
}
add_action( 'wp_footer', 'zf_forum_ad_stats_render_tracker', 99 );

if ( ! function_exists( 'zf_forum_ad_stats_add_admin_page' ) ) {
	function zf_forum_ad_stats_add_admin_page() {
		add_menu_page(
			'Forum advertentie stats',
			'Advertentie stats',
			'manage_options',
			'zf-forum-ad-stats',
			'zf_forum_ad_stats_render_admin_page',
			'dashicons-chart-bar',
			58
		);
	}
}
add_action( 'admin_menu', 'zf_forum_ad_stats_add_admin_page' );

if ( ! function_exists( 'zf_forum_ad_stats_get_summary_rows' ) ) {
	function zf_forum_ad_stats_get_summary_rows( $days ) {
		global $wpdb;

		$table_name = zf_forum_ad_stats_table_name();
		$days       = max( 1, min( 365, (int) $days ) );

		$sql = $wpdb->prepare(
			"SELECT
				destination_url,
				event_type,
				device_type,
				topic_id,
				forum_id,
				SUM(hits) AS clicks,
				MAX(updated_at) AS last_seen,
				MAX(topic_title) AS topic_title,
				MAX(forum_title) AS forum_title
			FROM {$table_name}
			WHERE event_type IN ('select_promotion', 'select_signature')
				AND event_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			GROUP BY destination_hash, destination_url, event_type, device_type, topic_id, forum_id
			ORDER BY clicks DESC, last_seen DESC",
			$days
		);

		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}
}

if ( ! function_exists( 'zf_forum_ad_stats_get_daily_rows' ) ) {
	function zf_forum_ad_stats_get_daily_rows( $days ) {
		global $wpdb;

		$table_name = zf_forum_ad_stats_table_name();
		$days       = max( 1, min( 365, (int) $days ) );

		$sql = $wpdb->prepare(
			"SELECT
				event_date,
				SUM(CASE WHEN event_type = 'view_promotion' THEN hits ELSE 0 END) AS impressions,
				SUM(CASE WHEN event_type = 'view_promotion' AND device_type = 'desktop' THEN hits ELSE 0 END) AS desktop_impressions,
				SUM(CASE WHEN event_type = 'view_promotion' AND device_type = 'mobile' THEN hits ELSE 0 END) AS mobile_impressions,
				SUM(CASE WHEN event_type = 'select_promotion' THEN hits ELSE 0 END) AS clicks,
				SUM(CASE WHEN event_type = 'select_signature' THEN hits ELSE 0 END) AS signature_clicks,
				SUM(CASE WHEN event_type = 'select_promotion' AND device_type = 'desktop' THEN hits ELSE 0 END) AS desktop_clicks,
				SUM(CASE WHEN event_type = 'select_promotion' AND device_type = 'mobile' THEN hits ELSE 0 END) AS mobile_clicks
			FROM {$table_name}
			WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			GROUP BY event_date
			ORDER BY event_date DESC",
			$days
		);

		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}
}

if ( ! function_exists( 'zf_forum_ad_stats_render_admin_page' ) ) {
	function zf_forum_ad_stats_render_admin_page() {
		$days        = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$days        = max( 7, min( 365, $days ) );
		$summary     = zf_forum_ad_stats_get_summary_rows( $days );
		$daily       = zf_forum_ad_stats_get_daily_rows( $days );
		$total_views = 0;
		$total_click = 0;
		$total_signature_clicks = 0;
		$total_desktop_impressions = 0;
		$total_mobile_impressions = 0;
		$total_desktop_clicks = 0;
		$total_mobile_clicks = 0;
		$ctr         = 0;
		$desktop_ctr = 0;
		$mobile_ctr  = 0;
		$i           = 0;
		$row         = array();

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		for ( $i = 0; $i < count( $daily ); $i++ ) {
			$total_views += (int) $daily[ $i ]['impressions'];
			$total_click += (int) $daily[ $i ]['clicks'];
			$total_signature_clicks += (int) $daily[ $i ]['signature_clicks'];
			$total_desktop_impressions += (int) $daily[ $i ]['desktop_impressions'];
			$total_mobile_impressions += (int) $daily[ $i ]['mobile_impressions'];
			$total_desktop_clicks += (int) $daily[ $i ]['desktop_clicks'];
			$total_mobile_clicks += (int) $daily[ $i ]['mobile_clicks'];
		}

		if ( $total_views > 0 ) {
			$ctr = round( ( $total_click / $total_views ) * 100, 2 );
		}

		if ( $total_desktop_impressions > 0 ) {
			$desktop_ctr = round( ( $total_desktop_clicks / $total_desktop_impressions ) * 100, 2 );
		}

		if ( $total_mobile_impressions > 0 ) {
			$mobile_ctr = round( ( $total_mobile_clicks / $total_mobile_impressions ) * 100, 2 );
		}

		echo '<div class="wrap">';
		echo '<h1>Forum advertentie stats</h1>';
		echo '<p>Overzicht van impressies en kliks op de vaste advertentie en van kliks op links in bbPress-handtekeningen.</p>';
		echo '<form method="get" style="margin:16px 0 20px;">';
		echo '<input type="hidden" name="page" value="zf-forum-ad-stats">';
		echo '<label for="zf-forum-ad-stats-days"><strong>Periode</strong></label> ';
		echo '<select id="zf-forum-ad-stats-days" name="days">';
		echo '<option value="7"' . selected( $days, 7, false ) . '>7 dagen</option>';
		echo '<option value="30"' . selected( $days, 30, false ) . '>30 dagen</option>';
		echo '<option value="90"' . selected( $days, 90, false ) . '>90 dagen</option>';
		echo '<option value="365"' . selected( $days, 365, false ) . '>365 dagen</option>';
		echo '</select> ';
		submit_button( 'Filter', 'secondary', '', false );
		echo '</form>';

		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Impressies</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $total_views ) ) . '</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Advertentiekliks</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $total_click ) ) . '</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Handtekening kliks</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $total_signature_clicks ) ) . '</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Desktop kliks</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $total_desktop_clicks ) ) . '</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Mobiele kliks</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $total_mobile_clicks ) ) . '</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">CTR</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $ctr, 2 ) ) . '%</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Desktop CTR</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $desktop_ctr, 2 ) ) . '%</div><div style="font-size:12px;color:#646970;">' . esc_html( number_format_i18n( $total_desktop_clicks ) ) . ' / ' . esc_html( number_format_i18n( $total_desktop_impressions ) ) . '</div></div>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:180px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;">Mobiele CTR</div><div style="font-size:28px;font-weight:700;">' . esc_html( number_format_i18n( $mobile_ctr, 2 ) ) . '%</div><div style="font-size:12px;color:#646970;">' . esc_html( number_format_i18n( $total_mobile_clicks ) ) . ' / ' . esc_html( number_format_i18n( $total_mobile_impressions ) ) . '</div></div>';
		echo '</div>';

		echo '<h2>Kliks per topic</h2>';
		echo '<p>Topics zonder advertentieklik worden niet afzonderlijk opgeslagen.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Bestemming</th><th>Plaatsing</th><th>Apparaat</th><th>Forum</th><th>Topic</th><th>Kliks</th><th>Laatste klik</th></tr></thead><tbody>';

		if ( empty( $summary ) ) {
			echo '<tr><td colspan="7">Nog geen kliks gevonden in deze periode.</td></tr>';
		} else {
			for ( $i = 0; $i < count( $summary ); $i++ ) {
				$row         = $summary[ $i ];
				$row_clicks  = (int) $row['clicks'];
				$forum_url   = ! empty( $row['forum_id'] ) ? get_permalink( (int) $row['forum_id'] ) : '';
				$topic_url   = ! empty( $row['topic_id'] ) ? get_permalink( (int) $row['topic_id'] ) : '';
				$forum_label = ! empty( $row['forum_title'] ) ? $row['forum_title'] : '-';
				$topic_label = ! empty( $row['topic_title'] ) ? $row['topic_title'] : '-';
				$device_label = 'Onbekend';
				$placement_label = 'select_signature' === $row['event_type'] ? 'Handtekening' : 'Advertentie';

				if ( 'desktop' === $row['device_type'] ) {
					$device_label = 'Desktop';
				} elseif ( 'mobile' === $row['device_type'] ) {
					$device_label = 'Mobiel';
				}

				echo '<tr>';
				echo '<td><a href="' . esc_url( $row['destination_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $row['destination_url'] ) . '</a></td>';
				echo '<td>' . esc_html( $placement_label ) . '</td>';
				echo '<td>' . esc_html( $device_label ) . '</td>';
				echo '<td>' . ( $forum_url ? '<a href="' . esc_url( $forum_url ) . '" target="_blank" rel="noopener">' . esc_html( $forum_label ) . '</a>' : esc_html( $forum_label ) ) . '</td>';
				echo '<td>' . ( $topic_url ? '<a href="' . esc_url( $topic_url ) . '" target="_blank" rel="noopener">' . esc_html( $topic_label ) . '</a>' : esc_html( $topic_label ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $row_clicks ) ) . '</td>';
				echo '<td>' . esc_html( $row['last_seen'] ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '<h2 style="margin-top:28px;">Per dag</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Datum</th><th>Impressies</th><th>Advertentiekliks</th><th>Handtekeningkliks</th><th>Desktop imp.</th><th>Desktop kliks</th><th>Desktop CTR</th><th>Mobiel imp.</th><th>Mobiele kliks</th><th>Mobiele CTR</th><th>CTR totaal</th></tr></thead><tbody>';

		if ( empty( $daily ) ) {
			echo '<tr><td colspan="11">Nog geen dagelijkse data gevonden in deze periode.</td></tr>';
		} else {
			for ( $i = 0; $i < count( $daily ); $i++ ) {
				$row             = $daily[ $i ];
				$day_impressions = (int) $row['impressions'];
				$day_clicks      = (int) $row['clicks'];
				$day_signature_clicks = (int) $row['signature_clicks'];
				$day_desktop_impressions = (int) $row['desktop_impressions'];
				$day_mobile_impressions = (int) $row['mobile_impressions'];
				$day_desktop_clicks = (int) $row['desktop_clicks'];
				$day_mobile_clicks = (int) $row['mobile_clicks'];
				$day_ctr         = $day_impressions > 0 ? round( ( $day_clicks / $day_impressions ) * 100, 2 ) : 0;
				$day_desktop_ctr = $day_desktop_impressions > 0 ? round( ( $day_desktop_clicks / $day_desktop_impressions ) * 100, 2 ) : 0;
				$day_mobile_ctr  = $day_mobile_impressions > 0 ? round( ( $day_mobile_clicks / $day_mobile_impressions ) * 100, 2 ) : 0;

				echo '<tr>';
				echo '<td>' . esc_html( $row['event_date'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_impressions ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_clicks ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_signature_clicks ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_desktop_impressions ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_desktop_clicks ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_desktop_ctr, 2 ) ) . '%</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_mobile_impressions ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_mobile_clicks ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_mobile_ctr, 2 ) ) . '%</td>';
				echo '<td>' . esc_html( number_format_i18n( $day_ctr, 2 ) ) . '%</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}

if ( ! function_exists( 'zf_forum_ad_stats_add_dashboard_widget' ) ) {
	function zf_forum_ad_stats_add_dashboard_widget() {
		wp_add_dashboard_widget(
			'zf_forum_ad_stats_dashboard',
			'Forum advertentie stats',
			'zf_forum_ad_stats_render_dashboard_widget'
		);
	}
}
add_action( 'wp_dashboard_setup', 'zf_forum_ad_stats_add_dashboard_widget' );

if ( ! function_exists( 'zf_forum_ad_stats_render_dashboard_widget' ) ) {
		function zf_forum_ad_stats_render_dashboard_widget() {
			$summary     = zf_forum_ad_stats_get_summary_rows( 30 );
			$daily       = zf_forum_ad_stats_get_daily_rows( 30 );
		$total_views = 0;
		$total_click = 0;
		$total_signature_clicks = 0;
		$total_desktop_impressions = 0;
		$total_mobile_impressions = 0;
		$total_desktop_clicks = 0;
		$total_mobile_clicks = 0;
		$top_row     = null;
		$ctr         = 0;
		$desktop_ctr = 0;
		$mobile_ctr  = 0;
		$i           = 0;
		$row         = array();

			for ( $i = 0; $i < count( $daily ); $i++ ) {
				$total_views += (int) $daily[ $i ]['impressions'];
				$total_click += (int) $daily[ $i ]['clicks'];
				$total_signature_clicks += (int) $daily[ $i ]['signature_clicks'];
				$total_desktop_impressions += (int) $daily[ $i ]['desktop_impressions'];
				$total_mobile_impressions += (int) $daily[ $i ]['mobile_impressions'];
				$total_desktop_clicks += (int) $daily[ $i ]['desktop_clicks'];
				$total_mobile_clicks += (int) $daily[ $i ]['mobile_clicks'];
			}

			for ( $i = 0; $i < count( $summary ); $i++ ) {
				$row = $summary[ $i ];
				if ( null === $top_row || (int) $row['clicks'] > (int) $top_row['clicks'] ) {
					$top_row = $row;
			}
		}

		if ( $total_views > 0 ) {
			$ctr = round( ( $total_click / $total_views ) * 100, 2 );
		}

		if ( $total_desktop_impressions > 0 ) {
			$desktop_ctr = round( ( $total_desktop_clicks / $total_desktop_impressions ) * 100, 2 );
		}

		if ( $total_mobile_impressions > 0 ) {
			$mobile_ctr = round( ( $total_mobile_clicks / $total_mobile_impressions ) * 100, 2 );
		}

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:12px;margin-bottom:16px;">';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">30d impressies</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $total_views ) ) . '</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">Advertentiekliks</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $total_click ) ) . '</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">Handtekening</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $total_signature_clicks ) ) . '</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">Desktop</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $total_desktop_clicks ) ) . '</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">Mobiel</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $total_mobile_clicks ) ) . '</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">30d CTR</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $ctr, 2 ) ) . '%</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">Desktop CTR</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $desktop_ctr, 2 ) ) . '%</div><div style="font-size:11px;color:#646970;">' . esc_html( number_format_i18n( $total_desktop_clicks ) ) . ' / ' . esc_html( number_format_i18n( $total_desktop_impressions ) ) . '</div></div>';
		echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;">Mobiele CTR</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format_i18n( $mobile_ctr, 2 ) ) . '%</div><div style="font-size:11px;color:#646970;">' . esc_html( number_format_i18n( $total_mobile_clicks ) ) . ' / ' . esc_html( number_format_i18n( $total_mobile_impressions ) ) . '</div></div>';
		echo '</div>';

		if ( $top_row ) {
			echo '<p style="margin:0 0 8px;"><strong>Meest aangeklikte plaatsing van de laatste 30 dagen</strong></p>';
			echo '<p style="margin:0 0 6px;"><a href="' . esc_url( $top_row['destination_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $top_row['destination_url'] ) . '</a></p>';
			echo '<p style="margin:0;color:#50575e;">' . esc_html( ! empty( $top_row['topic_title'] ) ? $top_row['topic_title'] : '-' ) . ' | ' . esc_html( number_format_i18n( (int) $top_row['clicks'] ) ) . ' kliks</p>';
		} else {
			echo '<p style="margin:0;">Nog geen advertentiedata beschikbaar.</p>';
		}

		echo '<p style="margin:14px 0 0;"><a href="' . esc_url( admin_url( 'admin.php?page=zf-forum-ad-stats' ) ) . '">Volledig overzicht openen</a></p>';
	}
}

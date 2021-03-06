<?php
/**
 * Plugin Name: PMPro LPV User Interface
 * Author: Stranger Studios && pbrocks
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/admin.php' );

include( 'pmpro-lpv-notification-bar.php' );

add_action( 'init', 'pmpro_lpv_ui_init' );
/**
 * Sets constants and whether or not to use JavaScript.
 *
 * @since 0.3.0
 */
function pmpro_lpv_ui_init() {
	// Check for backwards compatibility
	if ( ! defined( 'PMPRO_LPV_LIMIT' ) ) {
		global $current_user;
		if ( ! empty( $current_user->membership_level ) ) {
			$level_id = $current_user->membership_level->id;
		}
		if ( empty( $level_id ) ) {
			$level_id = 0;
		}

		$limit = get_option( 'pmprolpv_limit_' . $level_id );
		if ( ! empty( $limit ) ) {
			define( 'PMPRO_LPV_LIMIT', $limit['views'] );
			define( 'PMPRO_LPV_LIMIT_PERIOD', $limit['period'] );
		}
	}

	// Check for backwards compatibility
	if ( ! defined( 'PMPRO_LPV_USE_JAVASCRIPT' ) ) {
		$use_js = get_option( 'pmprolpv_use_js' );
		define( 'PMPRO_LPV_USE_JAVASCRIPT', $use_js );
	}
	return $level_id;
}

add_action( 'init', 'pmpro_get_user_level' );
/**
 * [pmpro_get_user_level Return the member's level
 *
 * @return [type] If not a member, level = 0
 */
function pmpro_get_user_level() {
	global $current_user;
	if ( ! empty( $current_user->membership_level ) ) {
		$level_id = $current_user->membership_level->id;
	}
	if ( empty( $level_id ) ) {
		$level_id = 0;
	}
	return $level_id;
}

add_action( 'wp_ajax_tie_into_lpv_diagnostics', 'pbrx_header_set_cookie' );
add_action( 'wp_ajax_nopriv_tie_into_lpv_diagnostics', 'pbrx_header_set_cookie' );
// add_action( 'init', 'pbrx_header_set_cookie' );
/**
 * [pbrx_header_set_cookie This AJAX is going to run on page load
 *                  It'll be too late for php to set a cookie, but
 *                  we can do so with Javascript
 *
 * @return [type] [description]
 */
function pbrx_header_set_cookie() {
	$ajax_data = $_POST;
	$month = date( 'n', current_time( 'timestamp' ) );
	if ( ! empty( $_COOKIE['pmpro_lpv_count'] ) ) {
		global $current_user;
		$parts = explode( ';', $_COOKIE['pmpro_lpv_count'] );
		$limitparts = explode( ',', $parts[0] );
		// Get the level limit for the current user.
		if ( defined( 'PMPRO_LPV_LIMIT' ) && PMPRO_LPV_LIMIT > 0 ) {
			$limit = intval( PMPRO_LPV_LIMIT );
		}
		$ajax_data['parts'] = $parts;
		$ajax_data['limitparts'] = $limitparts;
		$ajax_data['level'] = $limitparts[0];
		$ajax_data['view'] = $limitparts[1];
		$ajax_data['limit'] = $limit;
	}

	$curlev = pmpro_get_user_level();
	$curviews = $limitparts[1];
	$expires = date( 'Y-m-d', strtotime( '+30 days' ) );
	$cookiestr .= "$curlev,$curviews";
	echo json_encode( $ajax_data );

	exit();
}
/**
 * pbrx_modal_header Diagnostic info
 *
 * @return [type] [description]
 */
function pbrx_header_message() {
	$stg = '';
	?>
	<form id="lpv-diagnostics-form">
		<input type="hidden" name="hidden" value="lpv-diagnostics-test">
	<?php
	$cur_lev = pmpro_get_user_level();
	$xyz = basename( __FILE__ );
	$xyz = 'Limit ' . PMPRO_LPV_LIMIT . ' | per ' . PMPRO_LPV_LIMIT_PERIOD . ' | js ' . PMPRO_LPV_USE_JAVASCRIPT;
	if ( isset( $_COOKIE['pmpro_lpv_count'] ) ) {
		$button_value = 'Reset Cookie';
		// $button_value = 3600 * 24 * 100 . ' seconds';
		$button = '<input type="hidden" name="token" value="reset">';
		$stg = 'Current Level ' . $cur_lev . ' $_COOKIE(\'pmpro_lpv_count\') SET !! ' . $button;
	} else {
		$button_value = 'Set Cookie';
		$button = '<input type="hidden" name="token" value="set">';
		$stg = 'Current Level ' . $cur_lev . ' $_COOKIE(\'pmpro_lpv_count\') NOT set ?!?!? ' . $button;
	}
		?>
	</form>
	<script type="text/javascript">
		// insertCounter();
	</script>
	<script type="text/javascript">
		readCookie('pmpro_lpv_count');
	</script>
	<?php
	$values = pmpro_lpv_cookie_values();
	// print_r( $values );
	$header = '<h3 style="z-index:1;position:relative;text-align:center;color:tomato;">' . $stg . '<br>' . $xyz . ' Count <span id="lpv_counter">' . $values['count'] . '</span></h3><div id="data-returned">data-returned here</div><div id="demo">demo</div>';
	// if ( current_user_can( 'manage_options' ) ) {
		echo $header;
	// }
}
add_action( 'wp_head', 'pbrx_header_message' );

/**
 * Limit post views or load JS to do the same.
 * Hooks into wp action: add_action( 'wp', 'pmpro_lpv_wp1' );
 */
function pmpro_lpv_wp1() {
	global $current_user;
	if ( function_exists( 'pmpro_has_membership_access' ) ) {
		/**
		 * If we're viewing a page that the user doesn't have access to...
		 * Could add extra checks here.
		 */
		if ( ! pmpro_has_membership_access() ) {
			/**
			 * Filter which post types should be tracked by LPV
			 *
			 * @since .4
			 */
			$pmprolpv_post_types = apply_filters( 'pmprolpv_post_types', array( 'post' ) );
			$queried_object = get_queried_object();
			if ( empty( $queried_object ) || empty( $queried_object->post_type ) || ! in_array( $queried_object->post_type, $pmprolpv_post_types, true ) ) {
				return;
			}

			$hasaccess = apply_filters( 'pmprolpv_has_membership_access', true, $queried_object );
			if ( false === $hasaccess ) {
				set_pmpro_lpv_redirect();
			}

			// if we're using javascript, just give them access and let JS redirect them.
			if ( defined( 'PMPRO_LPV_USE_JAVASCRIPT' ) && PMPRO_LPV_USE_JAVASCRIPT ) {
				wp_enqueue_script( 'wp-utils', includes_url( '/js/utils.js' ) );
				add_action( 'wp_footer', 'pmpro_lpv_wp_footer1' );
				add_filter( 'pmpro_has_membership_access_filter', '__return_true' );

				return;
			}

			// PHP is going to handle cookie check and redirect.
			$level = pmpro_getMembershipLevelForUser( $current_user->ID );
			if ( ! empty( $level->id ) ) {
				$level_id = $level->id;
			} else {
				$level_id = null;
			}

			$cookie_values = pmpro_lpv_cookie_values();

			// if count is above limit, redirect, otherwise update cookie.
			if ( defined( 'PMPRO_LPV_LIMIT' ) && $count > PMPRO_LPV_LIMIT ) {
				set_pmpro_lpv_redirect();
			} else {
				// give them access and track the view.
				add_filter( 'pmpro_has_membership_access_filter', '__return_true' );

				if ( defined( 'PMPRO_LPV_LIMIT_PERIOD' ) ) {
					switch ( PMPRO_LPV_LIMIT_PERIOD ) {
						case 'hour':
							$expires = current_time( 'timestamp' ) + HOUR_IN_SECONDS;
							break;
						case 'day':
							$expires = current_time( 'timestamp' ) + DAY_IN_SECONDS;
							break;
						case 'week':
							$expires = current_time( 'timestamp' ) + WEEK_IN_SECONDS;
							break;
						case 'month':
							$expires = current_time( 'timestamp' ) + ( DAY_IN_SECONDS * 30 );
					}
				} else {
					$expires = current_time( 'timestamp' ) + ( DAY_IN_SECONDS * 30 );
				}

				// put the cookie string back together with updated values.
				$cookiestr = '';
				foreach ( $levellimits as $curlev => $curviews ) {
					$cookiestr .= "$curlev,$curviews";
				}
				// setcookie( 'pmpro_lpv_count', $cookiestr . ';' . $month, $expires, '/' );
				set_pmpro_lpv_cookie( $cookiestr );
			}
		}
	}
}

function pmpro_lpv_cookie_values() {
	$thismonth = date( 'n', current_time( 'timestamp' ) );
	$level_id = pmpro_get_user_level();
	// check for past views.
	if ( ! empty( $_COOKIE['pmpro_lpv_count'] ) ) {
		$month = $thismonth;
		$parts = explode( ';', sanitize_text_field( $_COOKIE['pmpro_lpv_count'] ) );
		if ( count( $parts ) > 1 ) { // just in case.
			$month = $parts[1];
		} else { // for one-time cookie format migration.
			$parts[0] = '0,0';
		}
		$limitparts = explode( ',', $parts[0] );
		$levellimits = array();
		$length = count( $limitparts );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( $i % 2 === 1 ) {
				$levellimits[ $limitparts[ $i - 1 ] ] = $limitparts[ $i ];
			}
		}
		if ( $month == $thismonth && array_key_exists( $level_id, $levellimits ) ) {
			$count = $levellimits[ $level_id ] + 1; // same month as other views.
			$levellimits[ $level_id ]++;
		} elseif ( $month == $thismonth ) { // same month, but we haven't ticked yet.
			$count = 1;
			$levellimits[ $level_id ] = 1;
		} else {
			$count = 1;                     // new month.
			$levellimits = array();
			$levellimits[ $level_id ] = 1;
			$month = $thismonth;
		}
	} else {
		// new user.
		$count = 1;
		$levellimits = array();
		$levellimits[ $level_id ] = 1;
		$month = $thismonth;
	}
	$return['count'] = $count;
	$return['month'] = $month;
	return $return;
}

add_action( 'init', 'set_pmpro_lpv_cookie' );
function set_pmpro_lpv_cookie( $cookiestr = '1,3' ) {
	$expires = time() + 3600 * 24 * 100;
	$month = date( 'n', current_time( 'timestamp' ) );
	$curlev = pmpro_get_user_level();
	$views = pmpro_lpv_cookie_values();
	$curviews = $views['count'];
	$cookiestr .= "$curlev,$curviews";

	if ( ! is_admin() && ! isset( $_COOKIE['pmpro_lpv_count'] ) ) {
		setcookie( 'pmpro_lpv_count', $cookiestr . ';' . $month, $expires, COOKIEPATH, COOKIE_DOMAIN, false );
	}
}

/**
 * Redirect to  the configured page or the default levels page
 */
function set_pmpro_lpv_redirect() {
	$page_id = get_option( 'pmprolpv_redirect_page' );

	if ( empty( $page_id ) ) {
		$redirect_url = pmpro_url( 'levels' );
	} else {
		$redirect_url = get_the_permalink( $page_id );
	}

	wp_redirect( $redirect_url );
	exit;
}


function test_ajax_data() {
	wp_register_script( 'lpv-diagnostics', plugins_url( '/js/lpv-diagnostics.js', __FILE__ ), array( 'jquery' ), false, false );
	wp_localize_script(
		'lpv-diagnostics', 'lpv_diagnostics_object', array(
			'lpv_diagnostics_ajaxurl' => admin_url( 'admin-ajax.php' ),
			'lpv_diagnostics_nonce' => wp_create_nonce( 'lpv-diagnostics-nonce' ),
			'lpv_diagnostics_user_level' => pmpro_get_user_level(),
			'lpv_diagnostics_cookie_values' => pmpro_lpv_cookie_values(),
			'lpv_diagnostics_php_expire' => date( 'Y-m-d H:i:s', strtotime( 'today + 1 week' ) ),
		)
	);
	wp_enqueue_script( 'lpv-diagnostics' );
	wp_register_script( 'set-get-cookies', plugins_url( '/js/set-get-cookies.js', __FILE__ ), array( 'jquery' ), false, false );
	wp_enqueue_script( 'set-get-cookies' );
	wp_register_script( 'set-cookie-2009', plugins_url( '/js/set-cookie-2009.js', __FILE__ ), array( 'jquery' ), false, false );
	wp_enqueue_script( 'set-cookie-2009' );
}
add_action( 'wp_enqueue_scripts', 'test_ajax_data' );

/**
 * Function to add links to the plugin action links
 *
 * @param array $links Array of links to be shown in plugin action links.
 */
function pmpro_lpv_ui_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$new_links = array(
			'<a href="' . get_admin_url( null, 'admin.php?page=pmpro-limitpostviews' ) . '">' . __( 'Settings', 'pmpro-lpv-ui' ) . '</a>',
		);
	}
	return array_merge( $new_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pmpro_lpv_ui_plugin_action_links' );


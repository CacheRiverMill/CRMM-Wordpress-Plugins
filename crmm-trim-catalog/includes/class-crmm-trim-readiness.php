<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Readiness {
	private static array $blocked_publish = array();

	public static function boot(): void {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'gate_publication' ), 20, 2 );
		add_filter( 'redirect_post_location', array( __CLASS__, 'add_blocked_notice' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_blocked_notice' ) );
	}

	public static function evaluate( int $post_id ): array {
		$blockers = array();
		$warnings = array();

		$integrity = CRMM_Trim_Integrity::validate( $post_id );
		if ( is_wp_error( $integrity ) ) {
			$blockers[] = $integrity->get_error_message();
		}
		if ( ! (bool) get_post_meta( $post_id, 'marketing_catalog', true ) ) {
			$blockers[] = __( 'Marketing catalog approval is required.', 'crmm-trim-catalog' );
		}
		if ( 'verified' !== get_post_meta( $post_id, 'verification_status', true ) ) {
			$blockers[] = __( 'The profile must be verified.', 'crmm-trim-catalog' );
		}
		if ( '' === (string) get_post_meta( $post_id, 'catalog_width', true ) || '' === (string) get_post_meta( $post_id, 'catalog_height', true ) ) {
			$blockers[] = __( 'Catalog width and height are required.', 'crmm-trim-catalog' );
		}
		if ( ! absint( get_post_meta( $post_id, 'profile_image', true ) ) ) {
			$blockers[] = __( 'A primary profile image is required.', 'crmm-trim-catalog' );
		}

		foreach ( array( 'profile_render' => 'Profile render', 'isometric_view' => 'Isometric view', 'pdf_download' => 'Profile PDF', 'dxf_download' => 'DXF download' ) as $meta_key => $label ) {
			if ( ! get_post_meta( $post_id, $meta_key, true ) ) {
				$warnings[] = sprintf( __( '%s is missing.', 'crmm-trim-catalog' ), $label );
			}
		}

		return array(
			'ready'    => empty( $blockers ),
			'state'    => empty( $blockers ) ? 'ready' : 'blocked',
			'blockers' => $blockers,
			'warnings' => $warnings,
		);
	}

	public static function gate_publication( array $data, array $postarr ): array {
		if ( CRMM_Trim_Post_Type::POST_TYPE !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		$post_id = absint( $postarr['ID'] ?? 0 );
		if ( ! $post_id ) {
			$data['post_status'] = 'draft';
			self::$blocked_publish[0] = array( __( 'Save the profile before publishing it.', 'crmm-trim-catalog' ) );
			return $data;
		}

		$readiness = self::evaluate( $post_id );
		if ( ! $readiness['ready'] ) {
			$data['post_status'] = 'draft';
			self::$blocked_publish[ $post_id ] = $readiness['blockers'];
			set_transient( 'crmm_trim_publish_blocked_' . get_current_user_id(), $readiness['blockers'], MINUTE_IN_SECONDS );
		}

		return $data;
	}

	public static function add_blocked_notice( string $location, int $post_id ): string {
		if ( isset( self::$blocked_publish[ $post_id ] ) || isset( self::$blocked_publish[0] ) ) {
			$location = add_query_arg( 'crmm_trim_publish_blocked', '1', $location );
		}
		return $location;
	}

	public static function render_blocked_notice(): void {
		if ( empty( $_GET['crmm_trim_publish_blocked'] ) ) {
			return;
		}
		$blockers = get_transient( 'crmm_trim_publish_blocked_' . get_current_user_id() );
		delete_transient( 'crmm_trim_publish_blocked_' . get_current_user_id() );
		if ( ! is_array( $blockers ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'This trim profile was not published.', 'crmm-trim-catalog' ) . '</strong></p><ul>';
		foreach ( $blockers as $blocker ) {
			echo '<li>' . esc_html( $blocker ) . '</li>';
		}
		echo '</ul></div>';
	}
}

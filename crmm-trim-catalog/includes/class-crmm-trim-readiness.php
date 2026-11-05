<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Readiness {
	private static array $blocked_publish = array();

	public static function boot(): void {
		add_action( 'add_meta_boxes_' . CRMM_Trim_Post_Type::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_crmm_trim_readiness', array( __CLASS__, 'ajax_readiness' ) );
		add_filter( 'rest_pre_insert_' . CRMM_Trim_Post_Type::POST_TYPE, array( __CLASS__, 'validate_rest_publication' ), 20, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'gate_publication' ), 20, 2 );
		add_filter( 'redirect_post_location', array( __CLASS__, 'add_blocked_notice' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_blocked_notice' ) );
	}

	public static function evaluate( int $post_id ): array {
		$blockers = array();
		$warnings = array();
		$checks = array();

		$integrity = CRMM_Trim_Integrity::validate( $post_id );
		$status = (string) get_post_meta( $post_id, 'verification_status', true );
		$status_labels = array( 'verified' => 'Verified', 'discrepancy' => 'Discrepancy found', 'not_reviewed' => 'Not reviewed', 'in_progress' => 'In progress' );
		$width = (string) get_post_meta( $post_id, 'catalog_width', true );
		$height = (string) get_post_meta( $post_id, 'catalog_height', true );
		$checks = array(
			array( 'label' => __( 'Permanent number and classification', 'crmm-trim-catalog' ), 'passed' => ! is_wp_error( $integrity ), 'detail' => is_wp_error( $integrity ) ? $integrity->get_error_message() : CRMM_Trim_Number_Registry::for_post( $post_id ), 'action' => __( 'Ask the catalog manager to resolve the number or classification issue.', 'crmm-trim-catalog' ) ),
			array( 'label' => __( 'Marketing approval', 'crmm-trim-catalog' ), 'passed' => (bool) get_post_meta( $post_id, 'marketing_catalog', true ), 'detail' => '', 'action' => __( 'Set Approved for Marketing Catalog to Yes in Catalog Classification.', 'crmm-trim-catalog' ) ),
			array( 'label' => __( 'Profile verification', 'crmm-trim-catalog' ), 'passed' => 'verified' === $status, 'detail' => __( $status_labels[ $status ] ?? 'Not reviewed', 'crmm-trim-catalog' ), 'action' => __( 'An authorized reviewer must resolve any discrepancy and set Verification Status to Verified in Internal Shop Records.', 'crmm-trim-catalog' ) ),
			array( 'label' => __( 'Catalog dimensions', 'crmm-trim-catalog' ), 'passed' => '' !== $width && '' !== $height, 'detail' => '' !== $width && '' !== $height ? $width . ' × ' . $height . ' in' : '', 'action' => __( 'Enter both Catalog Width and Catalog Height in Public Profile Details.', 'crmm-trim-catalog' ) ),
			array( 'label' => __( 'Primary profile image', 'crmm-trim-catalog' ), 'passed' => (bool) absint( get_post_meta( $post_id, 'profile_image', true ) ), 'detail' => '', 'action' => __( 'Add a Profile Image in Public Profile Details. The sidebar featured image is a different field.', 'crmm-trim-catalog' ) ),
		);
		foreach ( $checks as $check ) {
			if ( ! $check['passed'] ) {
				$blockers[] = $check['label'] . ( $check['detail'] ? ': ' . $check['detail'] . '.' : '.' ) . ' ' . $check['action'];
			}
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
			'checks'   => $checks,
		);
	}

	public static function add_meta_box(): void {
		add_meta_box( 'crmm-trim-publication', __( 'Publishing requirements', 'crmm-trim-catalog' ), array( __CLASS__, 'render_meta_box' ), CRMM_Trim_Post_Type::POST_TYPE, 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		echo '<div id="crmm-readiness-results" aria-live="polite">';
		self::render_requirements( $post->ID );
		echo '</div><p id="crmm-readiness-save-state" class="description" role="status">' . esc_html__( 'These checks use saved details. After corrections, click Save draft, then Publish. Saving does not publish the profile.', 'crmm-trim-catalog' ) . '</p>';
		echo '<button type="button" class="button" id="crmm-refresh-readiness">' . esc_html__( 'Refresh saved checks', 'crmm-trim-catalog' ) . '</button>';
	}

	public static function render_requirements( int $post_id ): void {
		$readiness = self::evaluate( $post_id );
		printf( '<p class="crmm-readiness-summary %1$s"><strong>%2$s</strong></p>', $readiness['ready'] ? 'is-ready' : 'is-blocked', esc_html( $readiness['ready'] ? __( 'Ready to publish', 'crmm-trim-catalog' ) : sprintf( __( 'Cannot publish yet — %d requirements need attention', 'crmm-trim-catalog' ), count( $readiness['blockers'] ) ) ) );
		echo '<ul class="crmm-readiness-checks">';
		foreach ( $readiness['checks'] as $check ) {
			printf( '<li class="%1$s"><strong>%2$s: %3$s</strong>%4$s', $check['passed'] ? 'is-ready' : 'is-blocked', esc_html( $check['passed'] ? __( 'Complete', 'crmm-trim-catalog' ) : __( 'Needs attention', 'crmm-trim-catalog' ) ), esc_html( $check['label'] ), $check['detail'] ? ' — ' . esc_html( $check['detail'] ) : '' );
			if ( ! $check['passed'] ) {
				echo '<p>' . esc_html( $check['action'] ) . '</p>';
			}
			echo '</li>';
		}
		echo '</ul><p class="description">' . esc_html__( 'Knife availability does not prevent publishing.', 'crmm-trim-catalog' ) . '</p>';
		if ( $readiness['warnings'] ) {
			echo '<details><summary>' . esc_html__( 'Optional files — do not prevent publishing', 'crmm-trim-catalog' ) . '</summary><ul>';
			foreach ( $readiness['warnings'] as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>';
			}
			echo '</ul></details>';
		}
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || CRMM_Trim_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'crmm-trim-readiness', plugins_url( 'assets/js/admin-readiness.js', CRMM_TRIM_CATALOG_FILE ), array( 'jquery', 'wp-data' ), CRMM_TRIM_CATALOG_VERSION, true );
		wp_localize_script( 'crmm-trim-readiness', 'CRMMTrimReadiness', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'crmm_trim_readiness' ), 'postId' => get_the_ID(), 'savedMessage' => __( 'Saved details checked. If all requirements are complete, click Publish.', 'crmm-trim-catalog' ), 'changedMessage' => __( 'You have changed profile details. Click Save draft to update these checks, then Publish.', 'crmm-trim-catalog' ), 'errorMessage' => __( 'Saved checks could not be refreshed. Reload the editor after saving to see the latest requirements.', 'crmm-trim-catalog' ) ) );
	}

	public static function ajax_readiness(): void {
		check_ajax_referer( 'crmm_trim_readiness', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot view the requirements for this profile.', 'crmm-trim-catalog' ) ), 403 );
		}
		ob_start();
		self::render_requirements( $post_id );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	public static function validate_rest_publication( mixed $prepared_post, WP_REST_Request $request ): mixed {
		if ( is_wp_error( $prepared_post ) || 'publish' !== $request->get_param( 'status' ) ) {
			return $prepared_post;
		}
		$post_id = absint( $request->get_param( 'id' ) );
		$readiness = $post_id ? self::evaluate( $post_id ) : array( 'ready' => false, 'blockers' => array( __( 'Save the profile as a draft before publishing.', 'crmm-trim-catalog' ) ) );
		if ( ! $readiness['ready'] ) {
			return new WP_Error( 'crmm_trim_publish_blocked', __( 'Cannot publish this profile: ', 'crmm-trim-catalog' ) . implode( ' ', $readiness['blockers'] ) . ' ' . __( 'Save your corrections as a draft, then publish again.', 'crmm-trim-catalog' ), array( 'status' => 400, 'blockers' => $readiness['blockers'] ) );
		}
		return $prepared_post;
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


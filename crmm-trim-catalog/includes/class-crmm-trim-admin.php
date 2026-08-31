<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Admin {
	public static function boot(): void {
		add_action( 'add_meta_boxes_' . CRMM_Trim_Post_Type::POST_TYPE, array( __CLASS__, 'add_number_meta_box' ) );
		add_action( 'admin_post_crmm_assign_trim_number', array( __CLASS__, 'assign_number' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'void_deleted_number' ) );
		add_action( 'admin_menu', array( 'CRMM_Trim_Importer', 'register_page' ) );
		add_action( 'admin_post_crmm_trim_import', array( 'CRMM_Trim_Importer', 'handle_upload' ) );
		add_action( 'wp_ajax_crmm_preview_trim_number', array( __CLASS__, 'preview_number' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	public static function add_number_meta_box(): void {
		add_meta_box(
			'crmm-trim-number-registry',
			__( 'Part Number Registry', 'crmm-trim-catalog' ),
			array( __CLASS__, 'render_number_meta_box' ),
			CRMM_Trim_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function render_number_meta_box( WP_Post $post ): void {
		$part_number = CRMM_Trim_Number_Registry::for_post( $post->ID );

		if ( $part_number ) {
			echo '<p><strong>' . esc_html( $part_number ) . '</strong></p>';
			echo '<p class="description">' . esc_html__( 'Assigned numbers are permanent. Trashing or deleting this profile will not make the number available again.', 'crmm-trim-catalog' ) . '</p>';
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the profile and select its category and profile type before assigning a number.', 'crmm-trim-catalog' ) . '</p>';
			return;
		}

		if ( ! current_user_can( CRMM_Trim_Capabilities::ASSIGN_NUMBERS ) ) {
			echo '<p>' . esc_html__( 'A catalog manager with number-assignment permission must assign the part number.', 'crmm-trim-catalog' ) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Assignment uses the next number after the highest existing number for this category and profile type. Sequence gaps are never filled.', 'crmm-trim-catalog' ) . '</p>';
		echo '<p id="crmm-expected-number" class="description">' . esc_html__( 'Select a category and profile type to preview the expected next number.', 'crmm-trim-catalog' ) . '</p>';
		echo '<button type="button" class="button button-primary" id="crmm-assign-number" data-post-id="' . esc_attr( (string) $post->ID ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'crmm_assign_trim_number_' . $post->ID ) ) . '" data-action-url="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . esc_html__( 'Assign Next Part Number', 'crmm-trim-catalog' ) . '</button>';
	}

	public static function assign_number(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id || ! current_user_can( CRMM_Trim_Capabilities::ASSIGN_NUMBERS ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die(
				esc_html__( 'You are not allowed to assign a number to this profile.', 'crmm-trim-catalog' ),
				esc_html__( 'Forbidden', 'crmm-trim-catalog' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'crmm_assign_trim_number_' . $post_id );

		$categories = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY );
		$subtypes   = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY );

		if ( is_wp_error( $categories ) || is_wp_error( $subtypes ) || 1 !== count( $categories ) || 1 !== count( $subtypes ) ) {
			self::redirect( $post_id, 'missing_classification' );
		}

		$category_code = (string) get_term_meta( $categories[0]->term_id, 'crmm_category_code', true );
		$subtype_code  = (string) get_term_meta( $subtypes[0]->term_id, 'crmm_subtype_code', true );

		if ( ! CRMM_Trim_Taxonomies::subtype_matches_category( $subtypes[0]->term_id, $category_code ) ) {
			self::redirect( $post_id, 'classification_mismatch' );
		}

		$result = CRMM_Trim_Number_Registry::allocate( $post_id, $category_code, $subtype_code, get_current_user_id() );
		self::redirect( $post_id, is_wp_error( $result ) ? 'assignment_failed' : 'assigned' );
	}

	public static function notices(): void {
		if ( empty( $_GET['crmm_trim_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['crmm_trim_notice'] ) );
		$map    = array(
			'assigned'                => array( 'success', __( 'Part number assigned.', 'crmm-trim-catalog' ) ),
			'missing_classification'  => array( 'error', __( 'Select and save exactly one category and one profile type before assigning a number.', 'crmm-trim-catalog' ) ),
			'classification_mismatch' => array( 'error', __( 'The selected profile type does not belong to the selected main category.', 'crmm-trim-catalog' ) ),
			'assignment_failed'       => array( 'error', __( 'The part number could not be assigned. Check the error log and try again.', 'crmm-trim-catalog' ) ),
		);

		if ( isset( $map[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $notice ][0] ), esc_html( $map[ $notice ][1] ) );
		}
	}

	public static function void_deleted_number( int $post_id ): void {
		if ( CRMM_Trim_Post_Type::POST_TYPE === get_post_type( $post_id ) ) {
			CRMM_Trim_Number_Registry::void_for_post( $post_id );
		}
	}

	public static function preview_number(): void {
		check_ajax_referer( 'crmm_preview_trim_number', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! current_user_can( CRMM_Trim_Capabilities::ASSIGN_NUMBERS ) || ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to preview part numbers.', 'crmm-trim-catalog' ) ), 403 );
		}

		$category = CRMM_Trim_Taxonomies::category_term_by_code( sanitize_text_field( wp_unslash( $_POST['category_code'] ?? '' ) ) );
		$subtype  = CRMM_Trim_Taxonomies::subtype_term_by_code( sanitize_text_field( wp_unslash( $_POST['subtype_code'] ?? '' ) ) );
		if ( ! $category || ! $subtype || ! CRMM_Trim_Taxonomies::subtype_matches_category( $subtype->term_id, (string) get_term_meta( $category->term_id, 'crmm_category_code', true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a matching category and profile type.', 'crmm-trim-catalog' ) ), 400 );
		}

		$number = CRMM_Trim_Number_Registry::preview_next(
			(string) get_term_meta( $category->term_id, 'crmm_category_code', true ),
			(string) get_term_meta( $subtype->term_id, 'crmm_subtype_code', true )
		);
		wp_send_json_success( array( 'part_number' => $number ) );
	}

	public static function enqueue_editor_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || CRMM_Trim_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$category_codes = array();
		$category_terms = get_terms( array( 'taxonomy' => CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, 'hide_empty' => false ) );
		foreach ( is_wp_error( $category_terms ) ? array() : $category_terms as $term ) {
			$category_codes[ (string) $term->term_id ] = (string) get_term_meta( $term->term_id, 'crmm_category_code', true );
		}
		$subtypes = array();
		$subtype_terms = get_terms( array( 'taxonomy' => CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY, 'hide_empty' => false ) );
		foreach ( is_wp_error( $subtype_terms ) ? array() : $subtype_terms as $term ) {
			$subtypes[ (string) $term->term_id ] = array(
				'code'     => (string) get_term_meta( $term->term_id, 'crmm_subtype_code', true ),
				'category' => (string) get_term_meta( $term->term_id, 'crmm_parent_category_code', true ),
			);
		}

		wp_enqueue_script( 'crmm-trim-admin-classification', plugins_url( 'assets/js/admin-classification.js', CRMM_TRIM_CATALOG_FILE ), array( 'jquery' ), CRMM_TRIM_CATALOG_VERSION, true );
		wp_localize_script(
			'crmm-trim-admin-classification',
			'CRMMTrimAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'crmm_preview_trim_number' ),
				'postId'        => get_the_ID(),
				'categoryCodes' => $category_codes,
				'subtypes'      => $subtypes,
				'previewLabel'  => __( 'Expected next number (not reserved):', 'crmm-trim-catalog' ),
			)
		);
	}

	private static function redirect( int $post_id, string $notice ): never {
		$url = add_query_arg(
			array(
				'post'             => $post_id,
				'action'           => 'edit',
				'crmm_trim_notice' => $notice,
			),
			admin_url( 'post.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}

<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Fields {
	private static array $previous_verification_status = array();
	private const INTERNAL_FIELD_KEYS = array(
		'field_crmm_old_crmm_number',
		'field_crmm_source_original_part_number',
		'field_crmm_template_status',
		'field_crmm_knife_status',
		'field_crmm_knife_storage_location',
		'field_crmm_board_width',
		'field_crmm_board_height',
		'field_crmm_internal_note',
		'field_crmm_verification_status',
		'field_crmm_verified_by',
		'field_crmm_verification_date',
		'field_crmm_discrepancy_notes',
		'field_crmm_onshape_url',
	);

	public static function boot(): void {
		add_action( 'acf/init', array( __CLASS__, 'register' ) );
		add_filter( 'acf/validate_value/key=field_crmm_trim_subtype', array( __CLASS__, 'validate_subtype' ), 10, 4 );
		add_filter( 'acf/prepare_field/key=field_crmm_trim_category', array( __CLASS__, 'lock_numbered_classification' ) );
		add_filter( 'acf/prepare_field/key=field_crmm_trim_subtype', array( __CLASS__, 'lock_numbered_classification' ) );
		add_filter( 'acf/update_value/key=field_crmm_trim_category', array( __CLASS__, 'protect_numbered_classification' ), 20, 3 );
		add_filter( 'acf/update_value/key=field_crmm_trim_subtype', array( __CLASS__, 'protect_numbered_classification' ), 20, 3 );
		add_filter( 'acf/update_value/key=field_crmm_part_number', array( __CLASS__, 'protect_registry_number' ), 20, 3 );
		add_filter( 'acf/prepare_field/key=field_crmm_marketing_catalog', array( __CLASS__, 'protect_approval_field' ) );
		add_filter( 'acf/update_value/key=field_crmm_marketing_catalog', array( __CLASS__, 'protect_approval_value' ), 20, 3 );
		add_filter( 'acf/prepare_field/key=field_crmm_verification_status', array( __CLASS__, 'protect_verification_field' ), 20 );
		add_filter( 'acf/update_value/key=field_crmm_verification_status', array( __CLASS__, 'stamp_verification' ), 20, 3 );
		add_action( 'acf/save_post', array( __CLASS__, 'reset_verification_after_material_change' ), 30 );

		foreach ( self::INTERNAL_FIELD_KEYS as $field_key ) {
			add_filter( 'acf/prepare_field/key=' . $field_key, array( __CLASS__, 'protect_internal_field' ) );
			add_filter( 'acf/update_value/key=' . $field_key, array( __CLASS__, 'protect_internal_value' ), 10, 3 );
		}
	}

	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'           => 'group_crmm_trim_classification',
				'title'         => __( 'Catalog Classification', 'crmm-trim-catalog' ),
				'fields'        => array(
					self::taxonomy_field( 'field_crmm_trim_category', 'trim_category', __( 'Main Category', 'crmm-trim-catalog' ), CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, true ),
					self::taxonomy_field( 'field_crmm_trim_subtype', 'trim_subtype', __( 'Profile Type', 'crmm-trim-catalog' ), CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY, true ),
					array(
						'key'           => 'field_crmm_marketing_catalog',
						'label'         => __( 'Approved for Marketing Catalog', 'crmm-trim-catalog' ),
						'name'          => 'marketing_catalog',
						'type'          => 'true_false',
						'instructions'  => __( 'Approval does not publish the post automatically.', 'crmm-trim-catalog' ),
						'default_value' => 0,
						'ui'            => 1,
					),
				),
				'location'      => self::location(),
				'position'      => 'normal',
				'style'         => 'default',
				'label_placement' => 'top',
				'show_in_rest'  => 1,
			),
		);

		acf_add_local_field_group(
			array(
				'key'           => 'group_crmm_trim_public_details',
				'title'         => __( 'Public Profile Details', 'crmm-trim-catalog' ),
				'fields'        => array(
					array(
						'key'          => 'field_crmm_part_number',
						'label'        => __( 'Part Number', 'crmm-trim-catalog' ),
						'name'         => CRMM_Trim_Number_Registry::PART_NUMBER_META,
						'type'         => 'text',
						'instructions' => __( 'Assigned by the Number Registry. Part numbers are uppercase and cannot be edited manually.', 'crmm-trim-catalog' ),
						'readonly'     => 1,
					),
					self::number_field( 'field_crmm_catalog_width', 'catalog_width', __( 'Catalog Width', 'crmm-trim-catalog' ) ),
					self::number_field( 'field_crmm_catalog_height', 'catalog_height', __( 'Catalog Height', 'crmm-trim-catalog' ) ),
					array(
						'key'   => 'field_crmm_profile_image',
						'label' => __( 'Profile Image', 'crmm-trim-catalog' ),
						'name'  => 'profile_image',
						'type'  => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
					array(
						'key'   => 'field_crmm_profile_render',
						'label' => __( 'Profile Render', 'crmm-trim-catalog' ),
						'name'  => 'profile_render',
						'type'  => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
					array(
						'key'   => 'field_crmm_isometric_view',
						'label' => __( 'Isometric View', 'crmm-trim-catalog' ),
						'name'  => 'isometric_view',
						'type'  => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
					array(
						'key'          => 'field_crmm_suggested_applications',
						'label'        => __( 'Suggested Applications', 'crmm-trim-catalog' ),
						'name'         => 'suggested_applications',
						'type'         => 'textarea',
						'rows'         => 4,
						'new_lines'    => 'wpautop',
					),
					self::file_field( 'field_crmm_pdf_download', 'pdf_download', __( 'Profile PDF', 'crmm-trim-catalog' ), 'pdf' ),
					self::file_field( 'field_crmm_dxf_download', 'dxf_download', __( 'DXF Download', 'crmm-trim-catalog' ), 'dxf' ),
					array(
						'key'   => 'field_crmm_download_revision',
						'label' => __( 'Download Revision', 'crmm-trim-catalog' ),
						'name'  => 'download_revision',
						'type'  => 'text',
					),
					array(
						'key'            => 'field_crmm_download_date',
						'label'          => __( 'Download Publication Date', 'crmm-trim-catalog' ),
						'name'           => 'download_date',
						'type'           => 'date_picker',
						'display_format' => 'F j, Y',
						'return_format'  => 'Y-m-d',
						'first_day'      => 0,
					),
				),
				'location'      => self::location(),
				'position'      => 'normal',
				'style'         => 'default',
				'label_placement' => 'top',
				'show_in_rest'  => 1,
			),
		);

		acf_add_local_field_group(
			array(
				'key'           => 'group_crmm_trim_shop_records',
				'title'         => __( 'Internal Shop Records', 'crmm-trim-catalog' ),
				'fields'        => array(
					self::text_field( 'field_crmm_old_crmm_number', 'old_crmm_number', __( 'Old CRMM Number', 'crmm-trim-catalog' ) ),
					self::text_field( 'field_crmm_source_original_part_number', 'source_original_part_number', __( 'Original Source Part Number', 'crmm-trim-catalog' ) ),
					self::status_field( 'field_crmm_template_status', 'template_status', __( 'Template Status', 'crmm-trim-catalog' ) ),
					self::status_field( 'field_crmm_knife_status', 'knife_status', __( 'Knife Status', 'crmm-trim-catalog' ) ),
					self::text_field( 'field_crmm_knife_storage_location', 'knife_storage_location', __( 'Knife Storage Location', 'crmm-trim-catalog' ) ),
					self::number_field( 'field_crmm_board_width', 'board_width', __( 'Board Width', 'crmm-trim-catalog' ) ),
					self::number_field( 'field_crmm_board_height', 'board_height', __( 'Board Height', 'crmm-trim-catalog' ) ),
					array(
						'key'          => 'field_crmm_onshape_url',
						'label'        => __( 'Onshape Document URL', 'crmm-trim-catalog' ),
						'name'         => 'onshape_url',
						'type'         => 'url',
						'instructions' => __( 'Internal source-document link. This URL is not exposed through public ACF REST data.', 'crmm-trim-catalog' ),
					),
					array(
						'key'      => 'field_crmm_internal_note',
						'label'    => __( 'Internal Notes', 'crmm-trim-catalog' ),
						'name'     => 'internal_note',
						'type'     => 'textarea',
						'rows'     => 5,
						'new_lines' => 'br',
					),
					array(
						'key'           => 'field_crmm_verification_status',
						'label'         => __( 'Verification Status', 'crmm-trim-catalog' ),
						'name'          => 'verification_status',
						'type'          => 'select',
						'choices'       => array(
							'not_reviewed' => __( 'Not reviewed', 'crmm-trim-catalog' ),
							'in_progress'  => __( 'In progress', 'crmm-trim-catalog' ),
							'verified'     => __( 'Verified', 'crmm-trim-catalog' ),
							'discrepancy'  => __( 'Discrepancy found', 'crmm-trim-catalog' ),
						),
						'default_value' => 'not_reviewed',
						'return_format' => 'value',
					),
					array(
						'key'           => 'field_crmm_verified_by',
						'label'         => __( 'Verified By', 'crmm-trim-catalog' ),
						'name'          => 'verified_by',
						'type'          => 'user',
						'role'          => array( 'administrator' ),
						'return_format' => 'id',
						'multiple'      => 0,
					),
					array(
						'key'            => 'field_crmm_verification_date',
						'label'          => __( 'Verification Date', 'crmm-trim-catalog' ),
						'name'           => 'verification_date',
						'type'           => 'date_picker',
						'display_format' => 'F j, Y',
						'return_format'  => 'Y-m-d',
						'first_day'      => 0,
					),
					array(
						'key'      => 'field_crmm_discrepancy_notes',
						'label'    => __( 'Discrepancy Notes', 'crmm-trim-catalog' ),
						'name'     => 'discrepancy_notes',
						'type'     => 'textarea',
						'rows'     => 5,
						'new_lines' => 'br',
					),
				),
				'location'      => self::location(),
				'position'      => 'normal',
				'style'         => 'default',
				'label_placement' => 'top',
				'show_in_rest'  => 0,
			),
		);
	}

	public static function protect_internal_field( array $field ): array|false {
		return current_user_can( CRMM_Trim_Capabilities::VIEW_INTERNAL ) ? $field : false;
	}

	public static function protect_internal_value( mixed $value, int|string $post_id, array $field ): mixed {
		if ( current_user_can( CRMM_Trim_Capabilities::VIEW_INTERNAL ) ) {
			return $value;
		}

		return get_post_meta( (int) $post_id, (string) $field['name'], true );
	}

	public static function lock_numbered_classification( array $field ): array {
		$post_id = self::current_post_id();
		if ( $post_id && CRMM_Trim_Number_Registry::for_post( $post_id ) ) {
			$field['disabled'] = 1;
			$field['instructions'] = trim( (string) ( $field['instructions'] ?? '' ) . ' ' . __( 'Classification is locked because a permanent part number has been assigned.', 'crmm-trim-catalog' ) );
		}
		return $field;
	}

	public static function protect_numbered_classification( mixed $value, int|string $post_id, array $field ): mixed {
		$post_id = (int) $post_id;
		if ( ! $post_id || ! CRMM_Trim_Number_Registry::for_post( $post_id ) ) {
			return $value;
		}
		$existing = get_post_meta( $post_id, (string) $field['name'], true );
		return '' !== (string) $existing ? $existing : $value;
	}

	public static function protect_registry_number( mixed $value, int|string $post_id, array $field ): string {
		return CRMM_Trim_Number_Registry::for_post( (int) $post_id );
	}

	public static function protect_approval_field( array $field ): array|false {
		return current_user_can( CRMM_Trim_Capabilities::APPROVE_PROFILES ) ? $field : false;
	}

	public static function protect_approval_value( mixed $value, int|string $post_id, array $field ): mixed {
		if ( current_user_can( CRMM_Trim_Capabilities::APPROVE_PROFILES ) ) {
			return $value;
		}
		return get_post_meta( (int) $post_id, (string) $field['name'], true );
	}

	public static function protect_verification_field( array $field ): array|false {
		return current_user_can( CRMM_Trim_Capabilities::VERIFY_PROFILES ) ? $field : false;
	}

	public static function stamp_verification( mixed $value, int|string $post_id, array $field ): mixed {
		$post_id = (int) $post_id;
		if ( ! $post_id || ! current_user_can( CRMM_Trim_Capabilities::VERIFY_PROFILES ) ) {
			return get_post_meta( $post_id, (string) $field['name'], true );
		}

		self::$previous_verification_status[ $post_id ] = (string) get_post_meta( $post_id, 'verification_status', true );
		if ( 'verified' === $value ) {
			update_post_meta( $post_id, 'verified_by', get_current_user_id() );
			update_post_meta( $post_id, '_verified_by', 'field_crmm_verified_by' );
			update_post_meta( $post_id, 'verification_date', current_time( 'Y-m-d' ) );
			update_post_meta( $post_id, '_verification_date', 'field_crmm_verification_date' );
			CRMM_Trim_Audit::record( $post_id, 'profile_verified' );
		}
		return $value;
	}

	public static function reset_verification_after_material_change( int|string $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! $post_id || CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$fingerprint = self::material_fingerprint( $post_id );
		$previous    = (string) get_post_meta( $post_id, '_crmm_verified_fingerprint', true );
		$status      = (string) get_post_meta( $post_id, 'verification_status', true );
		$was_status = self::$previous_verification_status[ $post_id ] ?? $status;

		if ( 'verified' === $status && 'verified' === $was_status && '' !== $previous && ! hash_equals( $previous, $fingerprint ) ) {
			update_post_meta( $post_id, 'verification_status', 'not_reviewed' );
			update_post_meta( $post_id, 'verified_by', '' );
			update_post_meta( $post_id, 'verification_date', '' );
			CRMM_Trim_Audit::record( $post_id, 'verification_reset_after_change' );
		}
		update_post_meta( $post_id, '_crmm_verified_fingerprint', $fingerprint );
	}

	public static function validate_subtype( mixed $valid, mixed $value, array $field, string $input ): mixed {
		if ( true !== $valid || ! $value || empty( $_POST['acf']['field_crmm_trim_category'] ) ) {
			return $valid;
		}

		$category_term_id = absint( wp_unslash( $_POST['acf']['field_crmm_trim_category'] ) );
		$category_code    = (string) get_term_meta( $category_term_id, 'crmm_category_code', true );

		if ( ! CRMM_Trim_Taxonomies::subtype_matches_category( absint( $value ), $category_code ) ) {
			return __( 'The selected profile type does not belong to the selected main category.', 'crmm-trim-catalog' );
		}

		return $valid;
	}

	private static function location(): array {
		return array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => CRMM_Trim_Post_Type::POST_TYPE,
				),
			),
		);
	}

	private static function taxonomy_field( string $key, string $name, string $label, string $taxonomy, bool $required ): array {
		return array(
			'key'           => $key,
			'label'         => $label,
			'name'          => $name,
			'type'          => 'taxonomy',
			'taxonomy'      => $taxonomy,
			'field_type'    => 'select',
			'add_term'      => 0,
			'save_terms'    => 1,
			'load_terms'    => 1,
			'return_format' => 'id',
			'multiple'      => 0,
			'required'      => $required ? 1 : 0,
		);
	}

	private static function number_field( string $key, string $name, string $label ): array {
		return array(
			'key'          => $key,
			'label'        => $label,
			'name'         => $name,
			'type'         => 'number',
			'append'       => 'in',
			'min'          => 0,
			'step'         => 0.0001,
		);
	}

	private static function text_field( string $key, string $name, string $label ): array {
		return array(
			'key'   => $key,
			'label' => $label,
			'name'  => $name,
			'type'  => 'text',
		);
	}

	private static function status_field( string $key, string $name, string $label ): array {
		return array(
			'key'           => $key,
			'label'         => $label,
			'name'          => $name,
			'type'          => 'select',
			'choices'       => array(
				'unknown' => __( 'Unknown', 'crmm-trim-catalog' ),
				'yes'     => __( 'Yes', 'crmm-trim-catalog' ),
				'no'      => __( 'No', 'crmm-trim-catalog' ),
			),
			'default_value' => 'unknown',
			'return_format' => 'value',
		);
	}

	private static function file_field( string $key, string $name, string $label, string $mime_types ): array {
		return array(
			'key'          => $key,
			'label'        => $label,
			'name'         => $name,
			'type'         => 'file',
			'return_format' => 'id',
			'library'      => 'all',
			'mime_types'   => $mime_types,
		);
	}

	private static function current_post_id(): int {
		global $post;
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
	}

	private static function material_fingerprint( int $post_id ): string {
		$values = array();
		foreach ( array( 'catalog_width', 'catalog_height', 'profile_image', 'profile_render', 'isometric_view', 'pdf_download', 'dxf_download', 'download_revision', 'download_date' ) as $meta_key ) {
			$values[ $meta_key ] = get_post_meta( $post_id, $meta_key, true );
		}
		$values['categories'] = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, array( 'fields' => 'ids' ) );
		$values['subtypes']   = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY, array( 'fields' => 'ids' ) );
		return hash( 'sha256', wp_json_encode( $values ) );
	}
}

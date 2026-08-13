<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Fields {
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
					self::taxonomy_field( 'field_crmm_trim_style', 'trim_style', __( 'Style', 'crmm-trim-catalog' ), CRMM_Trim_Taxonomies::STYLE_TAXONOMY, false ),
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
		return current_user_can( 'manage_options' ) ? $field : false;
	}

	public static function protect_internal_value( mixed $value, int|string $post_id, array $field ): mixed {
		if ( current_user_can( 'manage_options' ) ) {
			return $value;
		}

		return get_post_meta( (int) $post_id, (string) $field['name'], true );
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
}

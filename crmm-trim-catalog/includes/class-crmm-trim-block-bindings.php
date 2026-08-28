<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Block_Bindings {
	public const SOURCE = 'crmm-trim/catalog-field';

	private const FIELDS = array(
		'part_number' => array(
			'label' => 'Part Number',
			'meta'  => CRMM_Trim_Number_Registry::PART_NUMBER_META,
			'type'  => 'string',
		),
		'catalog_width' => array(
			'label' => 'Catalog Width',
			'meta'  => 'catalog_width',
			'type'  => 'string',
		),
		'catalog_height' => array(
			'label' => 'Catalog Height',
			'meta'  => 'catalog_height',
			'type'  => 'string',
		),
		'profile_image' => array(
			'label'  => 'Profile Image',
			'meta'   => 'profile_image',
			'type'   => 'string',
			'format' => 'attachment_url',
		),
		'profile_render' => array(
			'label'  => 'Profile Render',
			'meta'   => 'profile_render',
			'type'   => 'string',
			'format' => 'attachment_url',
		),
		'isometric_view' => array(
			'label'  => 'Isometric View',
			'meta'   => 'isometric_view',
			'type'   => 'string',
			'format' => 'attachment_url',
		),
		'suggested_applications' => array(
			'label' => 'Suggested Applications',
			'meta'  => 'suggested_applications',
			'type'  => 'string',
		),
		'pdf_download' => array(
			'label'  => 'Profile PDF',
			'meta'   => 'pdf_download',
			'type'   => 'string',
			'format' => 'attachment_url',
		),
		'dxf_download' => array(
			'label'  => 'DXF Download',
			'meta'   => 'dxf_download',
			'type'   => 'string',
			'format' => 'attachment_url',
		),
		'download_revision' => array(
			'label' => 'Download Revision',
			'meta'  => 'download_revision',
			'type'  => 'string',
		),
		'download_date' => array(
			'label' => 'Download Publication Date',
			'meta'  => 'download_date',
			'type'  => 'string',
		),
	);

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_source' ), 30 );
		add_action( 'init', array( __CLASS__, 'register_public_meta' ), 30 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	public static function register_source(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'CRMM Catalog Fields', 'crmm-trim-catalog' ),
				'get_value_callback' => array( __CLASS__, 'get_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	public static function register_public_meta(): void {
		foreach ( self::FIELDS as $field ) {
			register_post_meta(
				CRMM_Trim_Post_Type::POST_TYPE,
				$field['meta'],
				array(
					'single'       => true,
					'type'         => 'string',
					'show_in_rest' => true,
					'auth_callback' => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	public static function get_value( array $source_args, WP_Block $block_instance, string $attribute_name ): mixed {
		$field_key = isset( $source_args['field'] ) ? sanitize_key( (string) $source_args['field'] ) : '';
		if ( ! isset( self::FIELDS[ $field_key ] ) ) {
			return null;
		}

		$post_id = isset( $block_instance->context['postId'] ) ? absint( $block_instance->context['postId'] ) : 0;
		if ( ! $post_id || CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return null;
		}

		$field = self::FIELDS[ $field_key ];
		$value = get_post_meta( $post_id, $field['meta'], true );

		if ( isset( $field['format'] ) && 'attachment_url' === $field['format'] ) {
			$attachment_id = absint( $value );
			return $attachment_id ? ( wp_get_attachment_url( $attachment_id ) ?: null ) : null;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return null;
	}

	public static function enqueue_editor_assets(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		wp_enqueue_script(
			'crmm-trim-block-bindings',
			plugins_url( 'assets/js/block-bindings.js', CRMM_TRIM_CATALOG_FILE ),
			array( 'wp-blocks', 'wp-core-data', 'wp-data', 'wp-i18n' ),
			CRMM_TRIM_CATALOG_VERSION,
			true
		);

		wp_localize_script(
			'crmm-trim-block-bindings',
			'CRMMTrimBlockBindings',
			array(
				'source'   => self::SOURCE,
				'postType' => CRMM_Trim_Post_Type::POST_TYPE,
				'fields'   => self::editor_fields(),
			)
		);
	}

	private static function editor_fields(): array {
		$fields = array();

		foreach ( self::FIELDS as $key => $field ) {
			$fields[] = array(
				'key'    => $key,
				'label'  => __( $field['label'], 'crmm-trim-catalog' ),
				'type'   => $field['type'],
				'meta'   => $field['meta'],
				'format' => $field['format'] ?? 'text',
			);
		}

		return $fields;
	}
}

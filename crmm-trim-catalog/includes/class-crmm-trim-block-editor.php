<?php

defined( 'ABSPATH' ) || exit;

/**
 * Exposes intentionally public profile data to native WordPress block bindings.
 *
 * ACF Free remains the field-editing interface. Public mirror keys are used for
 * values that ACF stores as protected metadata, attachment IDs, or raw decimals.
 */
final class CRMM_Trim_Block_Editor {
	public const PART_NUMBER_META = 'crmm_part_number';

	private const ASSET_META = array(
		'profile_image' => array(
			'url' => 'profile_image_url',
			'alt' => 'profile_image_alt',
		),
		'profile_render' => array(
			'url' => 'profile_render_url',
			'alt' => 'profile_render_alt',
		),
		'isometric_view' => array(
			'url' => 'isometric_view_url',
			'alt' => 'isometric_view_alt',
		),
		'pdf_download' => array(
			'url' => 'pdf_download_url',
		),
		'dxf_download' => array(
			'url' => 'dxf_download_url',
		),
	);

	private const DIMENSION_META = array(
		'catalog_width'  => 'catalog_width_display',
		'catalog_height' => 'catalog_height_display',
	);

	private const GENERATED_META = array(
		self::PART_NUMBER_META,
		'catalog_width_display',
		'catalog_height_display',
		'profile_image_url',
		'profile_image_alt',
		'profile_render_url',
		'profile_render_alt',
		'isometric_view_url',
		'isometric_view_alt',
		'pdf_download_url',
		'dxf_download_url',
	);

	public static function register_meta(): void {
		$strings = array(
			self::PART_NUMBER_META    => __( 'CRMM Part Number', 'crmm-trim-catalog' ),
			'catalog_width_display'   => __( 'Catalog Width (formatted)', 'crmm-trim-catalog' ),
			'catalog_height_display'  => __( 'Catalog Height (formatted)', 'crmm-trim-catalog' ),
			'suggested_applications'  => __( 'Suggested Applications', 'crmm-trim-catalog' ),
			'download_revision'       => __( 'Download Revision', 'crmm-trim-catalog' ),
			'download_date'           => __( 'Download Publication Date', 'crmm-trim-catalog' ),
			'profile_image_url'       => __( 'Profile Image URL', 'crmm-trim-catalog' ),
			'profile_image_alt'       => __( 'Profile Image Alt Text', 'crmm-trim-catalog' ),
			'profile_render_url'      => __( 'Profile Render URL', 'crmm-trim-catalog' ),
			'profile_render_alt'      => __( 'Profile Render Alt Text', 'crmm-trim-catalog' ),
			'isometric_view_url'      => __( 'Isometric View URL', 'crmm-trim-catalog' ),
			'isometric_view_alt'      => __( 'Isometric View Alt Text', 'crmm-trim-catalog' ),
			'pdf_download_url'        => __( 'Profile PDF URL', 'crmm-trim-catalog' ),
			'dxf_download_url'        => __( 'DXF Download URL', 'crmm-trim-catalog' ),
		);

		foreach ( $strings as $meta_key => $label ) {
			register_post_meta(
				CRMM_Trim_Post_Type::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'string',
					'label'             => $label,
					'single'            => true,
					'show_in_rest'      => true,
					'revisions_enabled' => true,
					'sanitize_callback' => self::string_sanitizer( $meta_key ),
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
				)
			);
		}

		$asset_labels = array(
			'profile_image'   => __( 'Profile Image', 'crmm-trim-catalog' ),
			'profile_render'  => __( 'Profile Render', 'crmm-trim-catalog' ),
			'isometric_view'  => __( 'Isometric View', 'crmm-trim-catalog' ),
			'pdf_download'    => __( 'Profile PDF', 'crmm-trim-catalog' ),
			'dxf_download'    => __( 'DXF Download', 'crmm-trim-catalog' ),
		);

		foreach ( $asset_labels as $meta_key => $label ) {
			register_post_meta(
				CRMM_Trim_Post_Type::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'integer',
					'label'             => $label,
					'single'            => true,
					'show_in_rest'      => true,
					'revisions_enabled' => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
				)
			);
		}

		$dimension_labels = array(
			'catalog_width'  => __( 'Catalog Width (decimal inches)', 'crmm-trim-catalog' ),
			'catalog_height' => __( 'Catalog Height (decimal inches)', 'crmm-trim-catalog' ),
		);

		foreach ( $dimension_labels as $meta_key => $label ) {
			register_post_meta(
				CRMM_Trim_Post_Type::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'number',
					'label'             => $label,
					'single'            => true,
					'show_in_rest'      => true,
					'revisions_enabled' => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_dimension' ),
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
				)
			);
		}
	}

	public static function can_edit_meta( bool $allowed, string $meta_key, int $post_id ): bool {
		if ( in_array( $meta_key, self::GENERATED_META, true ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	public static function sanitize_dimension( mixed $value ): float {
		return is_numeric( $value ) ? max( 0, (float) $value ) : 0.0;
	}

	public static function sync_changed_meta( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		if ( CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if ( isset( self::ASSET_META[ $meta_key ] ) ) {
			self::sync_asset( $post_id, $meta_key, absint( $meta_value ) );
		}

		if ( isset( self::DIMENSION_META[ $meta_key ] ) ) {
			self::sync_dimension( $post_id, $meta_key, $meta_value );
		}
	}

	public static function backfill_public_meta(): void {
		$post_ids = get_posts(
			array(
				'post_type'      => CRMM_Trim_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $post_ids as $post_id ) {
			$part_number = CRMM_Trim_Number_Registry::for_post( (int) $post_id );
			if ( $part_number ) {
				update_post_meta( $post_id, self::PART_NUMBER_META, $part_number );
			}

			foreach ( array_keys( self::ASSET_META ) as $meta_key ) {
				self::sync_asset( (int) $post_id, $meta_key, absint( get_post_meta( $post_id, $meta_key, true ) ) );
			}

			foreach ( array_keys( self::DIMENSION_META ) as $meta_key ) {
				self::sync_dimension( (int) $post_id, $meta_key, get_post_meta( $post_id, $meta_key, true ) );
			}
		}
	}

	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		register_block_pattern_category(
			'crmm-trim-catalog',
			array( 'label' => __( 'CRMM Trim Catalog', 'crmm-trim-catalog' ) )
		);

		$patterns = array(
			'profile-hero' => array(
				'title'       => __( 'Trim Profile Hero', 'crmm-trim-catalog' ),
				'description' => __( 'Profile image, title, part number, type, and introductory copy.', 'crmm-trim-catalog' ),
				'content'     => self::hero_pattern(),
			),
			'profile-specifications' => array(
				'title'       => __( 'Trim Profile Specifications', 'crmm-trim-catalog' ),
				'description' => __( 'Bound width and height values with profile classifications.', 'crmm-trim-catalog' ),
				'content'     => self::specifications_pattern(),
			),
			'profile-visuals' => array(
				'title'       => __( 'Trim Profile Visuals', 'crmm-trim-catalog' ),
				'description' => __( 'Bound profile render and isometric view.', 'crmm-trim-catalog' ),
				'content'     => self::visuals_pattern(),
			),
			'profile-downloads' => array(
				'title'       => __( 'Trim Profile Downloads', 'crmm-trim-catalog' ),
				'description' => __( 'PDF and DXF download buttons with revision information.', 'crmm-trim-catalog' ),
				'content'     => self::downloads_pattern(),
			),
			'complete-profile' => array(
				'title'       => __( 'Complete Trim Profile Layout', 'crmm-trim-catalog' ),
				'description' => __( 'Full starter layout for the Single Trim Profile template.', 'crmm-trim-catalog' ),
				'content'     => self::complete_pattern(),
				'postTypes'   => array( 'wp_template' ),
			),
		);

		foreach ( $patterns as $slug => $properties ) {
			$post_types = $properties['postTypes'] ?? array( CRMM_Trim_Post_Type::POST_TYPE, 'wp_template' );
			unset( $properties['postTypes'] );

			register_block_pattern(
				'crmm-trim-catalog/' . $slug,
				array_merge(
					$properties,
					array(
						'categories'    => array( 'crmm-trim-catalog' ),
						'keywords'      => array( 'trim', 'moulding', 'profile', 'CRMM' ),
						'viewportWidth' => 1280,
						'postTypes'     => $post_types,
					)
				)
			);
		}
	}

	private static function sync_asset( int $post_id, string $source_key, int $attachment_id ): void {
		$definition = self::ASSET_META[ $source_key ];
		$url        = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

		update_post_meta( $post_id, $definition['url'], $url ? esc_url_raw( $url ) : '' );

		if ( isset( $definition['alt'] ) ) {
			$alt = $attachment_id ? (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : '';
			update_post_meta( $post_id, $definition['alt'], sanitize_text_field( $alt ) );
		}
	}

	private static function sync_dimension( int $post_id, string $source_key, mixed $value ): void {
		$display_key = self::DIMENSION_META[ $source_key ];
		$display     = is_numeric( $value ) ? self::format_inches( (float) $value ) : '';
		update_post_meta( $post_id, $display_key, $display );
	}

	private static function format_inches( float $value ): string {
		$rounded     = round( $value * 16 ) / 16;
		$whole       = (int) floor( $rounded );
		$sixteenths  = (int) round( ( $rounded - $whole ) * 16 );

		if ( 16 === $sixteenths ) {
			++$whole;
			$sixteenths = 0;
		}

		if ( 0 === $sixteenths ) {
			return $whole . '″';
		}

		$divisor     = self::greatest_common_divisor( $sixteenths, 16 );
		$numerator   = (int) ( $sixteenths / $divisor );
		$denominator = (int) ( 16 / $divisor );
		$fraction    = $numerator . '/' . $denominator;

		return ( $whole ? $whole . ' ' : '' ) . $fraction . '″';
	}

	private static function greatest_common_divisor( int $a, int $b ): int {
		while ( 0 !== $b ) {
			$remainder = $a % $b;
			$a         = $b;
			$b         = $remainder;
		}

		return max( 1, $a );
	}

	private static function string_sanitizer( string $meta_key ): callable {
		if ( str_ends_with( $meta_key, '_url' ) ) {
			return 'esc_url_raw';
		}

		if ( 'suggested_applications' === $meta_key ) {
			return 'sanitize_textarea_field';
		}

		return 'sanitize_text_field';
	}

	private static function binding( string $attribute, string $meta_key ): string {
		return wp_json_encode(
			array(
				'metadata' => array(
					'bindings' => array(
						$attribute => array(
							'source' => 'core/post-meta',
							'args'   => array( 'key' => $meta_key ),
						),
					),
				),
			),
			JSON_UNESCAPED_SLASHES
		);
	}

	private static function image_bindings( string $id_key, string $url_key, string $alt_key, string $class_name = '' ): string {
		return wp_json_encode(
			array(
				'className' => $class_name,
				'metadata'  => array(
					'bindings' => array(
						'id'  => array( 'source' => 'core/post-meta', 'args' => array( 'key' => $id_key ) ),
						'url' => array( 'source' => 'core/post-meta', 'args' => array( 'key' => $url_key ) ),
						'alt' => array( 'source' => 'core/post-meta', 'args' => array( 'key' => $alt_key ) ),
					),
				),
			),
			JSON_UNESCAPED_SLASHES
		);
	}

	private static function hero_pattern(): string {
		$image = self::image_bindings( 'profile_image', 'profile_image_url', 'profile_image_alt', 'crmm-profile-hero__image' );
		$part  = self::binding( 'content', self::PART_NUMBER_META );

		return <<<HTML
<!-- wp:group {"align":"full","className":"crmm-profile-hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull crmm-profile-hero"><!-- wp:columns {"verticalAlignment":"center","align":"wide"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"48%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:48%"><!-- wp:image {$image} -->
<figure class="wp-block-image crmm-profile-hero__image"><img src="" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center","width":"52%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:52%"><!-- wp:paragraph {"className":"crmm-profile-kicker"} -->
<p class="crmm-profile-kicker">Architectural moulding profile</p>
<!-- /wp:paragraph -->
<!-- wp:post-title {"level":1} /-->
<!-- wp:paragraph {$part} -->
<p>Part number</p>
<!-- /wp:paragraph -->
<!-- wp:post-excerpt {"moreText":""} /-->
<!-- wp:post-terms {"term":"crmm_trim_subtype","separator":" · ","className":"crmm-profile-type"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
HTML;
	}

	private static function specifications_pattern(): string {
		$width  = self::binding( 'content', 'catalog_width_display' );
		$height = self::binding( 'content', 'catalog_height_display' );
		$apps   = self::binding( 'content', 'suggested_applications' );

		return <<<HTML
<!-- wp:group {"align":"wide","className":"crmm-profile-specifications","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide crmm-profile-specifications"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Profile specifications</h2>
<!-- /wp:heading -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"small"} -->
<h3 class="wp-block-heading has-small-font-size">Width</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {$width} -->
<p>Width</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"small"} -->
<h3 class="wp-block-heading has-small-font-size">Height / projection</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {$height} -->
<p>Height</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"small"} -->
<h3 class="wp-block-heading has-small-font-size">Category</h3>
<!-- /wp:heading -->
<!-- wp:post-terms {"term":"crmm_trim_category"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"small"} -->
<h3 class="wp-block-heading has-small-font-size">Style</h3>
<!-- /wp:heading -->
<!-- wp:post-terms {"term":"crmm_trim_style"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Suggested applications</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {$apps} -->
<p>Suggested applications</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
HTML;
	}

	private static function visuals_pattern(): string {
		$render    = self::image_bindings( 'profile_render', 'profile_render_url', 'profile_render_alt', 'crmm-profile-render' );
		$isometric = self::image_bindings( 'isometric_view', 'isometric_view_url', 'isometric_view_alt', 'crmm-profile-isometric' );

		return <<<HTML
<!-- wp:group {"align":"wide","className":"crmm-profile-visuals","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide crmm-profile-visuals"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Profile drawings</h2>
<!-- /wp:heading -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {$render} -->
<figure class="wp-block-image crmm-profile-render"><img src="" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {$isometric} -->
<figure class="wp-block-image crmm-profile-isometric"><img src="" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
HTML;
	}

	private static function downloads_pattern(): string {
		$pdf      = self::binding( 'url', 'pdf_download_url' );
		$dxf      = self::binding( 'url', 'dxf_download_url' );
		$revision = self::binding( 'content', 'download_revision' );
		$date     = self::binding( 'content', 'download_date' );

		return <<<HTML
<!-- wp:group {"align":"wide","className":"crmm-profile-downloads","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide crmm-profile-downloads"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Downloads for specification</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Download the currently published reference files, then contact CRMM to confirm availability and project-specific requirements.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {$pdf} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Download profile PDF</a></div>
<!-- /wp:button -->
<!-- wp:button {$dxf} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Download DXF</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- wp:group {"className":"crmm-profile-download-meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group crmm-profile-download-meta"><!-- wp:paragraph -->
<p>Revision:</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {$revision} -->
<p>—</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Published:</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {$date} -->
<p>—</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
HTML;
	}

	private static function complete_pattern(): string {
		return self::hero_pattern()
			. "\n<!-- wp:group {\"tagName\":\"main\",\"align\":\"full\",\"className\":\"crmm-profile-main\",\"layout\":{\"type\":\"constrained\"}} -->\n<main class=\"wp-block-group alignfull crmm-profile-main\">"
			. "\n<!-- wp:post-content {\"align\":\"wide\",\"layout\":{\"type\":\"constrained\"}} /-->\n"
			. self::specifications_pattern()
			. "\n" . self::visuals_pattern()
			. "\n" . self::downloads_pattern()
			. "\n<!-- wp:group {\"align\":\"wide\",\"className\":\"crmm-profile-custom-cta\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group alignwide crmm-profile-custom-cta\"><!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">Need a variation—or something entirely custom?</h2>\n<!-- /wp:heading -->\n<!-- wp:paragraph -->\n<p>This catalog represents the kind of profiles we can manufacture, not the limits of our capabilities. Talk with our team about adapting this profile or developing a new one for your project.</p>\n<!-- /wp:paragraph -->\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\">Discuss your project</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:group -->\n</main>\n<!-- /wp:group -->";
	}
}

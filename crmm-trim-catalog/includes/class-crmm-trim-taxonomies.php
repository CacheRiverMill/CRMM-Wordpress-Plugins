<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Taxonomies {
	public const CATEGORY_TAXONOMY = 'crmm_trim_category';
	public const SUBTYPE_TAXONOMY  = 'crmm_trim_subtype';
	public const STYLE_TAXONOMY    = 'crmm_trim_style';

	private const CATEGORIES = array(
		'1' => 'Crown Moulding',
		'2' => 'Window & Door',
		'3' => 'Base Moulding',
		'4' => 'Chair Rail & Panel Moulding',
	);

	private const SUBTYPES = array(
		'BD' => array( 'name' => 'Bed Moulding', 'category' => '1' ),
		'CM' => array( 'name' => 'Crown Moulding', 'category' => '1' ),
		'CV' => array( 'name' => 'Cove Moulding', 'category' => '1' ),
		'NM' => array( 'name' => 'Neck Moulding', 'category' => '1' ),
		'PR' => array( 'name' => 'Picture Rail', 'category' => '1' ),
		'AG' => array( 'name' => 'T-Astragal', 'category' => '2' ),
		'BB' => array( 'name' => 'Back Band', 'category' => '2' ),
		'BK' => array( 'name' => 'Brick Moulding', 'category' => '2' ),
		'CG' => array( 'name' => 'Vertical Casing', 'category' => '2' ),
		'DC' => array( 'name' => 'Drip Cap', 'category' => '2' ),
		'DM' => array( 'name' => 'Detail Moulding', 'category' => '2' ),
		'DS' => array( 'name' => 'Door Stop', 'category' => '2' ),
		'OS' => array( 'name' => 'Outside Corner', 'category' => '2' ),
		'TC' => array( 'name' => 'Header & Top Cap', 'category' => '2' ),
		'TH' => array( 'name' => 'Threshold', 'category' => '2' ),
		'WS' => array( 'name' => 'Window Stool', 'category' => '2' ),
		'BM' => array( 'name' => 'Baseboard', 'category' => '3' ),
		'BS' => array( 'name' => 'Base Shoe', 'category' => '3' ),
		'QR' => array( 'name' => 'Quarter Round', 'category' => '3' ),
		'BT' => array( 'name' => 'Double Batten', 'category' => '4' ),
		'CR' => array( 'name' => 'Chair Rail', 'category' => '4' ),
		'FP' => array( 'name' => 'Panel Moulding', 'category' => '4' ),
		'RP' => array( 'name' => 'Recessed Panel', 'category' => '4' ),
		'TB' => array( 'name' => 'Tambour Panel', 'category' => '4' ),
		'TG' => array( 'name' => 'Tongue & Groove', 'category' => '4' ),
		'WC' => array( 'name' => 'Wainscot Cap', 'category' => '4' ),
	);

	public static function register(): void {
		register_taxonomy(
			self::CATEGORY_TAXONOMY,
			CRMM_Trim_Post_Type::POST_TYPE,
			array(
				'labels'            => self::labels( 'Category', 'Categories' ),
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => array( 'slug' => 'trim-category' ),
			),
		);

		register_taxonomy(
			self::SUBTYPE_TAXONOMY,
			CRMM_Trim_Post_Type::POST_TYPE,
			array(
				'labels'            => self::labels( 'Profile Type', 'Profile Types' ),
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => array( 'slug' => 'trim-type' ),
			),
		);

		register_taxonomy(
			self::STYLE_TAXONOMY,
			CRMM_Trim_Post_Type::POST_TYPE,
			array(
				'labels'            => self::labels( 'Style', 'Styles' ),
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => array( 'slug' => 'trim-style' ),
			),
		);
	}

	public static function seed_terms(): void {
		if ( ! taxonomy_exists( self::CATEGORY_TAXONOMY ) || ! taxonomy_exists( self::SUBTYPE_TAXONOMY ) ) {
			return;
		}
		if ( CRMM_TRIM_CATALOG_VERSION === get_option( 'crmm_trim_taxonomy_seed_version' ) ) {
			return;
		}

		foreach ( self::CATEGORIES as $code => $name ) {
			$term_id = self::ensure_term( self::CATEGORY_TAXONOMY, $name, 'category-' . $code );
			if ( $term_id ) {
				update_term_meta( $term_id, 'crmm_category_code', $code );
			}
		}

		foreach ( self::SUBTYPES as $code => $definition ) {
			$term_id = self::ensure_term( self::SUBTYPE_TAXONOMY, $definition['name'], strtolower( $code ) );
			if ( $term_id ) {
				update_term_meta( $term_id, 'crmm_subtype_code', $code );
				update_term_meta( $term_id, 'crmm_parent_category_code', $definition['category'] );
			}
		}

		update_option( 'crmm_trim_taxonomy_seed_version', CRMM_TRIM_CATALOG_VERSION, false );
	}

	public static function category_term_by_code( string $code ): ?WP_Term {
		return self::term_by_meta( self::CATEGORY_TAXONOMY, 'crmm_category_code', $code );
	}

	public static function subtype_term_by_code( string $code ): ?WP_Term {
		return self::term_by_meta( self::SUBTYPE_TAXONOMY, 'crmm_subtype_code', strtoupper( $code ) );
	}

	public static function subtype_matches_category( int $subtype_term_id, string $category_code ): bool {
		return (string) get_term_meta( $subtype_term_id, 'crmm_parent_category_code', true ) === $category_code;
	}

	private static function labels( string $singular, string $plural ): array {
		return array(
			'name'          => __( $plural, 'crmm-trim-catalog' ),
			'singular_name' => __( $singular, 'crmm-trim-catalog' ),
			'search_items'  => sprintf( __( 'Search %s', 'crmm-trim-catalog' ), $plural ),
			'all_items'     => sprintf( __( 'All %s', 'crmm-trim-catalog' ), $plural ),
			'edit_item'     => sprintf( __( 'Edit %s', 'crmm-trim-catalog' ), $singular ),
			'update_item'   => sprintf( __( 'Update %s', 'crmm-trim-catalog' ), $singular ),
			'add_new_item'  => sprintf( __( 'Add New %s', 'crmm-trim-catalog' ), $singular ),
			'new_item_name' => sprintf( __( 'New %s Name', 'crmm-trim-catalog' ), $singular ),
			'menu_name'     => __( $plural, 'crmm-trim-catalog' ),
		);
	}

	private static function ensure_term( string $taxonomy, string $name, string $slug ): int {
		$existing = get_term_by( 'slug', $slug, $taxonomy );
		if ( $existing instanceof WP_Term ) {
			return $existing->term_id;
		}

		$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	private static function term_by_meta( string $taxonomy, string $meta_key, string $value ): ?WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => $meta_key,
				'meta_value' => $value,
			)
		);

		return ! is_wp_error( $terms ) && isset( $terms[0] ) ? $terms[0] : null;
	}
}

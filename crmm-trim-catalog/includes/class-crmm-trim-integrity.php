<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Integrity {
	private static bool $repairing_terms = false;
	private static bool $repairing_title = false;

	public static function boot(): void {
		add_action( 'set_object_terms', array( __CLASS__, 'enforce_numbered_classification' ), 20, 6 );
		add_action( 'save_post_' . CRMM_Trim_Post_Type::POST_TYPE, array( __CLASS__, 'enforce_numbered_title' ), 40, 3 );
	}

	public static function validate( int $post_id ): true|WP_Error {
		$part_number = CRMM_Trim_Number_Registry::for_post( $post_id );
		if ( '' === $part_number ) {
			return new WP_Error( 'missing_part_number', __( 'A permanent part number has not been assigned.', 'crmm-trim-catalog' ) );
		}

		$parsed = CRMM_Trim_Number_Registry::parse( $part_number );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$category_terms = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY );
		$subtype_terms  = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY );
		if ( is_wp_error( $category_terms ) || 1 !== count( $category_terms ) || is_wp_error( $subtype_terms ) || 1 !== count( $subtype_terms ) ) {
			return new WP_Error( 'invalid_classification', __( 'Exactly one category and profile type are required.', 'crmm-trim-catalog' ) );
		}

		$category_code = (string) get_term_meta( $category_terms[0]->term_id, 'crmm_category_code', true );
		$subtype_code  = (string) get_term_meta( $subtype_terms[0]->term_id, 'crmm_subtype_code', true );
		if ( $parsed['category'] !== $category_code || $parsed['subcategory'] !== $subtype_code ) {
			return new WP_Error( 'classification_number_mismatch', __( 'The category or profile type does not match the permanent part number.', 'crmm-trim-catalog' ) );
		}

		return true;
	}

	public static function enforce_numbered_classification( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
		if ( self::$repairing_terms || CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}
		if ( ! in_array( $taxonomy, array( CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY ), true ) ) {
			return;
		}

		$parsed = CRMM_Trim_Number_Registry::parse( CRMM_Trim_Number_Registry::for_post( $object_id ) );
		if ( is_wp_error( $parsed ) ) {
			return;
		}

		$expected = CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY === $taxonomy
			? CRMM_Trim_Taxonomies::category_term_by_code( $parsed['category'] )
			: CRMM_Trim_Taxonomies::subtype_term_by_code( $parsed['subcategory'] );

		if ( ! $expected ) {
			return;
		}

		$current_ids = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_ids ) || array( $expected->term_id ) === array_map( 'intval', $current_ids ) ) {
			return;
		}

		self::$repairing_terms = true;
		wp_set_object_terms( $object_id, array( $expected->term_id ), $taxonomy, false );
		self::$repairing_terms = false;
		CRMM_Trim_Audit::record(
			$object_id,
			'classification_change_blocked',
			array( 'taxonomy' => $taxonomy, 'expected_term_id' => $expected->term_id )
		);
	}

	public static function enforce_numbered_title( int $post_id, WP_Post $post, bool $update ): void {
		if ( self::$repairing_title || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$part_number = CRMM_Trim_Number_Registry::for_post( $post_id );
		if ( '' === $part_number || $part_number === $post->post_title ) {
			return;
		}

		self::$repairing_title = true;
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $part_number ) );
		self::$repairing_title = false;
		CRMM_Trim_Audit::record( $post_id, 'numbered_title_restored', array( 'part_number' => $part_number ) );
	}

	public static function sync_title( int $post_id, string $part_number ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || $part_number === $post->post_title ) {
			return;
		}
		self::$repairing_title = true;
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $part_number ) );
		self::$repairing_title = false;
	}
}

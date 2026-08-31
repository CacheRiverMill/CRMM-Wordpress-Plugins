<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Number_Registry {
	public const PART_NUMBER_META     = '_crmm_part_number';
	public const CATEGORY_CODE_META   = '_crmm_category_code';
	public const SUBCATEGORY_CODE_META = '_crmm_subcategory_code';
	public const SEQUENCE_META        = '_crmm_sequence_number';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'crmm_trim_numbers';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			part_number varchar(64) NOT NULL,
			category_code varchar(8) NOT NULL,
			subcategory_code varchar(8) NOT NULL,
			sequence_number int(10) unsigned NOT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'assigned',
			assigned_by bigint(20) unsigned DEFAULT NULL,
			assigned_at datetime NOT NULL,
			source varchar(20) NOT NULL DEFAULT 'wordpress',
			PRIMARY KEY  (id),
			UNIQUE KEY part_number (part_number),
			UNIQUE KEY number_components (category_code, subcategory_code, sequence_number),
			KEY post_id (post_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'crmm_trim_catalog_db_version', CRMM_TRIM_CATALOG_VERSION );
	}

	public static function allocate( int $post_id, string $category_code, string $subcategory_code, int $user_id ): string|WP_Error {
		global $wpdb;

		if ( CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return new WP_Error( 'invalid_post', __( 'Part numbers can only be assigned to trim profiles.', 'crmm-trim-catalog' ) );
		}

		$existing = self::for_post( $post_id );
		if ( $existing ) {
			return $existing;
		}

		$category_code    = self::normalize_code( $category_code );
		$subcategory_code = self::normalize_code( $subcategory_code );

		if ( '' === $category_code || '' === $subcategory_code ) {
			return new WP_Error( 'missing_codes', __( 'A category and profile type are required before assigning a part number.', 'crmm-trim-catalog' ) );
		}

		$table     = self::table_name();
		$lock_name = substr( 'crmm_trim_' . $category_code . '_' . $subcategory_code, 0, 64 );
		$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );

		if ( 1 !== $locked ) {
			return new WP_Error( 'number_lock_failed', __( 'The number registry is busy. Please try again.', 'crmm-trim-catalog' ) );
		}

		$wpdb->query( 'START TRANSACTION' );

		try {
			$maximum = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(sequence_number) FROM {$table} WHERE category_code = %s AND subcategory_code = %s FOR UPDATE",
					$category_code,
					$subcategory_code
				)
			);
			$sequence    = max( 1000, $maximum + 1 );
			$part_number = self::format( $category_code, $subcategory_code, $sequence );

			$inserted = $wpdb->insert(
				$table,
				array(
					'part_number'      => $part_number,
					'category_code'    => $category_code,
					'subcategory_code' => $subcategory_code,
					'sequence_number'  => $sequence,
					'post_id'          => $post_id,
					'status'           => 'assigned',
					'assigned_by'      => $user_id,
					'assigned_at'      => current_time( 'mysql', true ),
					'source'           => 'wordpress',
				),
				array( '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
			);

			if ( false === $inserted ) {
				throw new RuntimeException( $wpdb->last_error ?: 'Unable to reserve the part number.' );
			}

			self::save_post_meta( $post_id, $part_number, $category_code, $subcategory_code, $sequence );
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
			return new WP_Error( 'number_assignment_failed', $exception->getMessage() );
		}

		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		CRMM_Trim_Audit::record(
			$post_id,
			'part_number_assigned',
			array( 'part_number' => $part_number, 'category_code' => $category_code, 'subtype_code' => $subcategory_code )
		);
		do_action( 'crmm_trim_number_assigned', $post_id, $part_number, $category_code, $subcategory_code, $user_id );
		return $part_number;
	}

	public static function preview_next( string $category_code, string $subcategory_code ): string {
		global $wpdb;
		$category_code    = self::normalize_code( $category_code );
		$subcategory_code = self::normalize_code( $subcategory_code );
		$maximum = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(sequence_number) FROM ' . self::table_name() . ' WHERE category_code = %s AND subcategory_code = %s',
				$category_code,
				$subcategory_code
			)
		);
		return self::format( $category_code, $subcategory_code, max( 1000, $maximum + 1 ) );
	}

	public static function reserve_imported( int $post_id, string $part_number, int $user_id = 0 ): true|WP_Error {
		global $wpdb;

		$part_number = self::normalize_part_number( $part_number );
		$parsed      = self::parse( $part_number );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE part_number = %s", $part_number ) );

		if ( $row ) {
			if ( (int) $row->post_id !== $post_id ) {
				return new WP_Error( 'duplicate_part_number', sprintf( __( '%s is already assigned to another profile.', 'crmm-trim-catalog' ), $part_number ) );
			}

			self::save_post_meta( $post_id, $part_number, $parsed['category'], $parsed['subcategory'], $parsed['sequence'] );
			return true;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'part_number'      => $part_number,
				'category_code'    => $parsed['category'],
				'subcategory_code' => $parsed['subcategory'],
				'sequence_number'  => $parsed['sequence'],
				'post_id'          => $post_id,
				'status'           => 'assigned',
				'assigned_by'      => $user_id ?: null,
				'assigned_at'      => current_time( 'mysql', true ),
				'source'           => 'legacy',
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'number_import_failed', $wpdb->last_error ?: __( 'Unable to reserve imported part number.', 'crmm-trim-catalog' ) );
		}

		self::save_post_meta( $post_id, $part_number, $parsed['category'], $parsed['subcategory'], $parsed['sequence'] );
		return true;
	}

	public static function for_post( int $post_id ): string {
		return self::normalize_part_number( (string) get_post_meta( $post_id, self::PART_NUMBER_META, true ) );
	}

	public static function normalize_part_number( string $part_number ): string {
		return strtoupper( trim( sanitize_text_field( $part_number ) ) );
	}

	public static function parse( string $part_number ): array|WP_Error {
		$part_number = self::normalize_part_number( $part_number );
		if ( ! preg_match( '/^([0-9]+)-([A-Z0-9]+)-([0-9]{4,})$/', $part_number, $matches ) ) {
			return new WP_Error( 'invalid_part_number', sprintf( __( '%s is not a valid CRMM part number.', 'crmm-trim-catalog' ), $part_number ) );
		}

		return array(
			'category'    => $matches[1],
			'subcategory' => $matches[2],
			'sequence'    => (int) $matches[3],
		);
	}

	public static function void_for_post( int $post_id ): void {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'status' => 'void' ),
			array( 'post_id' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	private static function format( string $category_code, string $subcategory_code, int $sequence ): string {
		return strtoupper( sprintf( '%s-%s-%04d', $category_code, $subcategory_code, $sequence ) );
	}

	private static function normalize_code( string $code ): string {
		return strtoupper( preg_replace( '/[^A-Z0-9]/i', '', trim( $code ) ) );
	}

	private static function save_post_meta( int $post_id, string $part_number, string $category, string $subcategory, int $sequence ): void {
		update_post_meta( $post_id, self::PART_NUMBER_META, $part_number );
		update_post_meta( $post_id, '_' . self::PART_NUMBER_META, 'field_crmm_part_number' );
		update_post_meta( $post_id, self::CATEGORY_CODE_META, $category );
		update_post_meta( $post_id, self::SUBCATEGORY_CODE_META, $subcategory );
		update_post_meta( $post_id, self::SEQUENCE_META, $sequence );
	}
}

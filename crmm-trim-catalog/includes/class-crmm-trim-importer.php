<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Importer {
	private const LEGACY_ID_META = '_crmm_legacy_profile_id';

	private const REQUIRED_COLUMNS = array(
		'id',
		'part_number',
		'category_code',
		'subcategory_code',
		'main_category',
		'subtype',
		'seq_num',
	);

	public static function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . CRMM_Trim_Post_Type::POST_TYPE,
			__( 'Import Legacy Profiles', 'crmm-trim-catalog' ),
			__( 'Import Legacy CSV', 'crmm-trim-catalog' ),
			'manage_options',
			'crmm-trim-import',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary = get_transient( 'crmm_trim_import_' . get_current_user_id() );
		if ( $summary ) {
			delete_transient( 'crmm_trim_import_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Legacy Trim Profiles', 'crmm-trim-catalog' ); ?></h1>
			<p><?php esc_html_e( 'Imports or updates profiles by legacy ID. Every imported profile remains a draft until reviewed and intentionally published.', 'crmm-trim-catalog' ); ?></p>
			<p><?php esc_html_e( 'Part numbers are normalized to uppercase and reserved permanently. Existing sequence gaps are preserved and will not be reused.', 'crmm-trim-catalog' ); ?></p>

			<?php if ( is_array( $summary ) ) : ?>
				<div class="notice notice-<?php echo empty( $summary['errors'] ) ? 'success' : 'warning'; ?>">
					<p>
						<?php
						echo esc_html(
							sprintf(
								__( 'Import complete: %1$d created, %2$d updated, %3$d skipped, %4$d errors.', 'crmm-trim-catalog' ),
								$summary['created'],
								$summary['updated'],
								$summary['skipped'],
								count( $summary['errors'] )
							)
						);
						?>
					</p>
					<?php if ( ! empty( $summary['errors'] ) ) : ?>
						<ul>
							<?php foreach ( array_slice( $summary['errors'], 0, 50 ) as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="crmm_trim_import">
				<?php wp_nonce_field( 'crmm_trim_import' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="crmm-trim-csv"><?php esc_html_e( 'Trim profile CSV', 'crmm-trim-catalog' ); ?></label></th>
						<td><input required type="file" id="crmm-trim-csv" name="trim_csv" accept=".csv,text/csv"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Images', 'crmm-trim-catalog' ); ?></th>
						<td>
							<label><input type="checkbox" name="download_images" value="1"> <?php esc_html_e( 'Download legacy profile images into the Media Library', 'crmm-trim-catalog' ); ?></label>
							<p class="description"><?php esc_html_e( 'Leave this off for the first data-only import. Legacy URLs are retained internally even when files are not downloaded.', 'crmm-trim-catalog' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Import Profiles', 'crmm-trim-catalog' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_upload(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to import trim profiles.', 'crmm-trim-catalog' ),
				esc_html__( 'Forbidden', 'crmm-trim-catalog' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'crmm_trim_import' );

		if ( empty( $_FILES['trim_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['trim_csv']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Choose a valid CSV file.', 'crmm-trim-catalog' ), '', array( 'response' => 400 ) );
		}

		$result = self::import( $_FILES['trim_csv']['tmp_name'], ! empty( $_POST['download_images'] ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		set_transient( 'crmm_trim_import_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CRMM_Trim_Post_Type::POST_TYPE . '&page=crmm-trim-import' ) );
		exit;
	}

	public static function import( string $path, bool $download_images = false ): array|WP_Error {
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'csv_open_failed', __( 'The CSV could not be opened.', 'crmm-trim-catalog' ) );
		}

		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			return new WP_Error( 'csv_empty', __( 'The CSV has no header row.', 'crmm-trim-catalog' ) );
		}

		$headers = array_map( static fn( $header ) => trim( (string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B\"" ), $headers );
		$missing = array_diff( self::REQUIRED_COLUMNS, $headers );
		if ( $missing ) {
			fclose( $handle );
			return new WP_Error( 'csv_missing_columns', sprintf( __( 'Missing required columns: %s', 'crmm-trim-catalog' ), implode( ', ', $missing ) ) );
		}

		$summary = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);
		$line = 1;

		while ( ( $values = fgetcsv( $handle ) ) !== false ) {
			++$line;
			if ( 1 === count( $values ) && '' === trim( (string) $values[0] ) ) {
				++$summary['skipped'];
				continue;
			}

			if ( count( $values ) !== count( $headers ) ) {
				$summary['errors'][] = sprintf( __( 'Line %d: column count does not match the header.', 'crmm-trim-catalog' ), $line );
				continue;
			}

			$row    = array_combine( $headers, $values );
			$result = self::import_row( $row, $download_images );

			if ( is_wp_error( $result ) ) {
				$summary['errors'][] = sprintf( __( 'Line %1$d (%2$s): %3$s', 'crmm-trim-catalog' ), $line, $row['part_number'] ?: 'unknown', $result->get_error_message() );
			} else {
				++$summary[ $result ];
			}
		}

		fclose( $handle );
		return $summary;
	}

	private static function import_row( array $row, bool $download_images ): string|WP_Error {
		$legacy_id   = absint( $row['id'] );
		$part_number = CRMM_Trim_Number_Registry::normalize_part_number( (string) $row['part_number'] );
		$parsed      = CRMM_Trim_Number_Registry::parse( $part_number );

		if ( ! $legacy_id || is_wp_error( $parsed ) ) {
			return is_wp_error( $parsed ) ? $parsed : new WP_Error( 'missing_legacy_id', __( 'The legacy ID is missing.', 'crmm-trim-catalog' ) );
		}

		if ( strtoupper( trim( (string) $row['category_code'] ) ) !== $parsed['category'] || strtoupper( trim( (string) $row['subcategory_code'] ) ) !== $parsed['subcategory'] ) {
			return new WP_Error( 'number_components_mismatch', __( 'The part number does not match its category and subcategory columns.', 'crmm-trim-catalog' ) );
		}

		$post_id = self::find_existing( $legacy_id, $part_number );
		$created = false;

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => CRMM_Trim_Post_Type::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => $part_number,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			$created = true;
		}

		$reserved = CRMM_Trim_Number_Registry::reserve_imported( $post_id, $part_number, get_current_user_id() );
		if ( is_wp_error( $reserved ) ) {
			if ( $created ) {
				wp_delete_post( $post_id, true );
			}
			return $reserved;
		}

		update_post_meta( $post_id, self::LEGACY_ID_META, $legacy_id );
		self::set_legacy_timestamp( $post_id, '_crmm_legacy_created_at', $row['created_at'] ?? '' );
		self::set_legacy_timestamp( $post_id, '_crmm_legacy_updated_at', $row['updated_at'] ?? '' );
		self::assign_terms( $post_id, $parsed['category'], $parsed['subcategory'], (string) ( $row['style'] ?? '' ) );

		self::set_if_present( $post_id, 'catalog_width', $row['catalog_width'] ?? '', 'decimal' );
		self::set_if_present( $post_id, 'catalog_height', $row['catalog_height'] ?? '', 'decimal' );
		self::set_if_present( $post_id, 'old_crmm_number', $row['old_crmm_number'] ?? '' );
		self::set_if_present( $post_id, 'source_original_part_number', $row['source_original_part_number'] ?? '' );
		self::set_if_present( $post_id, 'template_status', $row['template_status'] ?? '', 'status' );
		self::set_if_present( $post_id, 'knife_status', $row['knife_status'] ?? '', 'status' );
		self::set_if_present( $post_id, 'knife_storage_location', $row['knife_storage_location'] ?? '' );
		self::set_if_present( $post_id, 'board_width', $row['board_width'] ?? '', 'decimal' );
		self::set_if_present( $post_id, 'board_height', $row['board_height'] ?? '', 'decimal' );
		self::set_if_present( $post_id, 'internal_note', $row['note'] ?? '', 'textarea' );

		if ( absint( $row['seq_num'] ) !== $parsed['sequence'] ) {
			self::update_field( 'field_crmm_verification_status', 'verification_status', 'discrepancy', $post_id );
			self::update_field(
				'field_crmm_discrepancy_notes',
				'discrepancy_notes',
				sprintf(
					__( 'Legacy import: seq_num %1$d did not match the assigned part-number sequence %2$d. The part number was preserved as canonical.', 'crmm-trim-catalog' ),
					absint( $row['seq_num'] ),
					$parsed['sequence']
				),
				$post_id
			);
		}

		$marketing = self::normalize_boolean( (string) ( $row['marketing_catalog_v2'] ?? '' ) );
		if ( null !== $marketing ) {
			self::update_field( 'field_crmm_marketing_catalog', 'marketing_catalog', $marketing ? 1 : 0, $post_id );
		}

		foreach ( array( 'profile_image', 'profile_render', 'isometric_view' ) as $image_field ) {
			self::import_image_field( $post_id, $image_field, (string) ( $row[ $image_field ] ?? '' ), $download_images );
		}

		return $created ? 'created' : 'updated';
	}

	private static function find_existing( int $legacy_id, string $part_number ): int {
		$posts = get_posts(
			array(
				'post_type'      => CRMM_Trim_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::LEGACY_ID_META,
				'meta_value'     => $legacy_id,
			)
		);

		if ( $posts ) {
			return (int) $posts[0];
		}

		$posts = get_posts(
			array(
				'post_type'      => CRMM_Trim_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => CRMM_Trim_Number_Registry::PART_NUMBER_META,
				'meta_value'     => $part_number,
			)
		);

		return $posts ? (int) $posts[0] : 0;
	}

	private static function assign_terms( int $post_id, string $category_code, string $subcategory_code, string $style ): void {
		$category = CRMM_Trim_Taxonomies::category_term_by_code( $category_code );
		$subtype  = CRMM_Trim_Taxonomies::subtype_term_by_code( $subcategory_code );

		if ( $category ) {
			wp_set_object_terms( $post_id, array( $category->term_id ), CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, false );
		}
		if ( $subtype ) {
			wp_set_object_terms( $post_id, array( $subtype->term_id ), CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY, false );
		}

		$style = trim( $style );
		if ( '' !== $style ) {
			$result = wp_set_object_terms( $post_id, array( $style ), CRMM_Trim_Taxonomies::STYLE_TAXONOMY, false );
			if ( ! is_wp_error( $result ) ) {
				$style_term = get_term_by( 'term_taxonomy_id', (int) $result[0], CRMM_Trim_Taxonomies::STYLE_TAXONOMY );
				if ( $style_term instanceof WP_Term ) {
					self::update_field( 'field_crmm_trim_style', 'trim_style', $style_term->term_id, $post_id );
				}
			}
		}

		if ( $category ) {
			self::update_field( 'field_crmm_trim_category', 'trim_category', $category->term_id, $post_id );
		}
		if ( $subtype ) {
			self::update_field( 'field_crmm_trim_subtype', 'trim_subtype', $subtype->term_id, $post_id );
		}
	}

	private static function set_if_present( int $post_id, string $name, mixed $value, string $type = 'text' ): void {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			return;
		}

		if ( 'status' === $type ) {
			$boolean = self::normalize_boolean( (string) $value );
			if ( null === $boolean ) {
				return;
			}
			$value = $boolean ? 'yes' : 'no';
		} elseif ( 'decimal' === $type ) {
			if ( ! is_numeric( $value ) ) {
				return;
			}
			$value = (float) $value;
		} elseif ( 'textarea' === $type ) {
			$value = sanitize_textarea_field( (string) $value );
		} else {
			$value = sanitize_text_field( (string) $value );
		}

		self::update_field( 'field_crmm_' . $name, $name, $value, $post_id );
	}

	private static function normalize_boolean( string $value ): ?bool {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return null;
		}
		if ( in_array( $value, array( '1', 'true', 'yes', 'y' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'false', 'no', 'n' ), true ) ) {
			return false;
		}
		return null;
	}

	private static function set_legacy_timestamp( int $post_id, string $meta_key, mixed $value ): void {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return;
		}

		$timestamp = strtotime( $value );
		if ( false !== $timestamp ) {
			update_post_meta( $post_id, $meta_key, gmdate( 'Y-m-d H:i:s', $timestamp ) );
		}
	}

	private static function import_image_field( int $post_id, string $field_name, string $url, bool $download ): void {
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url ) {
			return;
		}

		update_post_meta( $post_id, '_crmm_legacy_' . $field_name . '_url', $url );
		if ( ! $download || get_post_meta( $post_id, $field_name, true ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			self::update_field( 'field_crmm_' . $field_name, $field_name, $attachment_id, $post_id );
		}
	}

	private static function update_field( string $field_key, string $meta_key, mixed $value, int $post_id ): void {
		if ( function_exists( 'update_field' ) ) {
			update_field( $field_key, $value, $post_id );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}
}

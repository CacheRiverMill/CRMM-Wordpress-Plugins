<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Admin_List {
	private const META_FILTERS = array(
		'crmm_template_status'     => 'template_status',
		'crmm_knife_status'        => 'knife_status',
		'crmm_marketing_catalog'   => 'marketing_catalog',
		'crmm_verification_status' => 'verification_status',
		'crmm_number_state'        => CRMM_Trim_Number_Registry::PART_NUMBER_META,
	);

	public static function boot(): void {
		add_filter( 'manage_' . CRMM_Trim_Post_Type::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . CRMM_Trim_Post_Type::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . CRMM_Trim_Post_Type::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_query' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'part_number_order' ), 20, 2 );
		add_filter( 'posts_search', array( __CLASS__, 'extend_search' ), 20, 2 );
		add_filter( 'views_edit-' . CRMM_Trim_Post_Type::POST_TYPE, array( __CLASS__, 'queue_views' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function columns( array $columns ): array {
		return array(
			'cb'                => $columns['cb'] ?? '<input type="checkbox">',
			'crmm_profile_image' => __( 'Profile', 'crmm-trim-catalog' ),
			'crmm_render'        => __( 'Render', 'crmm-trim-catalog' ),
			'title'              => __( 'Title', 'crmm-trim-catalog' ),
			'crmm_part_number'   => __( 'Part Number', 'crmm-trim-catalog' ),
			'crmm_classification' => __( 'Category / Type', 'crmm-trim-catalog' ),
			'crmm_dimensions'    => __( 'Dimensions', 'crmm-trim-catalog' ),
			'crmm_shop_status'   => __( 'Template / Knife', 'crmm-trim-catalog' ),
			'crmm_workflow'      => __( 'Approval / Verification', 'crmm-trim-catalog' ),
			'crmm_readiness'     => __( 'Readiness', 'crmm-trim-catalog' ),
			'crmm_modified'      => __( 'Modified', 'crmm-trim-catalog' ),
		);
	}

	public static function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'crmm_profile_image':
				self::render_attachment( $post_id, 'profile_image' );
				break;
			case 'crmm_render':
				self::render_attachment( $post_id, 'profile_render' );
				break;
			case 'crmm_part_number':
				echo '<strong>' . esc_html( CRMM_Trim_Number_Registry::for_post( $post_id ) ?: '—' ) . '</strong>';
				break;
			case 'crmm_classification':
				$category = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, array( 'fields' => 'names' ) );
				$subtype  = wp_get_post_terms( $post_id, CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY, array( 'fields' => 'names' ) );
				echo esc_html( ( ! is_wp_error( $category ) && $category ? $category[0] : '—' ) . ' / ' . ( ! is_wp_error( $subtype ) && $subtype ? $subtype[0] : '—' ) );
				break;
			case 'crmm_dimensions':
				$width  = get_post_meta( $post_id, 'catalog_width', true );
				$height = get_post_meta( $post_id, 'catalog_height', true );
				echo esc_html( '' !== (string) $width && '' !== (string) $height ? $width . ' × ' . $height . ' in' : '—' );
				break;
			case 'crmm_shop_status':
				if ( current_user_can( CRMM_Trim_Capabilities::VIEW_INTERNAL ) ) {
					echo esc_html( self::label( get_post_meta( $post_id, 'template_status', true ) ) . ' / ' . self::label( get_post_meta( $post_id, 'knife_status', true ) ) );
				} else {
					echo '—';
				}
				break;
			case 'crmm_workflow':
				$approved = (bool) get_post_meta( $post_id, 'marketing_catalog', true );
				$verified = self::label( get_post_meta( $post_id, 'verification_status', true ) );
				printf( '<span class="crmm-badge %1$s">%2$s</span><br><span class="description">%3$s</span>', $approved ? 'is-ready' : 'is-blocked', esc_html( $approved ? __( 'Approved', 'crmm-trim-catalog' ) : __( 'Not approved', 'crmm-trim-catalog' ) ), esc_html( $verified ) );
				break;
			case 'crmm_readiness':
				$readiness = CRMM_Trim_Readiness::evaluate( $post_id );
				$details = array_merge( $readiness['blockers'], $readiness['warnings'] );
				printf( '<span class="crmm-badge %1$s" title="%2$s">%3$s</span>', $readiness['ready'] ? 'is-ready' : 'is-blocked', esc_attr( implode( ' ', $details ) ), esc_html( $readiness['ready'] ? __( 'Ready', 'crmm-trim-catalog' ) : sprintf( __( '%d blockers', 'crmm-trim-catalog' ), count( $readiness['blockers'] ) ) ) );
				break;
			case 'crmm_modified':
				echo esc_html( get_the_modified_date( '', $post_id ) );
				break;
		}
	}

	public static function sortable_columns( array $columns ): array {
		$columns['crmm_part_number'] = 'crmm_part_number';
		$columns['crmm_dimensions']  = 'crmm_width';
		$columns['crmm_workflow']    = 'crmm_verification';
		$columns['crmm_modified']    = 'modified';
		return $columns;
	}

	public static function filters( string $post_type ): void {
		if ( CRMM_Trim_Post_Type::POST_TYPE !== $post_type ) {
			return;
		}

		self::taxonomy_dropdown( CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY, __( 'All categories', 'crmm-trim-catalog' ) );
		self::taxonomy_dropdown( CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY, __( 'All profile types', 'crmm-trim-catalog' ) );
		self::select_filter( 'crmm_template_status', __( 'All template states', 'crmm-trim-catalog' ), array( 'yes' => __( 'Template: Yes', 'crmm-trim-catalog' ), 'no' => __( 'Template: No', 'crmm-trim-catalog' ), 'unknown' => __( 'Template: Unknown', 'crmm-trim-catalog' ) ) );
		self::select_filter( 'crmm_marketing_catalog', __( 'All approval states', 'crmm-trim-catalog' ), array( '1' => __( 'Marketing approved', 'crmm-trim-catalog' ), '0' => __( 'Not marketing approved', 'crmm-trim-catalog' ) ) );
		self::select_filter( 'crmm_verification_status', __( 'All verification states', 'crmm-trim-catalog' ), array( 'not_reviewed' => __( 'Not reviewed', 'crmm-trim-catalog' ), 'in_progress' => __( 'In progress', 'crmm-trim-catalog' ), 'verified' => __( 'Verified', 'crmm-trim-catalog' ), 'discrepancy' => __( 'Discrepancy', 'crmm-trim-catalog' ) ) );
		self::select_filter( 'crmm_number_state', __( 'All number states', 'crmm-trim-catalog' ), array( 'numbered' => __( 'Numbered', 'crmm-trim-catalog' ), 'unnumbered' => __( 'Unnumbered', 'crmm-trim-catalog' ) ) );
	}

	public static function apply_query( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || CRMM_Trim_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'crmm_part_number' === $orderby ) {
			$query->set( 'crmm_order_part_number', 1 );
			$query->set( 'orderby', 'none' );
		} elseif ( 'crmm_width' === $orderby ) {
			$query->set( 'meta_key', 'catalog_width' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'crmm_verification' === $orderby ) {
			$query->set( 'meta_key', 'verification_status' );
			$query->set( 'orderby', 'meta_value' );
		}

		$meta_query = array( 'relation' => 'AND' );
		foreach ( self::META_FILTERS as $request_key => $meta_key ) {
			if ( ! isset( $_GET[ $request_key ] ) || '' === (string) $_GET[ $request_key ] ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_GET[ $request_key ] ) );
			if ( 'crmm_number_state' === $request_key ) {
				$meta_query[] = 'numbered' === $value
					? array( 'key' => $meta_key, 'compare' => 'EXISTS' )
					: array( 'key' => $meta_key, 'compare' => 'NOT EXISTS' );
			} else {
				$meta_query[] = array( 'key' => $meta_key, 'value' => $value );
			}
		}

		$queue = isset( $_GET['crmm_queue'] ) ? sanitize_key( wp_unslash( $_GET['crmm_queue'] ) ) : '';
		if ( 'needs_number' === $queue ) {
			$meta_query[] = array( 'relation' => 'OR', array( 'key' => CRMM_Trim_Number_Registry::PART_NUMBER_META, 'compare' => 'NOT EXISTS' ), array( 'key' => CRMM_Trim_Number_Registry::PART_NUMBER_META, 'value' => '' ) );
		} elseif ( 'needs_images' === $queue ) {
			$meta_query[] = array( 'relation' => 'OR', array( 'key' => 'profile_image', 'compare' => 'NOT EXISTS' ), array( 'key' => 'profile_image', 'value' => '' ) );
		} elseif ( 'needs_verification' === $queue ) {
			$meta_query[] = array( 'relation' => 'OR', array( 'key' => 'verification_status', 'compare' => 'NOT EXISTS' ), array( 'key' => 'verification_status', 'value' => 'verified', 'compare' => '!=' ) );
		} elseif ( 'discrepancies' === $queue ) {
			$meta_query[] = array( 'key' => 'verification_status', 'value' => 'discrepancy' );
		} elseif ( 'ready' === $queue ) {
			$meta_query[] = array( 'key' => 'marketing_catalog', 'value' => '1' );
			$meta_query[] = array( 'key' => 'verification_status', 'value' => 'verified' );
			$meta_query[] = array( 'key' => CRMM_Trim_Number_Registry::PART_NUMBER_META, 'compare' => 'EXISTS' );
		}

		if ( count( $meta_query ) > 1 ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	public static function part_number_order( array $clauses, WP_Query $query ): array {
		global $wpdb;
		if ( ! is_admin() || ! $query->is_main_query() || ! $query->get( 'crmm_order_part_number' ) ) {
			return $clauses;
		}

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} crmm_category_order ON crmm_category_order.post_id = {$wpdb->posts}.ID AND crmm_category_order.meta_key = %s LEFT JOIN {$wpdb->postmeta} crmm_subtype_order ON crmm_subtype_order.post_id = {$wpdb->posts}.ID AND crmm_subtype_order.meta_key = %s LEFT JOIN {$wpdb->postmeta} crmm_sequence_order ON crmm_sequence_order.post_id = {$wpdb->posts}.ID AND crmm_sequence_order.meta_key = %s",
			CRMM_Trim_Number_Registry::CATEGORY_CODE_META,
			CRMM_Trim_Number_Registry::SUBCATEGORY_CODE_META,
			CRMM_Trim_Number_Registry::SEQUENCE_META
		);
		$direction = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';
		$clauses['orderby'] = "CAST(crmm_category_order.meta_value AS UNSIGNED) {$direction}, crmm_subtype_order.meta_value {$direction}, CAST(crmm_sequence_order.meta_value AS UNSIGNED) {$direction}";
		return $clauses;
	}

	public static function extend_search( string $search_sql, WP_Query $query ): string {
		global $wpdb;
		if ( ! is_admin() || ! $query->is_main_query() || CRMM_Trim_Post_Type::POST_TYPE !== $query->get( 'post_type' ) || ! $query->get( 's' ) ) {
			return $search_sql;
		}

		$like = '%' . $wpdb->esc_like( (string) $query->get( 's' ) ) . '%';
		return $wpdb->prepare(
			" AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} crmm_search_meta WHERE crmm_search_meta.post_id = {$wpdb->posts}.ID AND crmm_search_meta.meta_key IN (%s, %s) AND crmm_search_meta.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} crmm_tr INNER JOIN {$wpdb->term_taxonomy} crmm_tt ON crmm_tt.term_taxonomy_id = crmm_tr.term_taxonomy_id INNER JOIN {$wpdb->terms} crmm_t ON crmm_t.term_id = crmm_tt.term_id WHERE crmm_tr.object_id = {$wpdb->posts}.ID AND crmm_tt.taxonomy IN (%s, %s, %s) AND crmm_t.name LIKE %s))",
			$like,
			CRMM_Trim_Number_Registry::PART_NUMBER_META,
			'old_crmm_number',
			$like,
			CRMM_Trim_Taxonomies::CATEGORY_TAXONOMY,
			CRMM_Trim_Taxonomies::SUBTYPE_TAXONOMY,
			CRMM_Trim_Taxonomies::STYLE_TAXONOMY,
			$like
		);
	}

	public static function queue_views( array $views ): array {
		$base = admin_url( 'edit.php?post_type=' . CRMM_Trim_Post_Type::POST_TYPE );
		$queues = array(
			'needs_number'       => __( 'Needs Number', 'crmm-trim-catalog' ),
			'needs_images'       => __( 'Needs Images', 'crmm-trim-catalog' ),
			'needs_verification' => __( 'Needs Verification', 'crmm-trim-catalog' ),
			'discrepancies'      => __( 'Discrepancies', 'crmm-trim-catalog' ),
			'ready'              => __( 'Ready to Publish', 'crmm-trim-catalog' ),
		);
		$current = isset( $_GET['crmm_queue'] ) ? sanitize_key( wp_unslash( $_GET['crmm_queue'] ) ) : '';
		foreach ( $queues as $key => $label ) {
			$views[ 'crmm_' . $key ] = sprintf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( add_query_arg( 'crmm_queue', $key, $base ) ), $current === $key ? ' class="current" aria-current="page"' : '', esc_html( $label ) );
		}
		return $views;
	}

	public static function row_actions( array $actions, WP_Post $post ): array {
		if ( CRMM_Trim_Post_Type::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		if ( ! CRMM_Trim_Number_Registry::for_post( $post->ID ) && current_user_can( CRMM_Trim_Capabilities::ASSIGN_NUMBERS ) ) {
			$actions['crmm_assign'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) . '#crmm-trim-number-registry' ) . '">' . esc_html__( 'Assign Number', 'crmm-trim-catalog' ) . '</a>';
		}
		return $actions;
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || CRMM_Trim_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'crmm-trim-admin', plugins_url( 'assets/css/admin-profile-manager.css', CRMM_TRIM_CATALOG_FILE ), array(), CRMM_TRIM_CATALOG_VERSION );
	}

	private static function render_attachment( int $post_id, string $meta_key ): void {
		$attachment_id = absint( get_post_meta( $post_id, $meta_key, true ) );
		if ( $attachment_id ) {
			echo wp_kses_post( wp_get_attachment_image( $attachment_id, array( 52, 52 ), false, array( 'class' => 'crmm-admin-thumb' ) ) );
		} else {
			echo '<span class="crmm-admin-thumb is-empty">—</span>';
		}
	}

	private static function taxonomy_dropdown( string $taxonomy, string $label ): void {
		wp_dropdown_categories(
			array(
				'show_option_all' => $label,
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) ) : '',
				'hide_empty'      => false,
				'hierarchical'    => false,
				'value_field'     => 'slug',
			)
		);
	}

	private static function select_filter( string $name, string $default, array $options ): void {
		$current = isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';
		echo '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $default ) . '</option>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	private static function label( mixed $value ): string {
		$value = (string) $value;
		return $value ? ucwords( str_replace( '_', ' ', $value ) ) : __( 'Unknown', 'crmm-trim-catalog' );
	}
}

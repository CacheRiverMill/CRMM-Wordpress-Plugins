<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Settings {
	public static function boot(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_crmm_trim_test_notification', array( __CLASS__, 'test_notification' ) );
		add_action( 'admin_notices', array( __CLASS__, 'mail_failure_notice' ) );
	}

	public static function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . CRMM_Trim_Post_Type::POST_TYPE,
			__( 'Trim Catalog Settings', 'crmm-trim-catalog' ),
			__( 'Settings', 'crmm-trim-catalog' ),
			CRMM_Trim_Capabilities::MANAGE_NOTIFICATIONS,
			'crmm-trim-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'crmm_trim_notifications',
			CRMM_Trim_Notifications::OPTION,
			array( 'type' => 'array', 'sanitize_callback' => array( 'CRMM_Trim_Notifications', 'sanitize_settings' ) )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( CRMM_Trim_Capabilities::MANAGE_NOTIFICATIONS ) ) {
			return;
		}
		$settings = CRMM_Trim_Notifications::settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Trim Catalog Settings', 'crmm-trim-catalog' ); ?></h1>
			<?php if ( isset( $_GET['crmm_trim_test'] ) ) : ?>
				<div class="notice notice-<?php echo 'sent' === sanitize_key( wp_unslash( $_GET['crmm_trim_test'] ) ) ? 'success' : 'error'; ?>"><p><?php echo 'sent' === sanitize_key( wp_unslash( $_GET['crmm_trim_test'] ) ) ? esc_html__( 'Test notification sent.', 'crmm-trim-catalog' ) : esc_html__( 'WordPress could not send the test notification.', 'crmm-trim-catalog' ); ?></p></div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Part-number notifications', 'crmm-trim-catalog' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'crmm_trim_notifications' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Enabled', 'crmm-trim-catalog' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( CRMM_Trim_Notifications::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Email recipients after a new permanent number is assigned', 'crmm-trim-catalog' ); ?></label></td></tr>
					<tr><th scope="row"><label for="crmm-trim-recipients"><?php esc_html_e( 'Recipients', 'crmm-trim-catalog' ); ?></label></th><td><textarea class="large-text" rows="3" id="crmm-trim-recipients" name="<?php echo esc_attr( CRMM_Trim_Notifications::OPTION ); ?>[recipients]"><?php echo esc_textarea( (string) $settings['recipients'] ); ?></textarea><p class="description"><?php esc_html_e( 'Separate email addresses with commas, spaces, or semicolons.', 'crmm-trim-catalog' ); ?></p></td></tr>
					<tr><th scope="row"><label for="crmm-trim-subject-prefix"><?php esc_html_e( 'Subject prefix', 'crmm-trim-catalog' ); ?></label></th><td><input class="regular-text" id="crmm-trim-subject-prefix" name="<?php echo esc_attr( CRMM_Trim_Notifications::OPTION ); ?>[subject_prefix]" value="<?php echo esc_attr( (string) $settings['subject_prefix'] ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="crmm_trim_test_notification">
				<?php wp_nonce_field( 'crmm_trim_test_notification' ); ?>
				<?php submit_button( __( 'Send Test Notification', 'crmm-trim-catalog' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function test_notification(): void {
		if ( ! current_user_can( CRMM_Trim_Capabilities::MANAGE_NOTIFICATIONS ) ) {
			wp_die( esc_html__( 'You are not allowed to test notifications.', 'crmm-trim-catalog' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'crmm_trim_test_notification' );
		$sent = CRMM_Trim_Notifications::send_test( get_current_user_id() );
		wp_safe_redirect(
			add_query_arg(
				'crmm_trim_test',
				$sent ? 'sent' : 'failed',
				admin_url( 'edit.php?post_type=' . CRMM_Trim_Post_Type::POST_TYPE . '&page=crmm-trim-settings' )
			)
		);
		exit;
	}

	public static function mail_failure_notice(): void {
		$part_number = get_transient( 'crmm_trim_mail_failed_' . get_current_user_id() );
		if ( ! $part_number ) {
			return;
		}
		delete_transient( 'crmm_trim_mail_failed_' . get_current_user_id() );
		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( sprintf( __( 'Part number %s was assigned, but WordPress could not send its notification email.', 'crmm-trim-catalog' ), $part_number ) ) );
	}
}

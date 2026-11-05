<?php
// Dependency-free unit harness. WordPress, ACF transport and MySQL are test doubles.
define( 'ABSPATH', __DIR__ );
define( 'CRMM_TRIM_CATALOG_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error { public function __construct( public string $code, public string $message ) {} public function get_error_message() { return $this->message; } }
class WP_Post { public function __construct( public int $ID, public string $post_title = '', public string $post_type = 'crmm_trim_profile' ) {} }
class WP_Term { public function __construct( public int $term_id ) {} }
class Json_Response extends Exception { public function __construct( public bool $success, public array $data, public int $status = 200 ) { parent::__construct(); } }
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( $value ); }
function sanitize_key( $value ) { return strtolower( $value ); }
function current_user_can( ...$args ) { return $GLOBALS['allowed']; }
function get_current_user_id() { return 7; }
function current_time( ...$args ) { return '2026-11-05'; }
function wp_is_post_revision( $id ) { return false; }
function wp_is_post_autosave( $id ) { return false; }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_post_type( $id ) { return get_post( $id )?->post_type; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function wp_update_post( $data ) { $GLOBALS['posts'][ $data['ID'] ]->post_title = $data['post_title']; }
function wp_get_post_terms( $id, $taxonomy, $args = array() ) {
 $ids = $GLOBALS['terms'][ $id ][ $taxonomy ] ?? array();
 return ( $args['fields'] ?? '' ) === 'ids' ? $ids : array_map( fn( $id ) => new WP_Term( $id ), $ids );
}
function wp_get_object_terms( ...$args ) { return wp_get_post_terms( ...$args ); }
function wp_set_object_terms( $id, $ids, $taxonomy, $append = false ) { $GLOBALS['terms'][ $id ][ $taxonomy ] = $ids; }
function get_term_meta( $id, $key, $single = true ) { return $GLOBALS['term_meta'][ $id ][ $key ] ?? ''; }
function get_terms( $args ) {
 foreach ( $GLOBALS['term_meta'] as $id => $meta ) if ( ( $meta[ $args['meta_key'] ] ?? null ) === $args['meta_value'] ) return array( new WP_Term( $id ) );
 return array();
}
function check_ajax_referer( ...$args ) { /* Transport authentication is covered by staging, not this unit harness. */ }
function wp_send_json_error( $data, $status = 200 ) { throw new Json_Response( false, $data, $status ); }
function wp_send_json_success( $data ) { throw new Json_Response( true, $data ); }
function add_filter( $tag, $callback, $priority = 10, $count = 1 ) { $GLOBALS['hooks'][ $tag ][ $priority ][] = array( $callback, $count ); }
function add_action( ...$args ) { add_filter( ...$args ); }
function do_action( $tag, ...$args ) {
 $hooks = $GLOBALS['hooks'][ $tag ] ?? array(); ksort( $hooks );
 foreach ( $hooks as $callbacks ) foreach ( $callbacks as [ $fn, $count ] ) $fn( ...array_slice( $args, 0, $count ) );
}
function apply_filters( $tag, $value, ...$args ) {
 $hooks = $GLOBALS['hooks'][ $tag ] ?? array(); ksort( $hooks );
 foreach ( $hooks as $callbacks ) foreach ( $callbacks as [ $fn, $count ] ) $value = $fn( ...array_slice( array( $value, ...$args ), 0, $count ) );
 return $value;
}
function acf_add_local_field_group( $group ) { foreach ( $group['fields'] as $field ) $GLOBALS['fields'][ $field['key'] ] = $field; }
function acf_add_validation_error( $input, $message ) { $GLOBALS['errors'][] = array( $input, $message ); }
class Test_DB {
 public string $prefix = 'wp_'; public array $inserted = array(); public int $reads = 0;
 public function prepare( $query, ...$args ) { return $query; }
 public function get_var( $query ) { ++$this->reads; return str_contains( $query, 'MAX(' ) ? 1014 : 1; }
 public function query( $query ) { return true; }
 public function insert( $table, $values, $formats ) { $this->inserted[] = $values; return 1; }
}
$root = getenv( 'PLUGIN_ROOT' ) ?: __DIR__ . '/../crmm-trim-catalog';
foreach ( array( 'post-type', 'capabilities', 'taxonomies', 'number-registry', 'audit', 'integrity', 'fields', 'admin' ) as $name ) require $root . '/includes/class-crmm-trim-' . $name . '.php';
CRMM_Trim_Fields::boot();
CRMM_Trim_Fields::register();
// ACF checks required values at priority 5, then runs the keyed validation filters.
add_action( 'acf/validate_save_post', function () {
 foreach ( $_POST['acf'] ?? array() as $key => $value ) {
  if ( ! isset( $GLOBALS['fields'][ $key ] ) ) continue;
  $field = $GLOBALS['fields'][ $key ];
  $valid = empty( $field['required'] ) || ! empty( $value );
  $valid = apply_filters( 'acf/validate_value/key=' . $key, $valid, $value, $field, 'acf[' . $key . ']' );
  if ( true !== $valid ) acf_add_validation_error( $key, is_string( $valid ) ? $valid : 'Required' );
 }
}, 5 );
function reset_case( $assigned = true ) {
 global $post, $posts, $meta, $terms, $term_meta, $errors, $wpdb, $allowed;
 $post = new WP_Post( 309, '4-CR-1009' ); $posts = array( 309 => $post, 400 => new WP_Post( 400 ) );
 $meta = array( 309 => array( '_crmm_part_number' => $assigned ? '4-CR-1009' : '' ) );
 $terms = array( 309 => array( 'crmm_trim_category' => array( 4 ), 'crmm_trim_subtype' => array( 40 ) ), 400 => array( 'crmm_trim_category' => array( 4 ), 'crmm_trim_subtype' => array( 40 ) ) );
 $term_meta = array( 4 => array( 'crmm_category_code' => '4' ), 40 => array( 'crmm_subtype_code' => 'CR', 'crmm_parent_category_code' => '4' ), 10 => array( 'crmm_subtype_code' => 'CM', 'crmm_parent_category_code' => '1' ) );
 $_POST = array( '_acf_post_id' => '309', 'acf' => array() ); $_GET = array(); $errors = array(); $wpdb = new Test_DB(); $allowed = true;
}
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function run_case( $name, $fn ) { reset_case(); $fn(); echo "PASS $name\n"; }
function validate_form() { do_action( 'acf/validate_save_post' ); return $GLOBALS['errors']; }
function preview() { try { CRMM_Trim_Admin::preview_number(); } catch ( Json_Response $r ) { return $r; } throw new RuntimeException( 'No JSON response' ); }

run_case( 'locked controls remain disabled; raw field definitions remain required', function () {
 foreach ( array( 'field_crmm_trim_category', 'field_crmm_trim_subtype' ) as $key ) {
  $f = $GLOBALS['fields'][ $key ]; $locked = CRMM_Trim_Fields::lock_numbered_classification( $f );
  check( $locked['disabled'] === 1 && $locked['required'] === 0 && $f['required'] === 1, 'Incorrect locked field configuration' );
 }
} );
run_case( 'omitted locked controls validate against saved taxonomy terms before ACF required checks', function () {
 check( validate_form() === array(), 'Valid locked classification failed' );
 check( $_POST['acf']['field_crmm_trim_category'] === 4 && $_POST['acf']['field_crmm_trim_subtype'] === 40, 'Missing authoritative values' );
} );
run_case( 'empty hidden controls and normal post_ID save work', function () {
 $_POST = array( 'post_ID' => '309', 'acf' => array( 'field_crmm_trim_category' => '', 'field_crmm_trim_subtype' => '' ) );
 check( validate_form() === array(), 'Empty disabled controls failed' );
 CRMM_Trim_Admin::assign_number_on_save( 309 );
 check( $GLOBALS['wpdb']->inserted === array() && CRMM_Trim_Number_Registry::for_post( 309 ) === '4-CR-1009', 'Existing profile renumbered' );
} );
run_case( 'tampered locked classification is rejected and authoritative values preserved', function () {
 $_POST['acf'] = array( 'field_crmm_trim_category' => '1', 'field_crmm_trim_subtype' => array( 10 ) );
 check( count( validate_form() ) === 2, 'Tampering accepted' );
 check( $_POST['acf']['field_crmm_trim_category'] === 4, 'Tampered value retained' );
 $f = $GLOBALS['fields']['field_crmm_trim_subtype'];
 check( CRMM_Trim_Fields::protect_numbered_classification( 10, 309, $f ) === 40, 'Saved taxonomy not preserved' );
} );
run_case( 'missing, multiple, mismatched and incompatible saved terms remain blocked', function () {
 foreach ( array( array(), array( 40, 10 ), array( 10 ) ) as $ids ) {
  $GLOBALS['terms'][309]['crmm_trim_subtype'] = $ids; $GLOBALS['errors'] = array();
  check( count( validate_form() ) > 0, 'Invalid saved terms accepted' );
 }
 $GLOBALS['terms'][309]['crmm_trim_subtype'] = array(40); $GLOBALS['term_meta'][40]['crmm_parent_category_code'] = '1'; $GLOBALS['errors'] = array();
 check( count( validate_form() ) > 0, 'Wrong subtype parent accepted' );
} );
run_case( 'unnumbered fields retain required and compatibility validation', function () {
 reset_case( false );
	check( count( validate_form() ) === 2, 'Omitted new classification accepted' ); $GLOBALS['errors'] = array();
 $_POST['acf'] = array( 'field_crmm_trim_category' => '', 'field_crmm_trim_subtype' => '' );
 check( count( validate_form() ) === 2, 'Missing new classification accepted' );
 $_POST['acf'] = array( 'field_crmm_trim_category' => 4, 'field_crmm_trim_subtype' => 10 ); $GLOBALS['errors'] = array();
 check( count( validate_form() ) === 1, 'Incompatible new subtype accepted' );
} );
run_case( 'conflicting request IDs cannot substitute another profiles classification', function () {
 $_POST['post_ID'] = '400'; check( count( validate_form() ) > 0, 'Conflicting IDs accepted' );
 check( $_POST['acf'] === array(), 'Classification copied between posts' );
} );
run_case( 'new profile assignment remains idempotent and registry backed', function () {
 CRMM_Trim_Admin::assign_number_on_save( 400 ); CRMM_Trim_Admin::assign_number_on_save( 400 );
 check( CRMM_Trim_Number_Registry::for_post(400) === '4-CR-1015' && count($GLOBALS['wpdb']->inserted) === 1, 'First save did not assign exactly once' );
 check( get_post(400)->post_title === '4-CR-1015', 'Assigned title not synchronized' );
} );
run_case( 'existing preview returns permanent identity even for stale/missing classification', function () {
 $_POST['post_id'] = '309'; $r = preview();
 check( $r->success && $r->data === array( 'part_number' => '4-CR-1009', 'assigned' => true ), 'Existing preview is incorrect' );
 check( $GLOBALS['wpdb']->reads === 0, 'Queried next number for existing assignment' );
} );
run_case( 'new preview is unreserved and rejects invalid classification', function () {
 $_POST = array( 'post_id' => 400, 'category_code' => '4', 'subtype_code' => 'CR' ); $r = preview();
 check( $r->success && $r->data['assigned'] === false && $r->data['part_number'] === '4-CR-1015', 'New preview is incorrect' );
 check( $GLOBALS['wpdb']->inserted === array() && CRMM_Trim_Number_Registry::for_post(400) === '', 'Preview allocated a number' );
 $_POST['subtype_code'] = 'CM'; check( preview()->status === 400, 'Invalid subtype accepted' );
 unset( $_POST['category_code'] ); check( preview()->status === 400, 'Missing category accepted' );
} );
run_case( 'preview and locked validation still enforce permission and post type', function () {
 $GLOBALS['allowed'] = false; $_POST['post_id'] = '309'; check( preview()->status === 403, 'Preview permission bypass' );
 check( count(validate_form()) > 0, 'Validation permission bypass' );
 $GLOBALS['allowed'] = true; $GLOBALS['posts'][309]->post_type = 'page'; check( preview()->status === 400, 'Non-profile preview accepted' );
} );
run_case( 'server title and taxonomy integrity guards remain active', function () {
 $GLOBALS['post']->post_title = '4-CR-1015'; CRMM_Trim_Integrity::enforce_numbered_title( 309, $GLOBALS['post'], true );
 check( $GLOBALS['post']->post_title === '4-CR-1009', 'Title not restored' );
 $GLOBALS['terms'][309]['crmm_trim_subtype'] = array(10);
 CRMM_Trim_Integrity::enforce_numbered_classification(309, array(10), array(), 'crmm_trim_subtype', false, array(40));
 check( $GLOBALS['terms'][309]['crmm_trim_subtype'] === array(40), 'Taxonomy integrity lost' );
} );

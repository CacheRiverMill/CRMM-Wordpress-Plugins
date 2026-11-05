<?php
// Production filter with WordPress doubles; optional real REST class verification below.
define( 'ABSPATH', __DIR__ );
define( 'REST_REQUEST', getenv( 'NON_REST_TEST' ) !== '1' );
class CRMM_Trim_Post_Type { public const POST_TYPE = 'crmm_trim_profile'; }
class CRMM_Trim_Number_Registry { public const PART_NUMBER_META = '_crmm_part_number'; }
function add_action( ...$args ) {}
function add_filter( $tag, $callback, $priority, $count ) { $GLOBALS['filters'][$tag] = $callback; }
function get_post_type( $id ) { return $id === 318 ? 'crmm_trim_profile' : 'post'; }
function current_user_can( ...$args ) { return $GLOBALS['allowed']; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['rows'][$key] ?? array(); }
require ( getenv( 'PLUGIN_ROOT' ) ?: __DIR__ . '/../crmm-trim-catalog' ) . '/includes/class-crmm-trim-block-bindings.php';
CRMM_Trim_Block_Bindings::boot();
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function attempt( $key, $value, $check = null, $id = 318, $previous = '' ) {
 return ($GLOBALS['filters']['update_post_metadata'])($check, $id, $key, $value, $previous);
}
$GLOBALS['allowed'] = true;
$GLOBALS['rows'] = array('isometric_view'=>array('9869','9869'));
if ( ! REST_REQUEST ) {
 check(attempt('isometric_view','9869') === null, 'Non-REST updates changed');
 echo "PASS non-REST writes retain normal behavior\n";
 exit;
}
check(attempt('isometric_view','9869') === true, 'Unchanged duplicate still fails');
check($GLOBALS['rows']['isometric_view'] === array('9869','9869'), 'Stored rows were modified');
check(attempt('isometric_view',9869) === true, 'Equivalent sanitized integer ID failed');
echo "PASS unchanged duplicate image is acknowledged without changing data\n";
foreach (array('profile_image','profile_render','pdf_download','dxf_download') as $key) {
 $GLOBALS['rows'][$key] = array('9869','9869');
 check(attempt($key,'9869') === true, 'Another attachment field still fails');
}
echo "PASS all registered attachment fields handle identical duplicate rows\n";
check(attempt('isometric_view','9870') === null, 'Changed image was swallowed');
check(attempt('isometric_view',null) === null, 'Image deletion was swallowed');
check(attempt('isometric_view',array('9869')) === null, 'Invalid input was accepted');
echo "PASS actual changes, deletions and malformed input use normal validation/writes\n";
check(attempt('isometric_view','9869',false) === false, 'Another filter rejection was overridden');
check(attempt('isometric_view','9869',null,318,'old') === null, 'Conditional update was overridden');
check(attempt('isometric_view','9869',null,99) === null, 'Non-profile write was changed');
$GLOBALS['allowed'] = false;
check(attempt('isometric_view','9869') === null, 'Permission check bypassed');
$GLOBALS['allowed'] = true;
echo "PASS permissions, conditional updates, other filters and post types are preserved\n";
foreach (array(array(),array('9869'),array('9869','9870'),array('9869',array('9869'))) as $rows) {
 $GLOBALS['rows']['isometric_view'] = $rows;
 check(attempt('isometric_view','9869') === null, 'Missing, single or conflicting rows suppressed');
}
echo "PASS missing, single and conflicting metadata retain normal behavior\n";
$GLOBALS['rows']['verification_status'] = array('verified','verified');
check(attempt('verification_status','verified') === null, 'Verification write intercepted');
echo "PASS verification and other non-attachment fields are not intercepted\n";

// When supplied, exercise the production filter through WordPress's real REST
// update_meta_value(), not a copy of that method. The database remains a double.
if ( getenv( 'WP_REST_META_FIELDS_SOURCE' ) ) {
 class WP_Error { public function __construct( ...$args ) {} }
 class WP_Http { public const INTERNAL_SERVER_ERROR = 500; }
 function get_metadata_raw( ...$args ) { return get_post_meta($args[1],$args[2],false); }
 function get_object_subtype( ...$args ) { return 'crmm_trim_profile'; }
 function sanitize_meta( $key, $value, ...$args ) { return $value; }
 function wp_slash( $value ) { return $value; }
 function __( $value ) { return $value; }
 function rest_authorization_required_code() { return 403; }
 function update_metadata( $type, $id, $key, $value ) {
  $check = empty($GLOBALS['disable_guard']) ? attempt($key,$value,null,$id) : null;
  if ( $check !== null ) return (bool)$check;
  ++$GLOBALS['db_updates'];
  if ( $GLOBALS['fail_db'] || $GLOBALS['rows'][$key] === array($value,$value) ) return false;
  $GLOBALS['rows'][$key] = array($value,$value);
  return true;
 }
 require getenv( 'WP_REST_META_FIELDS_SOURCE' );
 class Actual_REST_Attachment_Test extends WP_REST_Meta_Fields {
  protected function get_meta_type() { return 'post'; }
  protected function get_rest_field_type() { return 'crmm_trim_profile'; }
  public function save($value) { return $this->update_meta_value(318,'isometric_view','isometric_view',$value); }
 }
 $rest = new Actual_REST_Attachment_Test();
 $GLOBALS['rows']['isometric_view'] = array('9869','9869');
 $GLOBALS['db_updates'] = 0; $GLOBALS['fail_db'] = false;
 $GLOBALS['disable_guard'] = true;
 check($rest->save('9869') instanceof WP_Error, 'Original duplicate failure was not reproduced');
 $GLOBALS['disable_guard'] = false; $GLOBALS['db_updates'] = 0;
 check($rest->save('9869') === true && $rest->save('9869') === true && $GLOBALS['db_updates'] === 0, 'Repeated real REST saves failed');
 check($rest->save('9870') === true && $GLOBALS['db_updates'] === 1, 'Changed attachment not saved');
 $GLOBALS['fail_db'] = true;
 check($rest->save('9871') instanceof WP_Error, 'Genuine database failure was hidden');
 echo "PASS actual WordPress REST method: repeated no-ops succeed; changed writes and database errors remain real\n";
}

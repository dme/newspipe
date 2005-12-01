<script language="php">
// $Id: main.php,v 1.3 2005/12/01 13:52:27 rcarmo Exp $
session_start();
ini_set( "display_errors", "off" );
require( "config.php" );
require( "lib.php" );

$goIMAP = new CIMAPWrapper( MAILBOX, MAIL_USERNAME, MAIL_PASSWORD );
// Disable warnings for next assignment, since there may not be a PATH_INFO
// at all, there may be more (or less) than two parameters, etc.
//@list( $gszScript, $gszUid, $gszAction, $gszParam ) = split( "/", $_SERVER["PATH_INFO"] );
@$gszScript = basename($_SERVER['PHP_SELF']);
@$gszUid = $_GET["uid"];
@$gszAction = $_GET["action"];
@$gszParam = $_GET["param"];
$gaValues = array();
$gaValues["url"] = assembleUrl();
$goAuthenticator = new CAuthenticator( "WebPipe", $gaUsers, $gaACL );
if( isset( $_GET["action"] ) )
  if( $_GET["action"] == "logout" )
    $goAuthenticator->logout();
@audit( E_DEBUG, "Session variables: " . serialize( $_SESSION ));
@audit( E_DEBUG, "Checking Privileges in $gszScript (" . serialize($gaPrivileges) . ")" );
$gaPrivileges = $goAuthenticator->checkPrivileges();
if( !isset($gaPrivileges["view"]) ) {
  if(!$goAuthenticator->login()) {
    @audit( "E_WARNING", "Invalid Authentication" );
  }
  $goAuthenticator->deny();
  exit;
}
</script>

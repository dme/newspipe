<script language="php">
// $Id: main.php,v 1.1 2004/10/10 00:11:27 rcarmo Exp $
ini_set( "display_errors", "off" );
require( "config.php" );
require( "lib.php" );

$goIMAP = new CIMAPWrapper( MAILBOX, MAIL_USERNAME, MAIL_PASSWORD );
// Disable warnings for next assignment, since there may not be a PATH_INFO
// at all, there may be more (or less) than two parameters, etc.
@list( $gszScript, $gszUid, $gszAction, $gszParam ) = split( "/", $_SERVER["PATH_INFO"] );
$gaValues = array();
$gaValues["url"] = assembleUrl();
</script>

<script language="php">
// $Id: action.php,v 1.3 2005/12/01 13:52:27 rcarmo Exp $
require( "main.php" );
if( $gszUid && $gaPrivileges["edit"] ) {
  $szNext = $goIMAP->getNextMessage( $gszUid );
  if( $gszAction != "delete" ) 
    $goIMAP->setFlag( $gszUid, $gszAction, $gszParam );
  else if( $gszAction == "delete" )
    $goIMAP->moveToTrash( $gszUid );
}
if( preg_match( "'body.php'", $_SERVER["HTTP_REFERER"] ) ) {
  redirect($szNext);
}
else
  redirect();
</script>

<script language="php">
// $Id: action.php,v 1.1 2004/10/10 00:11:27 rcarmo Exp $
require( "main.php" );
if( $gszUid )
  if( $gszAction != "delete" )
    $goIMAP->setFlag( $gszUid, $gszAction, $gszParam );
  else if( $gszAction == "delete" )
    $goIMAP->moveToTrash( $gszUid );
redirect();
</script>

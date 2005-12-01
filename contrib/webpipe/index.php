<script language="php">
// $Id: index.php,v 1.4 2005/12/01 13:52:27 rcarmo Exp $
require( "main.php" );
$oTemplate = new CTemplate( "list.html" );
$gaValues["messagelist"] = $goIMAP->renderMessageList( "item.html" );
$gaValues["debug"] = "";
$gaValues["privileges"] = '<a href="index.php?action=logout">logout</a>|' . join( "|", array_keys( $gaPrivileges ) );
header('Content-Type: text/html; charset=utf-8');
echo $oTemplate->replaceValues( $gaValues );
</script>

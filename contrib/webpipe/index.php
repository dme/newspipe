<script language="php">
// $Id: index.php,v 1.1 2004/10/10 00:11:27 rcarmo Exp $
require( "main.php" );
$oTemplate = new CTemplate( "list.html" );
$gaValues["messagelist"] = $goIMAP->messageList( "item.html" );
header('Content-Type: text/html; charset=utf-8');
echo $oTemplate->replaceValues( $gaValues );
</script>

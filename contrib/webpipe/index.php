<script language="php">
// $Id: index.php,v 1.2 2004/10/10 00:22:11 rcarmo Exp $
require( "main.php" );
$oTemplate = new CTemplate( "list.html" );
$gaValues["messagelist"] = $goIMAP->messageList( "item.html" );
header('Content-Type: text/html; charset=iso-8859-15');
echo utf8_decode($oTemplate->replaceValues( $gaValues ));
</script>

<script language="php">
require( "main.php" );
$oTemplate = new CTemplate( "feed.xml" );
$gaValues["items"] = $goIMAP->messageList( "item.xml" );
header('Content-Type: text/xml; charset=utf-8');
echo $oTemplate->replaceValues( $gaValues );
</script>

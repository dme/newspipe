<script language="php">
require( "main.php");

if( $gszUid ) {
  if( $gszParam == "" ) {
    @$gaValues = array_merge( $gaValues, $goIMAP->getMessage($gszUid) );
    header('Content-Type: text/html; charset=utf-8');
    $oTemplate = new CTemplate( "message.html" );
    echo $oTemplate->replaceValues( $gaValues );
  }
  else {
    list( $type, $length, $content, $modified ) = $goIMAP->getNamedPart( $gszUid, $gszParam );
    $nRequested = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    header("Last-Modified: " . gmdate("D, d M Y H:i:s", $modified) . " GMT");
    if( $nModified == $nRequested ) {
      header( $_SERVER['SERVER_PROTOCOL'] . " 304 Not Modified");
      exit();
    }
    header( "Content-Length: " . $length);
    header( "Content-Type: " . $type );
    header( "Cache-Control: max-age=2592000" );
    header( "Expires: " . gmdate("D, d M Y H:i:s", $modified+2592000) . " GMT");
    echo $content;
  }
}
</script>

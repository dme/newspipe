<script language="php">
// ======================================================================
// $Id: lib.php,v 1.3 2004/10/11 23:47:18 rcarmo Exp $
// IMAP Wrapper (non-XSLT version)
// ======================================================================

$gaIMAPTypes = array( "text", "multipart", "message", "application", "audio", "image", "video", "other" );
$gaIMAPEncodings = array( "7bit", "8bit", "binary", "base64", "quoted-printable", "other" );


function assembleUrl() { // {{{
  $szServerName = $_SERVER["SERVER_NAME"];
  if( $_SERVER["SERVER_PORT"] == "443" ) {
    return( "https://$szServerName" . SITE_ROOT );
  }
  if( $_SERVER["SERVER_PORT"] != "80" )
    $szServerName .= ":" . $_SERVER["SERVER_PORT"];
  return ( "http://$szServerName" . SITE_ROOT );
} // assembleUrl }}}


function redirect() { // {{{
  header( "Location: " . assembleUrl() );
  exit;
} // redirect }}}


class CTemplate { // {{{
  var $m_szBuffer;
  
  function CTemplate( $szFileName = "") { // {{{
    if( $szFileName != "" ) {
      $this->readTemplate( $szFileName );
    }
  } // CTemplate }}}

  function lengthSortHelper($a, $b) { // {{{
    $a = strlen($a);
    $b = strlen($b);
    if( $a == $b )
      return 0;
    return ($a > $b) ? -1 : 1;
  } // lengthSortHelper }}}

  function readTemplate( $szFileName ) { // {{{
    $this->m_szBuffer = "";
    if( file_exists( $szFileName ) ) {
      $this->m_szBuffer = file_get_contents($szFileName);
    }
  } // readTemplate }}}

  function replaceValues( $aValues ) { // {{{
    $szBuffer = $this->m_szBuffer;
    
    // Replace longest strings first
    uksort($aValues, array($this,"lengthSortHelper"));
    foreach($aValues as $szKey => $szVal) {
      $szBuffer = str_replace( "\$" . $szKey, $szVal, $szBuffer );
    }
    return $szBuffer;
  } // replaceValues }}}
} // }}}


class CIMAPWrapper extends CTemplate { // {{{
  var $m_oMailbox;

  function CIMAPWrapper( $szMailbox, $szUsername, $szPassword ) { // {{{
    // No error handling (yet)
    $this->m_oMailbox = imap_open( $szMailbox, $szUsername, $szPassword );
  } // }}}

  function timeSortHelper($a,$b) { // {{{
    $a = strtotime($a->date);
    $b = strtotime($b->date);
    if( $a == $b )
      return 0;
    return ($a > $b) ? -1 : 1;
  } // timeSortHelper }}}

  function getValues( $oMessage ) { // {{{
    // Build array for template substitution.
    // Remember that we want to toggle some fields, so values are negated.
    $aValues = array();
    $aValues["uid"] = $oMessage->uid;
    $aValues["subject"] = imap_utf8($oMessage->subject);
    $aValues["format_open"] = $oMessage->seen ? "" : "<b>";
    $aValues["format_close"] = $oMessage->seen ? "" : "</b>";
    $aValues["read"] = $oMessage->seen ? "yes" : "no";
    $aValues["read_icon"] = $oMessage->seen ? "dot.gif" : "ball.gif";
    $aValues["read_alt"] = $oMessage->seen ? "[ ]" : "[r]";
    $aValues["flag"] = $oMessage->flagged ? "off" : "on";
    $aValues["flag_icon"] = $oMessage->flagged ? "flag.gif" : "dot.gif";
    $aValues["flag_alt"] = $oMessage->flagged ? "[F]" : "[ ]";
    $aValues["from"] = htmlentities(trim(preg_replace( '/("|:|<.+>)/', "", imap_utf8($oMessage->from) )));
    $aValues["date"] = $oMessage->date;
    $aValues["size"] = $oMessage->size;
    $aValues["shortdate"] = strftime("%a %m %H:%M", strtotime($oMessage->date));
    return $aValues;
  } // getValues }}}

  function messageList( $szTemplate, $nBound = 30 ) { // {{{
    $this->readTemplate( $szTemplate );
    $szBuffer = "";
    $aMessages = array_slice( imap_headers( $this->m_oMailbox ), 0, $nBound );
    $aMessages = imap_fetch_overview( $this->m_oMailbox, "1:" . count($aMessages) );

    // Sort messages by descending time
    uasort($aMessages, array($this, "timeSortHelper"));
    
    if( $aMessages != false ) {
      foreach( $aMessages as $oMessage ) {
        // We might have other clients accessing the mailbox, so ignore
        // messages flagged as deleted
        if( $oMessage->deleted != 1 ) {
          $aValues = $this->getValues( $oMessage );
          $szBuffer .= $this->replaceValues($aValues);
        }
      }
    }
    return $szBuffer;
  } // messageList }}}
  
  function getMessage( $szUid ) { // {{{
    $szBuffer = "";
    $oStructure = imap_fetchstructure( $this->m_oMailbox, $szUid, FT_UID );
    if( !$oStructure )
      redirect();
    list($aHeaders) = imap_fetch_overview( $this->m_oMailbox, $szUid, FT_UID );
    $aHeaders = array_merge($aHeaders, $this->getValues($aHeaders));
    
    // There are only two newspipe MIME structures: with or without images.
    // Images cause deeper nesting of the HTML section we want
    foreach( $oStructure->parts as $key => $val ) {
      if( $val->subtype == 0) {
        if(strtolower($val->subtype) == "html") {
          $szBuffer = quoted_printable_decode(imap_fetchbody( $this->m_oMailbox, $szUid, $key+1,  FT_UID));
        }
        else if(strtolower($val->parts[0]->subtype) == "html" ) {
          $szBuffer = quoted_printable_decode(imap_fetchbody( $this->m_oMailbox, $szUid, $key+1 . ".1",  FT_UID));
        }
      }
    }
    $szBuffer = preg_replace( "/cid:/", "", $szBuffer );
    return(array_merge( $aHeaders,
                        array( "body" => $szBuffer )));
  } // getMessage }}}
  
  function getNamedPart( $szUid, $szPart ) { // {{{
    global $gaIMAPTypes;
    $oStructure = imap_fetchstructure( $this->m_oMailbox, $szUid, FT_UID );
    // Brutally hard-coded for new newspipe MIME structure. Soft-fails with
    // a warning message.
    $nParts = count($oStructure->parts);
    $oBranch = $oStructure->parts[$nParts-1]->parts;
    foreach( $oBranch as $key => $val) {
      if( @strpos($val->id, $szPart) ) {
        $szContent = base64_decode( imap_fetchbody( $this->m_oMailbox, $szUid, "$nParts." . ($key+1), FT_UID ) );
        $szSubtype = $oBranch->subtype;
        $szType = strtolower( $gaIMAPTypes[$val->type] . "/" . $szSubtype);
        return array( $szType, strlen($szContent), $szContent );
      }
    }
  } // getNamedPart }}}
  
  function setFlag( $szUid, $szAction, $szParam ) { // {{{
    $szFlag = "";
    $aActions = array( "unread" => "\\Seen", "flag" => "\\Flagged" );
    @$szFlag = $aActions[$szAction];
    if( $szFlag ) {
      if( $szParam == "on" || $szParam == "no" )
        imap_setflag_full( $this->m_oMailbox, $szUid, $szFlag, ST_UID );
      else
        imap_clearflag_full( $this->m_oMailbox, $szUid, $szFlag, ST_UID );
    }
  } // setFlag }}}
  
  function moveToTrash( $szUid ) { // {{{
    imap_mail_move( $this->m_oMailbox, $szUid, MAIL_TRASH_FOLDER, CP_UID );
  } // moveToTrash }}}
} // CIMAPWrapper }}}

</script>

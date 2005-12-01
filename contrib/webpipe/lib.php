<script language="php">
// ======================================================================
// $Id: lib.php,v 1.9 2005/12/01 18:47:43 rcarmo Exp $
//
// WebPipe main library file	 
//	 
// Copyright (C) 2005 Rui Carmo, http://the.taoofmac.com	 
//	 
// This program is free software; you can redistribute it and/or modify	 
// it under the terms of the GNU General Public License as published by	 
// the Free Software Foundation; either version 2 of the License, or	 
// (at your option) any later version.	 
//	 
//  This program is distributed in the hope that it will be useful,	 
//  but WITHOUT ANY WARRANTY; without even the implied warranty of	 
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the	 
//  GNU General Public License for more details.	 
//	 
//  You should have received a copy of the GNU General Public License	 
//  along with this program; if not, write to the Free Software	 
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA	 
// ======================================================================

// constants {{{
define( 'FAULT_SYSLOG',  0 );
define( 'FAULT_EMAIL',   1 );
define( 'FAULT_FILELOG', 3 );
define( 'ANSI_LOGS',     1 );
define( 'LOG_LEVEL',     'E_INFO' | 'E_ERROR' | 'E_PARSE' | 'E_NOTICE' | 'E_WARNING' );
define( 'ANSI_NORMAL',   "\033[0m" );
define( 'ANSI_BLACK',    "\033[30m" );
define( 'ANSI_RED',      "\033[31m" );
define( 'ANSI_GREEN',    "\033[32m" );
define( 'ANSI_YELLOW',   "\033[33m" );
define( 'ANSI_BLUE',     "\033[34m" );
define( 'ANSI_MAGENTA',  "\033[35m" );
define( 'ANSI_CYAN',     "\033[36m" );
define( 'ANSI_WHITE',    "\033[37m" );
define( 'LOGIN_COOKIE', "loginattempts" );
define( 'DEVICE_PROFILE_DB', "devices.dat" );
// }}} 

// globals {{{
$gaIMAPTypes = array( "text", "multipart", "message", "application", "audio", "image", "video", "other" );
$gaIMAPEncodings = array( "7bit", "8bit", "binary", "base64", "quoted-printable", "other" );
// }}}


function assembleUrl( $szUid = "" ) { // {{{ build a base URL with a valid port, etc.
  $szServerName = $_SERVER["SERVER_NAME"];
  if( $_SERVER["SERVER_PORT"] == "443" ) {
    $szUrl = "https://$szServerName" . SITE_ROOT;
  }
  if( $_SERVER["SERVER_PORT"] != "80" )
    $szServerName .= ":" . $_SERVER["SERVER_PORT"];
  $szUrl = "http://$szServerName" . SITE_ROOT;
  if( $szUid )
    $szUrl .= "/body.php?uid=$szUid&action=view";
  return $szUrl;
} // assembleUrl }}} 

function redirect( $szUid = "" ) { // {{{ redirect to the site root
  header( "Location: " . assembleUrl( $szUid ) );
  exit;
} // redirect }}}

class CTemplate { // {{{ generic text template class
  var $m_szBuffer;
  
  function CTemplate( $szFileName = "") { // {{{ constructor
    if( $szFileName != "" ) {
      $this->readTemplate( $szFileName );
    }
  } // CTemplate }}}

  function lengthSortHelper($a, $b) { // {{{ reverse sort by length
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

class CIMAPWrapper extends CTemplate { // {{{ IMAP transaction wrapper
  var $m_oMailbox;
  var $m_oFolders;

  function CIMAPWrapper( $szMailbox, $szUsername, $szPassword ) { // {{{
    // No error handling (yet)
    $this->m_oMailbox = imap_open( $szMailbox, $szUsername, $szPassword );
    $this->m_oFolders = imap_getmailboxes( $this->m_oMailbox, $szMailbox, "*" );
  } // }}}

  function timeSortHelper($a,$b) { // {{{ reverse sort by time
    $a = strtotime($a->date);
    $b = strtotime($b->date);
    if( $a == $b )
      return 0;
    return ($a > $b) ? -1 : 1;
  } // timeSortHelper }}}

  function getValues( $oMessage ) { // {{{ values for template substitution
    global $gaPrivileges; // this is not very OO, but works.

    // Build array for template substitution.
    // Remember that we want to toggle some fields, so values are negated.
    $aValues = array();
    $aValues["uid"] = $oMessage->uid;
    if( !preg_match( "/Windows CE; PPC; 240x320/i", $_SERVER["HTTP_USER_AGENT"]) )
      $aValues["iconsize"] = 16;
    else
      $aValues["iconsize"] = 32;
    $aValues["subject"] = htmlentities(imap_utf8($oMessage->subject));
    $aValues["format_open"] = $oMessage->seen ? "" : "<b>";
    $aValues["format_close"] = $oMessage->seen ? "" : "</b>";
    $aValues["read"] = $oMessage->seen ? "yes" : "no";
    $aValues["read_icon"] = $oMessage->seen ? "dot.gif" : "ball.gif";
    $aValues["delete_icon"] = $gaPrivileges["edit"] ? "delete.gif" : "dot.gif";
    $aValues["delete_alt"] = $gaPrivileges["edit"] ? "[X]" : "[-]";
    $aValues["read_alt"] = $oMessage->seen ? "[r]" : "[ ]";
    $aValues["flag"] = $oMessage->flagged ? "off" : "on";
    $aValues["flag_icon"] = $oMessage->flagged ? "flag.gif" : "dot.gif";
    $aValues["flag_alt"] = $oMessage->flagged ? "[F]" : "[ ]";
    $aValues["from"] = trim(preg_replace( '/("|:|<.+>)/', "", imap_utf8($oMessage->from) ));
    $aValues["date"] = $oMessage->date;
    $aValues["server"] = $_SERVER['SERVER_NAME'];
    @$aValues["proto"] = $_SERVER['SERVER_HTTPS'] ? "https" : "http";
    $aValues["port"] = $_SERVER['SERVER_PORT'];
    $aValues["size"] = $oMessage->size;
    $aValues["shortdate"] = strftime("%a %d %H:%M", strtotime($oMessage->date));
    return $aValues;
  } // getValues }}}

  function messageList( $nBound = 30 ) { // {{{ message list
    $aMessages = imap_headers( $this->m_oMailbox );
    $aMessages = imap_fetch_overview( $this->m_oMailbox, "1:" . count($aMessages) );

    // Sort messages by descending time
    uasort($aMessages, array($this, "timeSortHelper"));
    $aBuffer = array();
    $nIndex = 0;
    if( $aMessages != false ) {
      foreach( $aMessages as $oMessage ) {
        // We might have other clients accessing the mailbox, so ignore
        // messages flagged as deleted
        if( $oMessage->deleted != 1 ) {
          $aBuffer[$oMessage->uid] = $oMessage;
          if( $nIndex > $nBound ) // limit listing length
            return $aBuffer;
        }
      }
    }
    return $aBuffer;
  } // messageList }}}

  function getNextMessage( $szUid ) { // {{{ get next UID
    $aMessages = $this->messageList();
    $aFirst = each($aMessages);
    reset($aMessages);
    while( $aMessages ) {
      $oIndex = array_shift($aMessages);
      if( intval($oIndex->uid) == intval($szUid) ) {
        $oIndex = array_shift($aMessages);
        return $oIndex->uid;
      }
    }
    return $aFirst->uid;
  } // getNextMessage }}}

  function renderMessageList( $szTemplate, $nBound = 30 ) { // {{{ render message list
    $szBuffer = "";
    $this->readTemplate( $szTemplate );
    $aMessages = $this->messageList( $nBound );
    
    $nIndex = 0;
    if( $aMessages != false ) {
      foreach( $aMessages as $oMessage ) {
        $nIndex++;
        $aValues = $this->getValues( $oMessage );
        $aValues["row_class"] = $nIndex % 2 ? "odd" : "even";
        $szBuffer .= $this->replaceValues($aValues);
        if( $nIndex > $nBound ) // limit listing length
          return $szBuffer;
      }
    }
    return $szBuffer;
  } // renderMessageList }}}

  function messageContentList( $szTemplate, $nBound = 30 ) { // {{{ render message list
    $this->readTemplate( $szTemplate );
    $szBuffer = "";
    $aMessages = imap_headers( $this->m_oMailbox );
    $aMessages = imap_fetch_overview( $this->m_oMailbox, "1:" . count($aMessages) );

    // Sort messages by descending time
    uasort($aMessages, array($this, "timeSortHelper"));
    
    $nIndex = 0;
    if( $aMessages != false ) {
      foreach( $aMessages as $oMessage ) {
        // We might have other clients accessing the mailbox, so ignore
        // messages flagged as deleted
        if( $oMessage->deleted != 1 ) {
          $nIndex++;
          $aValues = $this->getValues( $oMessage );
          $aValues["row_class"] = $nIndex % 2 ? "odd" : "even";
          $aMsg = $this->getMessage( $oMessage->uid, FT_UID | FT_PEEK );
          $aValues["body"] = htmlspecialchars(str_replace('src="', 'src="' . assembleURL($oMessage->uid ) . "&param=", $aMsg["body"]));
          $szBuffer .= $this->replaceValues($aValues);
          if( $nIndex > $nBound ) // limit listing length
            return $szBuffer;
        }
      }
    }
    return $szBuffer;
  } // messageList }}}
  
  function getMessage( $szUid, $nFlags = FT_UID ) { // {{{ get a single HTML message 
    $szBuffer = "";
    $oStructure = imap_fetchstructure( $this->m_oMailbox, $szUid, $nFlags );
    if( !$oStructure )
      redirect();
    list($aHeaders) = imap_fetch_overview( $this->m_oMailbox, $szUid, $nFlags );
    $aHeaders = array_merge($aHeaders, $this->getValues($aHeaders));
    
    // There are only two newspipe MIME structures: with or without images.
    // Images cause deeper nesting of the HTML section we want
    foreach( $oStructure->parts as $key => $val ) {
      if( $val->subtype == 0) {
        if(strtolower($val->subtype) == "html") {
          $szBuffer = quoted_printable_decode(imap_fetchbody( $this->m_oMailbox, $szUid, $key+1,  $nFlags ));
        }
        else if(strtolower($val->parts[0]->subtype) == "html" ) {
          $szBuffer = quoted_printable_decode(imap_fetchbody( $this->m_oMailbox, $szUid, $key+1 . ".1", $nFlags ));
        }
      }
    }
    $szBuffer = preg_replace( '/cid:(.+)"/U', "body.php?uid=$szUid&param=" . '$1"', $szBuffer );
    // Remove image sizes from HTML sent to WAP/PDA browsers
    $oProfile = new CProfileManager();
    if( $oProfile->getScreenSize() ) {
      $szBuffer = preg_replace( "/width=.+ /i", " ", $szBuffer );
      $szBuffer = preg_replace( "/height=.+ /i", " ", $szBuffer );
    }
    return(array_merge( $aHeaders,
                        array( "body" => $szBuffer )));
  } // getMessage }}}
  
  function getNamedPart( $szUid, $szPart, $nFlags = FT_UID ) { // {{{ get a specific part (image)
    global $gaIMAPTypes;
    list($aHeaders) = imap_fetch_overview( $this->m_oMailbox, $szUid, $nFlags );
    $oStructure = imap_fetchstructure( $this->m_oMailbox, $szUid, $nFlags );
    // Brutally hard-coded for new newspipe MIME structure. Soft-fails with
    // a warning message.
    $nParts = count($oStructure->parts);
    $oBranch = $oStructure->parts[$nParts-1]->parts;
    foreach( $oBranch as $key => $val) {
      if( @strpos($val->id, $szPart) ) {
        $szContent = trim(base64_decode( imap_fetchbody( $this->m_oMailbox, $szUid, "$nParts." . ($key+1), $nFlags ) ));
        $szSubtype = strtolower($val->subtype);
        $szSupertype = strtolower($gaIMAPTypes[$val->type]);
        $szType = strtolower( $szSupertype . "/" . $szSubtype);
        @audit( E_DEBUG, "MIME Type is $szType" );
        if( ( $szSupertype == "image" ) && RESIZE_IMAGES ) {
          // Resize images for WAP/PDA browsers
          $oProfile = new CProfileManager();
          $szSize = $oProfile->getScreenSize();
          if( $szSize ) {
            @audit( E_DEBUG, "Screen size is $szSize" );
            list( $x, $y ) = split( "x", $szSize );
            $x = $x - 8; // allow for scroll bars in phones
            // May not work on GIFs depending on your PHP
            $i = imagecreatefromstring( $szContent );
            if( $i ) {
              $ox = $sx = 1.0 * imagesx( $i );
              $oy = $sy = 1.0 * imagesy( $i );
              
              $bFitWidth = false;
              if( $sx > $x ) { // {{{ image doesn't fit
                $bResize = true;
                if( $sx/$sy > 1.5 ) { // wide image, need to rotate
                  $bRotate = true;
                  $bFitWidth = true;
                }
                else 
                  $bRotate = false;
              } // }}}
    global $gaProfileOverrides; // This should be cleaned up and become a parameter
    foreach( $gaProfileOverrides as $szUserAgent => $aOverrides ) {
      if(preg_match($szUserAgent, $_SERVER['HTTP_USER_AGENT']))
        $bRotate = $aOverrides["rotate"];
    }
              
              if( $bRotate ) { // {{{ rotate image (around its center)
                $c = imagecreatetruecolor(max($sx, $sy), max($sx, $sy));
                imagecopy( $c, $i, 0,0,0,0, $sx, $sy );
                $r = imagerotate( $c, 270, 0 );
                $foo = $sx; $sx = $sy; $sy = $foo;
                $offset = max($sx,$sy) - $sx; // rotation is around _center_
              } 
              else {
                $offset = 0;
                $r = $i;
              } // }}}

              if( $bResize || $bRotate ) { // {{{ adjust final size
                if( $bResize ) {
                  if( $bFitWidth ) {
                    if($sx > $x) {
                      $y = ($sy / $sx) * $x;
                    }
                    else {
                      $x = $sx;
                      $y = $sy;
                    }
                  }
                  else {
                    if ($x && ($sx < $sy)) {
                      $x = ($y / $sy) * $sx;
                    } else {
                      $y = ($x / $sx) * $sy;
                    } 
                  }
                } // }}}
                @audit( E_DEBUG, sprintf( "Projected (original) image size: %dx%d (%dx%d)", $x, $y, $ox, $oy ) );

                $n = imagecreatetruecolor( $x, $y );
                imagecopyresampled( $n, $r, 0,0,$offset,0, $x,$y,$sx,$sy );
                ob_start(); // capture output
                imagejpeg( $n, null, 90 );
               
                // Work around amazing BES/Blackberry image resize bug - it only
                // displays long JPEGs properly if I send an incorrect mimetype.
                if( !preg_match( "/BlackBerry/i", $_SERVER["HTTP_USER_AGENT"]) )
                  $szType = "image/jpeg";
                else
                  $szType = "image/png";
                $szContent=ob_get_contents();
                @audit( E_DEBUG, "Generated JPEG with " . strlen( $szContent ) . " bytes" );
                ob_end_clean();
              }
            }
          }
        }
        return array( $szType, strlen($szContent), $szContent, strtotime($oMessage->date) );
      }
    }
  } // getNamedPart }}}
  
  function setFlag( $szUid, $szAction, $szParam ) { // {{{ flag a message
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
  
  function moveToTrash( $szUid ) { // {{{ "erase" a message
    imap_mail_move( $this->m_oMailbox, $szUid, MAIL_TRASH_FOLDER, CP_UID );
  } // moveToTrash }}}
} // CIMAPWrapper }}}


// Authentication

class CAuthenticator { // {{{
  var $m_szRealm;
  var $m_aAuthData;
  var $m_szUsername;
  var $m_aACL;

  function CAuthenticator( $szRealm, $aAuthData, $aACL = "" ) { // {{{
    $this->m_szRealm   = $szRealm;
    $this->m_aAuthData = $aAuthData;
    $this->m_aACL      = $aACL;
  } // }}}

  function checkPrivileges() { // {{{
    $aLevels = array();
    if( is_array($this->m_aACL) ) {
      @audit( E_INFO, "[Auth] Checking ACL" );
      foreach( $this->m_aACL as $aEntry ) {
        $aPrivileges = split( ",", array_shift( $aEntry ) );
        $nConditions = 0; 
        foreach( $aEntry as $szEnv => $szVal ) {
          if( $szEnv == "action" )
            continue;
          @audit( E_DEBUG, "[Auth] $szEnv(" . $_SERVER[$szEnv] . ") => $szVal" );
          if( $szEnv == "PHP_AUTH_USER" && $this->login() )
            $nConditions++;
          elseif( isset($_SERVER[$szEnv]) ) {
            if( preg_match( "$szVal", $_SERVER[$szEnv] ) ) {
              $nConditions++;
            }
          }
        }
        // did we match all conditions?
        if( $nConditions == count( $aEntry ) ) {
          foreach( $aPrivileges as $szName )
            $aLevels[$szName] = 1;
        }
      }
    }
    @audit( E_DEBUG, "[Auth] Assembled levels: " . serialize( $aLevels ) );
    return $aLevels;
  } // checkPrivileges }}}

  function auth() { // {{{
    @audit( E_INFO, "[Auth] Sending HTTP Auth request" );
    header('WWW-Authenticate: Basic realm="' . $this->m_szRealm . '"');
    header( $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized');
    header( 'status: 401 Unauthorized');
    exit;
  } // auth }}}

  function deny( $szMessage = "<h1>401 Unauthorized</h1>", $szDigest = "Invalid Credentials" ) { // {{{
    header( $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized');
    header( "X-Reason: $szDigest" );
    echo( '<a href="index.php?action=logout">Reset session</a>' );
    echo "$szMessage\n$szDigest";
    exit;
  } // deny }}}

  function logout() { // {{{
    @session_destroy();
  } // }}}

  function login() { // {{{
    @$szUsername = $_SERVER["PHP_AUTH_USER"];
    @$szPassword = $_SERVER["PHP_AUTH_PW"];

    if( isset($_SESSION["Login"]) )
      return true;

    if( $szUsername != "" ) {
      if( $this->m_aAuthData[$szUsername] == md5($szPassword) ) {
        $this->m_szUsername = $szUsername;
        @audit( E_INFO, "[Auth] Accepted Authentication for $szUsername." );
        $_SESSION["Login"] = true;
        return true;
      }
      else {
        @audit( E_WARNING, ANSI_YELLOW . "[Auth]" . ANSI_NORMAL . " Rejected Authentication for $szUsername." );
        return false;
      }
    }
    $this->auth();
  } // }}}  

  function checkACL( $szName ) { // {{{
    if( isset( $this->m_aACL[$szName] ) )
      if( $this->m_ACL[$szName]["authorized"] )
        return true;
    return false;
  } // }}}

} // CAuthenticator }}}

class CProfileManager { // {{{ 
  var $m_aData;
  var $m_szURL;

  function CProfileManager() {  // {{{
    @$this->m_aData = unserialize( file_get_contents(DEVICE_PROFILE_DB) );
    // Some devices and gateways are broken, so we have to look for the
    // device profile in several fields
    $aFields = array( "HTTP_X_WAP_PROFILE", "HTTP_X_PROFILE",
                      "HTTP_PROFILE", "HTTP_13_PROFILE" );
    foreach( $aFields as $szField ) { // {{{
      if(isset($_SERVER[$szField])) {
        $szURL = stripslashes( $_SERVER[$szField] );
        // in case of multiple profiles or extra parameters:
        $szURL = array_shift( preg_split( "/[,;\s]/", $szURL ) );
        // remove quotes around URL (some gateways make this worse by adding
        // junk quotes, so we look for multiple levels)
        while( preg_match( "'\"(.+)\"'", $szURL, $regs) )
          $szURL = $regs[1];
        audit( E_DEBUG, "Got $szURL" );
        if( preg_match( "/^http:/i", $szURL ) ) {
          $this->m_szURL = $szURL;
          if( isset( $this->m_aData[$szURL] ) )
            break;
          // Now try to parse the profile
          $szXML = file_get_contents( $szURL );
          if( $szXML ) { // {{{
            // very basic parsing - we do not look at CharSet "bags",
            // for instance...
            preg_match( "'<prf:Model>(.+)</prf:Model>'im", $szXML, $aMatches );
            $this->m_aData[$szURL]["model"] = $aMatches[1];
            preg_match( "'<prf:ScreenSize>(.+)</prf:ScreenSize>'im", $szXML, $aMatches );
            $this->m_aData[$szURL]["screensize"] = $aMatches[1];
            preg_match( "'<prf:BitsPerPixel>(.+)</prf:BitsPerPixel>'im", $szXML, $aMatches );
            $this->m_aData[$szURL]["bitsperpixel"] = $aMatches[1];
            audit( E_DEBUG, "New profile $szURL: " . serialize( $this->m_aData[$szURL] ) );
            $hFile = fopen( DEVICE_PROFILE_DB, "w" );
            flock( $hFile, LOCK_EX );
            fwrite( $hFile, serialize( $this->m_aData ) );
            flock( $hFile, LOCK_UN );
            fclose( $hFile );
          } // }}}
        }
      }
    } // }}}
  } // }}}

  function getScreenSize() {
    global $gaProfileOverrides; // This should be cleaned up and become a parameter
    foreach( $gaProfileOverrides as $szUserAgent => $aOverrides ) {
      if(preg_match($szUserAgent, $_SERVER['HTTP_USER_AGENT']))
        return $aOverrides["size"];
    }
    return $this->m_aData[$this->m_szURL]["screensize"];
  } 
  
} // }}}

// ----------------------------------------------------------------------
// audit {{{ 
//
// Output stuff to the error log
// ----------------------------------------------------------------------

function audit( $nLevel, $szMessage, $nType = FAULT_SYSLOG, $szDestination = "", $szHeaders = "" ) {
  if( $nLevel && LOG_LEVEL ) {
    $szMessage = "[" . $_SERVER["REMOTE_ADDR"] . ":" . $_SERVER["REMOTE_PORT"] . "->" . $_SERVER["SERVER_ADDR"] . ":" . $_SERVER["SERVER_PORT"] . "] " . "[" . APPLICATION_NAME . "] $szMessage";
    if( !ANSI_LOGS )
      $szMessage = ereg_replace( "\033\[[0-9]+m", "", $szMessage );
    @error_log( $szMessage, $nSeverity, $szDestination, $szHeaders );
  }
} // audit }}}

</script>

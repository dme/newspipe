<script language="php">
// ======================================================================
// $Id: main.php,v 1.4 2005/12/01 18:47:43 rcarmo Exp $
//
// WebPipe main handler
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

session_start();
ini_set( "display_errors", "off" );
require( "config.php" );
require( "lib.php" );

$goIMAP = new CIMAPWrapper( MAILBOX, MAIL_USERNAME, MAIL_PASSWORD );
// Disable warnings for next assignment, since there may not be a PATH_INFO
// at all, there may be more (or less) than two parameters, etc.
//@list( $gszScript, $gszUid, $gszAction, $gszParam ) = split( "/", $_SERVER["PATH_INFO"] );
@$gszScript = basename($_SERVER['PHP_SELF']);
@$gszUid = $_GET["uid"];
@$gszAction = $_GET["action"];
@$gszParam = $_GET["param"];
$gaValues = array();
$gaValues["url"] = assembleUrl();
$goAuthenticator = new CAuthenticator( "WebPipe", $gaUsers, $gaACL );
if( isset( $_GET["action"] ) )
  if( $_GET["action"] == "logout" )
    $goAuthenticator->logout();
@audit( E_DEBUG, "Session variables: " . serialize( $_SESSION ));
@audit( E_DEBUG, "Checking Privileges in $gszScript (" . serialize($gaPrivileges) . ")" );
$gaPrivileges = $goAuthenticator->checkPrivileges();
if( !isset($gaPrivileges["view"]) ) {
  if(!$goAuthenticator->login()) {
    @audit( "E_WARNING", "Invalid Authentication" );
  }
  $goAuthenticator->deny();
  exit;
}
</script>

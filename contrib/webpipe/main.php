<script language="php">
// ======================================================================
// $Id: main.php,v 1.2 2004/10/24 00:49:30 rcarmo Exp $
//
// WebPipe Main File
//
// Copyright (C) 2004 Rui Carmo, http://the.taoofmac.com
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
//
// ======================================================================

ini_set( "display_errors", "off" );
require( "config.php" ); // Configuration, ACLs, Passwords, etc.
require( "lib.php" );    // Class Library

// Start talking to IMAP server
$goIMAP = new CIMAPWrapper( MAILBOX, MAIL_USERNAME, MAIL_PASSWORD );

// Disable warnings for next assignment, since there may not be a PATH_INFO
// at all, there may be more (or less) than two parameters, etc.
@list( $gszScript, $gszUid, $gszAction, $gszParam ) = split( "/", $_SERVER["PATH_INFO"] );

$gaValues = array();
$gaValues["url"] = assembleUrl();

// Access Control
$goAuthenticator = new CAuthenticator( "WebPipe", $gaUsers, $gaACL );
$gaPrivileges = $goAuthenticator->checkPrivileges();
audit( E_DEBUG, "Checking Privileges" );
if( !isset($gaPrivileges["view"]) ) {
  if(false == $goAuthenticator->login()) {
    audit( "E_WARNING", "Invalid Authentication" );
  }
  $goAuthenticator->deny();
  exit;
}
</script>

<script language="php">
// ======================================================================
// $Id: action.php,v 1.4 2005/12/01 18:47:43 rcarmo Exp $
//
// WebPipe action handler
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
require( "main.php" );
if( $gszUid && $gaPrivileges["edit"] ) {
  $szNext = $goIMAP->getNextMessage( $gszUid );
  if( $gszAction != "delete" ) 
    $goIMAP->setFlag( $gszUid, $gszAction, $gszParam );
  else if( $gszAction == "delete" )
    $goIMAP->moveToTrash( $gszUid );
}
if( preg_match( "'body.php'", $_SERVER["HTTP_REFERER"] ) ) {
  redirect($szNext);
}
else
  redirect();
</script>

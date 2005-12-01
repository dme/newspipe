<script language="php">
// ======================================================================
// $Id: body.php,v 1.5 2005/12/01 18:47:43 rcarmo Exp $
//
// WebPipe message body renderer
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

<script language="php">
// ======================================================================
// $Id: index.php,v 1.5 2005/12/01 18:47:43 rcarmo Exp $
//
// WebPipe main index
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
$oTemplate = new CTemplate( "list.html" );
$gaValues["messagelist"] = $goIMAP->renderMessageList( "item.html" );
$gaValues["debug"] = "";
$gaValues["privileges"] = '<a href="index.php?action=logout">logout</a>|' . join( "|", array_keys( $gaPrivileges ) );
header('Content-Type: text/html; charset=utf-8');
echo $oTemplate->replaceValues( $gaValues );
</script>

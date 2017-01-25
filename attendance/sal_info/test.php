<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
?>
<html>
    <head>
        
    </head>
    <body>
        <form action="upload_file.php" method="POST" enctype="multipart/form-data">
            
            <input type="hidden" name="user_id" value="343">
            <input type="hidden" name="document_type" value="Test">
            <input type="hidden" name="page_url" value="http://google.com">
            <input type="file" class="form-control" name="link_1">
            <input type="submit" name="submit" value="Upload" >
            
        </form>
    </body>
</html>



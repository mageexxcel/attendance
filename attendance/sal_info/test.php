<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
?>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Multiple File Ppload with PHP</title>
</head>
<body>
    <form action="upload_file_attachment.php" method="post" enctype="multipart/form-data">
    <input type="file" id="file" name="files[]" multiple="multiple" required />
  <input name ='submit' type="submit" value="Upload!" />
</form>
</body>
</html>

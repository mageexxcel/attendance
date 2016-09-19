<?php
//$currentMonth = "2016-08-01";
//echo Date('F', strtotime($currentMonth));

if(isset($_POST['submit'])){
    echo "<pre>";
   print_r($_POST);
   echo "<br>";
   foreach($_FILES as $k=>$v){
       if($v['name'] !=""){
           print_r($v);
       }
   }
}

?>
<html>
    <body>
        <form action="#" method="POST" enctype="multipart/form-data">
            <input type="file" name="file1">
            <input type="file" name="file2">
            <input type="file" name="file3">
            <input type="submit" name="submit" value="send">
        </form>
    </body>
</html>



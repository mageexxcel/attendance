<?php


$now = time(); // or your date as well
$your_date = strtotime("2016-08-24");
$datediff = $now - $your_date;

echo floor($datediff / (60 * 60 * 24));

die;

?>


<html>
    <body>
        <form action="display_user_info.php" method="POST" >
            <input type="checkbox" name="user_id[]" value="343">
            <input type="checkbox" name="user_id[]" value="313">
            <input type="checkbox" name="user_id[]" value="288">
            <input type="hidden" name="token" value="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6IjM0MyIsInVzZXJuYW1lIjoibWVyYWoiLCJyb2xlIjoiQWRtaW4iLCJuYW1lIjoibW9oYW1tYWQgbWVyYWoiLCJqb2J0aXRsZSI6InBocCBkZXZlbG9wZXIiLCJwcm9maWxlSW1hZ2UiOiJodHRwczpcL1wvYXZhdGFycy5zbGFjay1lZGdlLmNvbVwvMjAxNi0wMi0xMFwvMjA4MDc3ODkwOTBfMjdmMDQyNGQ2MmY5YzczZTAxMDVfMTkyLmpwZyIsImxvZ2luX3RpbWUiOjE0NzMzMjgwMjksImxvZ2luX2RhdGVfdGltZSI6IjA4LVNlcC0yMDE2IDA0OjQ3OjA5In0.ckPEB261rPmS1y3OAW70j0P1XrGQgs67jZjHtzffhdM">
            
            <input type="submit" name="submit" value="send">
        </form>
        
    </body>
</html>



<?php
$date1 = new DateTime("2016-03-01");
$date2 = new DateTime("2016-04-02");
$interval = $date1->diff($date2);
echo "difference " . $interval->y . " years, " . $interval->m." months, ".$interval->d." days "; 


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



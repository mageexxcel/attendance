<html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
        <title>Time</title>
        <link rel="stylesheet" href="css/jquery-ui.css">
        <script src="js/jquery.min.js"></script>
        <script src="js/jquery-ui.min.js"></script>

        <script>
            $(document).ready(function() {
                $("#datepicker").datepicker();
            });
        </script>
    </head>
    <body>

        <div class="container">
            <br><br>
            <div class="row">
                <h3>Time Table</h3><br>
                <form class="form-inline" action="upload_attendance.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Upload File</label>
                        <input type="file" name="image" required="required" />
                        <input type="hidden" name ="token" value="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6IjM0MyIsInVzZXJuYW1lIjoibWVyYWoiLCJyb2xlIjoiQWRtaW4iLCJuYW1lIjoibW9oYW1tYWQgbWVyYWoiLCJqb2J0aXRsZSI6InBocCBkZXZlbG9wZXIiLCJwcm9maWxlSW1hZ2UiOiJodHRwczpcL1wvYXZhdGFycy5zbGFjay1lZGdlLmNvbVwvMjAxNi0wMi0xMFwvMjA4MDc3ODkwOTBfMjdmMDQyNGQ2MmY5YzczZTAxMDVfMTkyLmpwZyIsImxvZ2luX3RpbWUiOjE0NzMzMjgwMjksImxvZ2luX2RhdGVfdGltZSI6IjA4LVNlcC0yMDE2IDA0OjQ3OjA5In0.ckPEB261rPmS1y3OAW70j0P1XrGQgs67jZjHtzffhdM">
                    </div>
                    <button type="submit" name="submit" class="btn btn-default">upload</button>
                </form>
            </div>
        </div>

        <hr><br>
        <div class="container">
            <div class="row">
                <table class="table table-hover">
                    <tr>
                        <th>#</th>
                        <th>User Id</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Timing</th>
                        <th>Duration</th>
                    </tr>
                    <?php
                    if (sizeof($time_table) > 0) {
                        $i = 1;
                        $time1 = "";
                        $time2 = "";
                        foreach ($time_table as $val) {
                            $time1 = str_replace("PM", "", current($val['timing']));
                            $time1 = strtotime(str_replace("-", "/", $time1));
                            $time2 = str_replace("PM", "", end($val['timing']));
                            $time2 = strtotime(str_replace("-", "/", $time2));
                            $time3 = $time2 - $time1;
                            $time3 = date("H:i:s", $time3);
                            echo "<tr>";
                            echo "<td>" . $i . "</td>";
                            echo "<td>" . $val['id'] . "</td>";
                            echo "<td>" . $val['name'] . "</td>";
                            echo "<td>" . $val['work_email'] . "</td>";
                            echo "<td>";
                            foreach ($val['timing'] as $d) {
                                echo $d . "<br>";
                            }
                            echo "</td>";
                            echo "<td>" . $time3 . "</td>";
                            echo "</tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr>";
                        echo "<td colspan='6'>No Data To Show</td>";
                        echo "</tr>";
                    }
                    ?>

                </table>
            </div>
        </div>

    </body>
</html>
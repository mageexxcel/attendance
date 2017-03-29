<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once ("c-hr.php");
$date = date("Y-m-d");

$db = Database::getInstance();
$mysqli = $db->getConnection();
$array=array();
if (isset($_POST['submit'])) {

    $userid = $mac_id = $m_type = $m_name = $m_price = $serial_no = $date_purchase = $mac_addr = $os = $status = $comment = "";
    $mac_addr = trim($_POST['mac_add']);
    $email = trim($_POST['work_email']);
    
    
    $q4 = "SELECT users.*,user_profile.* from users LEFT JOIN user_profile on users.id = user_profile.user_Id WHERE users.status = 'Enabled' and user_profile.work_email='$email'";
    $runQuery4 = Database::DBrunQuery($q4);
    $row4 = Database::DBfetchRow($runQuery4);
    if ($row4 != false) {
      $userid = $row4['user_Id'];  
      
    }
    else{
        echo "User id not found. PLease check email address inserted";
        die();
    }
    
    $q = "select * from machines_list where mac_address='$mac_addr'";
    $runQuery = Database::DBrunQuery($q);
    $row = Database::DBfetchRow($runQuery);
    if ($row != false) {
        $q = "UPDATE machines_list SET machine_type='$m_type', machine_name='$m_name', machine_price='$m_price', serial_number='$serial_no', date_of_purchase='$date_purchase', operating_system = '$os', status = '$status', comments = '$comment' WHERE id =" . $row['id'];
        Database::DBrunQuery($q);
        $mac_id = $row['id'];
    } else {
        $q = "INSERT INTO machines_list ( machine_type, machine_name, machine_price, serial_number, date_of_purchase, mac_address, operating_system, status, comments ) VALUES ( '$m_type', '$m_name', '$m_price', '$serial_no','$date_purchase', '$mac_addr', '$os', '$status', '$comment' ) ";
        Database::DBrunQuery($q);
        $mac_id = mysqli_insert_id($mysqli);
    }
    
    $q2 = "select * from machines_user where machine_id='$mac_id'";
    $runQuery2 = Database::DBrunQuery($q2);
    $row2 = Database::DBfetchRow($runQuery2);
    
    
    if ($row2 != false) {
         echo "<p style='color:red'>Mac address already assigned</p>";
       // $q5 = "UPDATE machines_user SET user_Id='$userid', assign_date='$date' WHERE id=".$row2['id'];
        //Database::DBrunQuery($q5);
    } else {
        $q5 = "INSERT INTO machines_user ( machine_id, user_Id, assign_date ) VALUES ( $mac_id, $userid, '$date') ";
        Database::DBrunQuery($q5);
    }
   
    
}
if(isset($_POST['update'])){
   
    $output = HR::UpdateOfficeMachine($_POST);
    header('location: add_mac.php');
    exit();
}
if(isset($_GET['delete']) && !empty($_GET['delete'])){
    
   $data = $_GET['delete'];
    $q = "Delete from machines_user where machine_id=$data";
    echo $q;
    $runQuery = Database::DBrunQuery($q);
    header('location: add_mac.php');
    exit();
}
if(isset($_GET['edit']) && !empty($_GET['edit'])){
    
   $id = $_GET['edit'];
    $q = "select * from machines_list where id=$id";
    $runQuery = Database::DBrunQuery($q);
    $array = Database::DBfetchRow($runQuery);
 
}

$a = HR::getAllMachineDetail();

?>

<!DOCTYPE html>
<html>
    <head>

    </head>
    <body>
        <div style="margin: 10px auto">
            <?php 
            if(sizeof($array) > 0){ ?>
            <h3>Update Mac Address </h3>
                 <form action="#" method="post">
                <table>
                   <input type="hidden" name="id" value="<?php echo $array['id'] ?>">
                    <tr>
                        <td><label>Mac_address: </label></td>
                        <td><input type="text" name="mac_address" value="<?php echo $array['mac_address'] ?>" required></td>
                    </tr>
                  
                    <tr>
                        <td colspan="2"><input type="submit" name="update" value="Update"></td>
                    </tr>
                </table>

            </form>
            <?php }
            else{ ?>
            <h3>Assign Mac Address to User</h3>
                 <form action="#" method="post">
                <table>
                    <tr>
                        <td><label>Mac_address: </label></td>
                        <td><input type="text" name="mac_add" value="" required></td>
                    </tr>
                    <tr>
                        <td><label>Assigning User's email_address: </label></td>
                        <td><input type="email" name="work_email" value="" required></td>
                    </tr>
                    <tr>
                        <td colspan="2"><input type="submit" name="submit" value="save"></td>
                    </tr>
                </table>

            </form>
           <?php }
            ?>
           
        </div>  
        <br><br>
        <div>
            <table>
                <tr>
                    <th>#Sr No.</th>
                    <th>MAC_address</th>
                    <th>User Name</th>
                    <th>Email address</th>
                    <th>Action</th>
                </tr>
               <?php 
               if(isset($a['data']) && sizeof($a['data']) > 0){
                   
                   $i= 1;
                   foreach($a['data'] as $val){
                  ?> 
                <tr>
                    <td><?php echo  $i?></td>
                    <td><?php echo  $val['mac_address']?></td>
                    <td><?php echo  $val['name']?></td>
                    <td><?php echo  $val['work_email']?></td>
                    <td><a href="?edit=<?php echo  $val['id']?>"><button>Edit</button></a> / <a href="?delete=<?php echo  $val['id']?>"><button>Delete</button></a></td>
                </tr>
                <?php  
                $i++;
                   }
               }
               ?>
            </table>
        </div>
        
        
        
    </body>
</html>
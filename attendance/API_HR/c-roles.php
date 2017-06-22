<?php

//require_once 'c-database.php';

trait Roles {

    //pages
    static $PAGE_inventory_system = 101;
    static $PAGE_manage_payslips = 102;
    static $PAGE_home = 103;
    static $PAGE_monthly_attendance = 104;
    static $PAGE_manage_working_hours = 105;
    static $PAGE_logout = 106;
    static $PAGE_holidays = 107;
    static $PAGE_team_view = 108;
    static $PAGE_apply_leave = 109;
    static $PAGE_manage_leaves = 110;
    static $PAGE_my_leaves = 111;
    static $PAGE_disabled_employes = 112;
    static $PAGE_manage_user_working_hours = 113;
    static $PAGE_leaves_summary = 114;
    static $PAGE_salary = 115;
    static $PAGE_manage_salary = 116;
    static $PAGE_my_profile = 117;
    static $PAGE_my_inventory = 118;
    static $PAGE_manage_users = 119;
    static $PAGE_manage_clients = 120;
    static $PAGE_forgot_password = 121;
    static $PAGE_documents = 122;
    static $PAGE_uploadAttendance = 123;
    static $PAGE_view_salary = 124;
    static $PAGE_policy_documents = 125;
    static $PAGE_upload_policy_documents = 126;
    static $PAGE_add_variables = 127;
    static $PAGE_mail_templates = 128;
    
    
    
    //action
    static $ACTION_working_hours_summary = 201;
    static $ACTION_add_new_employee = 202;
    static $ACTION_add_user_working_hours = 203;
    static $ACTION_get_user_worktime_detail = 204;
    //notification
    static $NOTIFICATION_apply_leave = 1001;
    static $NOTIFICATION_update_leave_status = 1002;
    static $NOTIFICATION_add_user_working_hours = 1003;

    public static function getAllPages() {
        $array = array(
            array(
                'id' => self::$PAGE_inventory_system,
                'name' => 'manageInventory',
            ),
            array(
                'id' => self::$PAGE_manage_payslips,
                'name' => 'managePayslips'
            )
        );


        return $array;
    }

    public static function getAllAction() {

        $array = array(
            array(
                'id' => self::$ACTION_working_hours_summary,
                'name' => 'working_hours_summary',
            ),
            array(
                'id' => self::$ACTION_add_new_employee,
                'name' => 'add_new_employee'
            ),
            array(
                'id' => self::$ACTION_add_user_working_hours,
                'name' => 'add_user_working_hours'
            ),
            array(
                'id' => self::$ACTION_get_user_worktime_detail,
                'name' => 'get_user_worktime_detail'
            )
        );

        return $array;
    }

    public static function getAllNotification() {
        $array = array(
            array(
                'id' => self::$NOTIFICATION_apply_leave,
                'name' => 'applyLeave',
            ),
            array(
                'id' => self::$NOTIFICATION_update_leave_status,
                'name' => 'updateLeaveStatus'
            ),
            array(
                'id' => self::$NOTIFICATION_add_user_working_hours,
                'name' => 'addUserWorkingHours'
            )
        );
        return $array;
    }

    public static function AddNewRole($name, $description) {
        $r_error = 1;
        $r_message = "";
        $q = "SELECT * FROM roles WHERE name ='" . trim($name) . "'";
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'name' => $name,
                'description' => $description
            );
            self::DBinsertQuery('roles', $ins);
            $r_error = 0;
            $r_message = "New role added";
        } else {
            $r_error = 1;
            $r_message = "Role name already exist";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function updateRole($data) {
        $r_error = 1;
        $r_message = "";
        $table = "";
        $search = "";
        $role = $data['role_id'];
        if (isset($data['page_id']) && $data['page_id'] !="") {
            $table = "roles_pages";
            $search = "page_Id";
            $pid = $data['page_id'];
        }
        if (isset($data['action_id']) && $data['action_id'] !="") {
            $table = "roles_action";
            $search = "action_Id";
            $pid = $data['action_id'];
        }
        if (isset($data['notification_id']) && $data['notification_id'] !="") {
            $table = "roles_notification";
            $search = "notification_Id";
            $pid = $data['notification_id'];
        }
        if(!empty($table)){
           $q = "SELECT * FROM " . $table . " WHERE role_Id = $role AND $search = $pid";
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'role_Id' => $role,
                $search => $pid
            );
            self::DBinsertQuery($table, $ins);
            $r_error = 0;
            $r_message = "Role updated!!";
        } else {
            $q = "DELETE FROM " . $table . " WHERE role_Id = $role AND $search = $pid";
            self::DBrunQuery($q);
            $r_error = 0;
            $r_message = "Role updated!!";
        } 
        }
        else{
          $r_message = "Empty data passed";  
        }
        
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function listAllRole() {

        $result = array();
        $allpages = self::getAllPages();
        $allaction = self::getAllAction();
        $allnotification = self::getAllNotification();
        $result['default_pages'] = $allpages;
        $result['default_actions'] = $allaction;
        $result['default_notifications'] = $allnotification;

        $array = self::getAllRole();
        $array2 = array();
        if (sizeof($array) > 0) {
            foreach ($array as $val) {
                $role_page = self::getRolePages($val['id']);
                $role_action = self::getRoleActions($val['id']);
                $role_notify = self::getRoleNotifications($val['id']);
                foreach ($allpages as $v1) {
                    $p = 0;
                    foreach ($role_page as $u1) {
                        
                        if ($u1['page_Id'] == $v1['id']) {
                            $p = 1;
                        }
                       
                    }
                     $v1['is_assigned'] = $p;
                  $val['role_pages'][] =  $v1;
                }
                foreach ($allaction as $v2) {
                    $p = 0;
                    foreach ($role_action as $u2) {
                        
                        if ($u2['action_Id'] == $v2['id']) {
                            $p = 1;
                        }
                       
                    }
                     $v2['is_assigned'] = $p;
                  $val['role_actions'][] =  $v2;
                }
                foreach ($allnotification as $v3) {
                    $p = 0;
                    foreach ($role_notify as $u2) {
                        
                        if ($u2['notification_Id'] == $v3['id']) {
                            $p = 1;
                        }
                        
                      
                    }
                      $v3['is_assigned'] = $p;
                     $val['role_notifications'][] = $v3;
                }
                $result['roles'][] = $val;
                
            }
        }
       $result['users_list'] = self::getEnabledUsersListWithoutPass();

         $return = array();
        $return['error'] = 0;
        $return['data'] = $result;
        return $return;
    }

    public static function getRolePages($id) {
        $q = "select * from roles_pages where role_Id=$id";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        return $rows;
    }

    public static function getRoleActions($id) {
        $q = "select * from roles_action where role_Id=$id";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        return $rows;
    }

    public static function getRoleNotifications($id) {
        $q = "select * from roles_notification where role_Id=$id ";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        return $rows;
    }

    public static function getAllRole() {

        $q = "Select * from roles";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);

        return $rows;
    }
   
    
    public static function assignUserRole($userid,$roleid) {
        $r_error = 1;
        $r_message = "";
        $q = "SELECT * FROM user_role WHERE user_Id =".$userid;
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'user_Id' => $userid,
                'role_Id' => $roleid
            );
            self::DBinsertQuery('user_role', $ins);
            $r_error = 0;
            $r_message = "User role assigned!!";
        } else {
            self::DBrunQuery( "UPDATE user_role SET role_Id = '$roleid' WHERE user_Id = $userid " );
            $r_error = 0;
            $r_message = "User role changed!!";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    // delete role pages
    public static function deleteRolePages($id) {
        $q = "DELETE FROM roles_pages WHERE role_Id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role actions
    public static function deleteRoleActions($id) {
        $q = "DELETE FROM roles_action WHERE role_Id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role notifications
    public static function deleteRoleNotifications($id) {
        $q = "DELETE FROM roles_notification WHERE role_Id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role users
    public static function deleteRoleUsers($id) {
        $q = "DELETE FROM user_role WHERE role_Id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role
    public static function deleteRole($id) {
        // remove all linked pages, actions, notification & users;
        self::deleteRolePages( $id );
        self::deleteRoleActions( $id );
        self::deleteRoleNotifications( $id );
        self::deleteRoleUsers( $id );
        $run = self::DBrunQuery( "DELETE FROM roles WHERE id=$id" );
        $return = array();
        $return['error'] = 0;
        $return['message'] = 'Role deleted!!';
        return $return;
    }
    
    

}

//new Roles();
?>
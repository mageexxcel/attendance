<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	require_once ("c-database.php");

	$q = "SELECT * FROM user_role";


	// $q = "SELECT 
	// 				users.*,
	// 				user_profile.*,
	// 				roles.id as role_id,
	// 				roles.name as role_name 
	// 				FROM users 
	// 				LEFT JOIN user_profile ON users.id = user_profile.user_Id 
	// 				LEFT JOIN user_role ON users.id = user_role.user_Id 
	// 				LEFT JOIN roles ON user_role.role_Id = roles.id 
	// 				where 
	// 				users.id = 313 ";

	$run = Database::DBrunQuery($q);
  $rows = Database::DBfetchRows($run);

  echo '<pre>';
  print_r( $rows );
  echo '</pre>';

?>
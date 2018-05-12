<?php
$SHOW_ERROR = true;
if( $SHOW_ERROR ){
    error_reporting(E_ALL);
    ini_set('display_errors', 1); 
} else{
    error_reporting(0);
    ini_set('display_errors', 0);
}

require_once ("c-hr.php");

$token = $_POST['token'];
$validateToken = HR::validateToken($token);
if ($validateToken == false) {
	echo "Login token expired please login again!!!";
	die;
} else {
	$loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
  $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
  $logged_user_id = $loggedUserInfo['id'];
}

function uploadFile($file){
	global $logged_user_id;
	$file_name = $file['name'];
	$file_size = $file['size'];
	$file_tmp = $file['tmp_name'];
	$file_type = $file['type'];
	$ext = pathinfo($file_name, PATHINFO_EXTENSION);
	$exten = array('pdf', 'jpeg', 'docx', 'doc', 'jpg', 'png');
	$upload_dir = "../uploaded_files/";
	$new_file_name = time() . '_' . $file_name;
	if (is_dir($upload_dir) && is_writable($upload_dir)) {
		if (in_array($ext, $exten)) {
			if (!move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
				echo "File Not uploaded";
				die;
			} else {
				// when file success fully uploaded insert details in files table
				$fileId = HR::addNewFile( $logged_user_id, $new_file_name );
				return $fileId;
			}
		} else {
			echo "Please upload the document in one of the format: PDF,Docx,doc,jpg,jpeg and png.";
			die;
		}	
	} else{
		echo 'Upload directory is not writable, or does not exist.';
		die;
	}
}

// redirect url 
$redirectUrl = $_POST['page_url'];

if( $_POST['file_upload_action'] ) {

	$fileUploadAction = $_POST['file_upload_action'];
	
	// INVENTORY FILES
	if($fileUploadAction == 'inventory_files' && isset($_POST['inventory_id']) && !empty($_POST['inventory_id']) ){
		$inventory_id = $_POST['inventory_id'];

		//if inventory_invoice file
		if( isset($_FILES['inventory_invoice']) && $_FILES['inventory_invoice']['error'] == 0 && $_FILES['inventory_invoice']['size'] > 0 ){
			$file_id = uploadFile( $_FILES['inventory_invoice'] );
			HR::updateInventoryFileInvoice( $logged_user_id, $inventory_id, $file_id  );
		}

		//if inventory_warranty file
		if( isset($_FILES['inventory_warranty']) && $_FILES['inventory_warranty']['error'] == 0 && $_FILES['inventory_warranty']['size'] > 0 ){
			$file_id = uploadFile( $_FILES['inventory_warranty'] );
			HR::updateInventoryFileWarranty( $logged_user_id, $inventory_id, $file_id );
		}

		//if inventory_photo file
		if( isset($_FILES['inventory_photo']) && $_FILES['inventory_photo']['error'] == 0 && $_FILES['inventory_photo']['size'] > 0 ){
			$file_id = uploadFile( $_FILES['inventory_photo'] );
			HR::updateInventoryFilePhoto( $logged_user_id, $inventory_id, $file_id );
		}
	}
}

header("Location: $redirectUrl");
exit;

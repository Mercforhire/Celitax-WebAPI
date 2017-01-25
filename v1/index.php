<?php

require_once '../include/DbHandler.php';
require_once '../include/EmailHandler.php';
require_once '../include/Utils.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_email = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 * The method authenticate() will be executed every time before doing any task 
 * related operations on database.
 */
function authenticate(\Slim\Route $route)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization']))
    {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key))
        {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access denied. Invalid Api key";
            echoRespnse(400, $response);
            $app->stop();
        }
        else
        {
            global $user_email;
            // get user primary key id
            $email = $db->getUserEmailByKey($api_key);
            if ($email != NULL)
            {
                $user_email = $email;
            }
            else
            {
                $response["error"] = true;
                $response["message"] = "No user account holds this Api key";
                echoRespnse(400, $response);
                $app->stop();
            }
        }
    }
    else
    {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is missing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT')
    {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field)
    {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0)
        {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error)
    {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email)
{
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - email, password, first_name, last_name, country
 */
$app->post('/register', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('email', 'password', 'first_name', 'last_name', 'country'));

    $response = array();

    // reading post params
    $email = $app->request->post('email');
    $password = $app->request->post('password');
    $first_name = $app->request->post('first_name');
    $last_name = $app->request->post('last_name');
    $country = $app->request->post('country');

    $email = trim($email);
    $password = trim($password);
    $first_name = trim($first_name);
    $last_name = trim($last_name);

    // validating email address
    validateEmail($email);

    $db = new DbHandler();
    $res = $db->createUser($email, $password, $first_name, $last_name, $country);

    if ($res == USER_CREATED_SUCCESSFULLY)
    {
        $userid = $db->getUserIDbyEmail($email);
        
        $result = $db->addExpirationDate($userid, 9999);
        
        $emailer = new EmailHandler();
        $emailer->sendWelcomeMail($email);
        
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else if ($res == USER_CREATE_FAILED)
    {
        $response["error"] = true;
        $response["message"] = "USER_CREATE_FAILED";
        echoRespnse(400, $response);
    }
    else if ($res == USER_ALREADY_EXIST)
    {
        $response["error"] = true;
        $response["message"] = "USER_ALREADY_EXIST";
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('email', 'password'));

    // reading post params
    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $response = array();

    $db = new DbHandler();
    // check for correct email and password
    $result = $db->checkLogin($email, $password);

    if ($result == USER_LOGIN_SUCCESS)
    {
        // get the user by email
        $user = $db->getUserByEmail($email);
        $useraccountinfo = $db->requestUserAccountInfo($email);

        if ($user != NULL && $useraccountinfo != NULL)
        {
            $response["error"] = false;
            $response['message'] = OPERATION_SUCCESS;
            
            $response['email'] = $email;
            $response['api_key'] = $user['api_key'];
            $response['first_name'] = $useraccountinfo['first_name'];
            $response['last_name'] = $useraccountinfo['last_name'];
            $response['country'] = $useraccountinfo['country'];
            echoRespnse(200, $response);
        }
        else
        {
            // unknown error occurred
            $response['error'] = true;
            $response['message'] = UNKNOWN_ERROR;
            echoRespnse(400, $response);
        }
    }
    else if ($result == USER_DOESNT_EXIST)
    {
        // user doesn't exist
        $response['error'] = true;
        $response['message'] = "USER_DOESNT_EXIST";
        echoRespnse(200, $response);
    }
    else if ($result == USER_PASSWORD_WRONG)
    {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = "USER_PASSWORD_WRONG";
        echoRespnse(200, $response);
    }
    else
    {
        // unknown error occurred
        $response['error'] = true;
        $response['message'] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * User Request password reset
 * url - /forgetpassword
 * method - post
 * params - email
 */
$app->post('/forgetpassword', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('email'));

    // reading post params
    $email = $app->request()->post('email');

    $emailer = new EmailHandler();

    $db = new DbHandler();
    
    $tokenid = $db->generateNewResetToken($email);

    $response = array();
    
    if ($tokenid != NULL)
    {
        $accountInfoArray = $db->getUserDetailsByEmail($email);
        $firstname = $accountInfoArray['first_name'];
        $lastname = $accountInfoArray['last_name'];

        $result = $emailer->sendPasswordResetEmailWithToken($email, $tokenid, $firstname, $lastname);
        $resultcode = $result->http_response_code;
        $response["error"] = false;
        $response["account"] = $accountInfoArray;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Sorry, this user account doesn't exist.";
        echoRespnse(200, $response);
    }
});


/**
 * ----------- METHODS WITH AUTHENTICATION ---------------------------------
 */

/**
 * method POST
 * params - data
 * url - /upload/
 */
$app->post('/upload', 'authenticate', function() use ($app)
{
    global $user_email;

    $response = array();

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $rawjson = $app->request->post('data');

    $dataDictionary = json_decode($rawjson, true);

    if ($dataDictionary != NULL)
    {
        $db = new DbHandler();

        if ($db->syncData($userid, $dataDictionary))
        {
            $dataBatchID = $db->getDataBatchID($userid);

            $response["error"] = false;
            $response["message"] = OPERATION_SUCCESS;
            $response["batchID"] = $dataBatchID;
            echoRespnse(200, $response);

            // Clean up the user's Photo Storage folder
            $receipts = $db->downloadReceipts($userid);

            // Get all the filenames from $receipts
            $filenames = array();

            foreach ($receipts as $receipt)
            {
                $filenameString = $receipt['filenames'];

                $filenamesInReceipt = explode(",", $filenameString);

                if (is_array($filenamesInReceipt) || is_object($filenamesInReceipt))
                {
                    foreach ($filenamesInReceipt as $filename)
                    {
                        $filenames[$filename . '.jpg'] = true;
                    }
                }
            }

            // Get all the filesnames of existing image files for current user
            $filenamesOnDisk = getListOfFilesOwnedBy($userid);

            if ($filenamesOnDisk != NULL)
            {
                // Store the filenames of files that don't also exist in $receipts
                $filenamesToDelete = array();
                foreach ($filenamesOnDisk as $filenameOnDisk)
                {
                    //echo 'Checking file: ' . $filenameOnDisk . PHP_EOL;
                    if (array_key_exists($filenameOnDisk, $filenames))
                    {
                        //echo $filenameOnDisk . ' found in database' . PHP_EOL;
                    }
                    else
                    {
                        if (startsWith($filenameOnDisk, 'Receipt'))
                        {
                            //echo $filenameOnDisk . ' not found in database' . PHP_EOL;
                            $filenamesToDelete[] = $filenameOnDisk;
                        }
                    }
                }

                // Delete $filenamesToDelete
                foreach ($filenamesToDelete as $filenameToDelete)
                {
                    //echo 'Deleting file: ' . $filenameToDelete . PHP_EOL;
                    deleteImage($filenameToDelete, $userid);
                }
            }
        }
        else
        {
            $response["error"] = true;
            $response["message"] = UNKNOWN_ERROR;
            echoRespnse(400, $response);
        }
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * method GET
 * url - /data_batchid/
 */
$app->get('/data_batchid', 'authenticate', function()
{
    $response = array();

    global $user_email;

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $dataBatchID = $db->getDataBatchID($userid);

    if ($dataBatchID != NULL)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        $response["batchID"] = $dataBatchID;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = false;
        $response["message"] = "USER_NO_DATA";
        echoRespnse(200, $response);
    }
});

/**
 * method GET
 * url - /download/
 */
$app->get('/download', 'authenticate', function()
{
    // check for required params

    $response = array();

    global $user_email;

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $dataDictionary = $db->downloadData($userid);

    $dataBatchID = $db->getDataBatchID($userid);

    if ($dataDictionary != NULL || $dataBatchID != NULL)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        $response["batchID"] = $dataBatchID;
        $response["data"] = $dataDictionary;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = false;
        $response["message"] = "USER_NO_DATA";
        echoRespnse(200, $response);
    }
});

/**
 * method POST
 * params - filename
 * url - /upload_photo/
 */
$app->post('/upload_photo', 'authenticate', function() use ($app)
{
    global $user_email;

    // check for required params
    verifyRequiredParams(array('filename'));

    $response = array();

    $filename = $app->request->post('filename');

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $uploadResult = storeUploadedReceipt($userid, $filename);

    if ($uploadResult == UPLOAD_SUCCESSFULLY)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else if ($uploadResult == UPLOAD_FAILED_TO_CREATE_FOLDER)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_FAILED_TO_CREATE_FOLDER";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_RECEIPT_NO_FILE_TO_UPLOAD)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_RECEIPT_NO_FILE_TO_UPLOAD";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_FAILED_TO_COPY_TO_FOLDER)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_FAILED_TO_COPY_TO_FOLDER";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_FOLDER_NOT_WRITABLE)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_FOLDER_NOT_WRITABLE";
        echoRespnse(400, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Add or replace a user's profile photo
 * method POST
 * url - /update_profile_photo/
 */
$app->post('/update_profile_photo', 'authenticate', function() use ($app)
{
    global $user_email;

    $response = array();

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $uploadResult = storeUploadedProfileImage($userid);

    if ($uploadResult == UPLOAD_SUCCESSFULLY)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else if ($uploadResult == UPLOAD_FAILED_TO_CREATE_FOLDER)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_FAILED_TO_CREATE_FOLDER";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_RECEIPT_NO_FILE_TO_UPLOAD)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_RECEIPT_NO_FILE_TO_UPLOAD";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_FAILED_TO_COPY_TO_FOLDER)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_FAILED_TO_COPY_TO_FOLDER";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_FOLDER_NOT_WRITABLE)
    {
        $response["error"] = true;
        $response["message"] = "UPLOAD_FOLDER_NOT_WRITABLE";
        echoRespnse(400, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Delete a user's profile photo
 * method POST
 * url - /delete_profile_photo/
 */
$app->post('/delete_profile_photo', 'authenticate', function() use ($app)
{
    global $user_email;

    $response = array();

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $deleteResult = deleteProfileImage($userid);

    if ($deleteResult == DELETE_PROFILE_IMAGE_SUCCESSFULLY)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "FILE_DELETE_FAILED";
        echoRespnse(400, $response);
    }
});

/**
 * method POST
 * params - filename
 * url - /request_file_url/
 */
$app->post('/request_file_url', 'authenticate', function() use ($app)
{
    global $user_email;

    // check for required params
    verifyRequiredParams(array('filename'));

    $response = array();

    $filename = $app->request->post('filename');

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $filePath = getImageFilePath($userid, $filename);

    if ($filePath != NULL)
    {
        $filePath = str_replace("../..", "", $filePath);
        $filePath = '/crave' . $filePath;
        $fileURL = path2url($filePath);

        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        $response["url"] = $fileURL;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "RECEIPT_IMAGE_FILE_NO_LONGER_EXIST";
        echoRespnse(200, $response);
    }
});

/**
 * method GET
 * url - /get_profile_image/
 */
$app->get('/get_profile_image', 'authenticate', function()
{
    global $user_email;

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $filePath = getProfileImageFilePath($userid);

    if ($filePath != NULL)
    {
        $filePath = str_replace("../..", "", $filePath);

        $filePath = '/crave' . $filePath;

        $fileURL = path2url($filePath);

        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        $response["url"] = $fileURL;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "PROFILE_IMAGE_FILE_DOESNT_EXIST";
        echoRespnse(200, $response);
    }
});

/**
 * method GET
 * url - /get_files_need_upload/
 */
$app->get('/get_files_need_upload', 'authenticate', function()
{
    $response = array();

    global $user_email;

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $receiptDictionaries = $db->downloadReceipts($userid);

    $allFilenames = array();

    foreach ($receiptDictionaries as $receiptDictionary)
    {
        $filenamesString = $receiptDictionary['filenames'];

        $filenames = explode(",", $filenamesString);

        $allFilenames = array_merge($allFilenames, $filenames);
    }

    $namesOfFilesNeedUpload = array();

    foreach ($allFilenames as $filename)
    {
        if (!checkIfImageFileExist($userid, $filename))
        {
            $namesOfFilesNeedUpload[] = $filename;
        }
    }

    $response["error"] = false;
    $response["message"] = OPERATION_SUCCESS;
    $response["files_need_upload"] = $namesOfFilesNeedUpload;
    echoRespnse(200, $response);
});

/**
 * Let the user to submit a feedback
 * method POST
 * url - /submit_feedback/
 */
$app->post('/submit_feedback', 'authenticate', function() use ($app)
{
    // check for required params
    $feedback_text = $app->request->post('feedback_text');

    global $user_email;

    $response = array();
    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $result = $db->newFeedback($userid, $feedback_text);
    
    $accountInfoArray = $db->getUserDetailsByEmail($user_email);
    $firstname = $accountInfoArray['first_name'];
    $lastname = $accountInfoArray['last_name'];
        
    $emailer = new EmailHandler();
    
    $emailer->notifyAppFeedback($firstname . " " . $lastname, $user_email, $feedback_text);

    if ($result)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Update user's account info
 * method POST
 * params - firstname
 * params - lastname
 * params - country
 * url - /update_location/
 */
$app->post('/update_account', 'authenticate', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('firstname', 'lastname', 'country'));

    $response = array();

    $db = new DbHandler();

    global $user_email;

    $userid = $db->getUserIDbyEmail($user_email);

    $firstname = $app->request->post('firstname');
    $lastname = $app->request->post('lastname');
    $country = $app->request->post('country');

    // creating new task
    $result = $db->changeUserAccountInfo($userid, $firstname, $lastname, $country);

    if ($result == UPDATE_ACCOUNT_SUCCESS)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Kill account, delete all user data
 * method POST
 * params - password
 * url - /kill_account/
 */
$app->post('/kill_account', 'authenticate', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('password'));

    //reading post params
    global $user_email;

    $password = $app->request()->post('password');

    $response = array();

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    // check for correct email and password
    $result = $db->checkLogin($user_email, $password);

    if ($result == USER_LOGIN_SUCCESS)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);

        // proceed to erase all account contents
        //1. delete all user's receipts files
        deleteImagesOfUser($userid);

        //2. delete user profile image
        deleteProfileImage($userid);

        //3. delete user tax years
        $db->deleteTaxYears($userid);

        //4. delete user categories
        $db->deleteCatagories($userid);

        //5. delete user receipts
        $db->deleteReceipts($userid);

        //6. delete user records
        $db->deleteRecords($userid);

        //7. delete user account
        $db->deleteUser($userid);
    }
    else
    {
        // wrong password
        $response['error'] = true;
        $response['message'] = "USER_PASSWORD_WRONG";
        echoRespnse(200, $response);
    }
});

/**
 * Change user password
 * method POST
 * params - old_password, new_password
 * url - /change_password/
 */
$app->post('/change_password', 'authenticate', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('old_password', 'new_password'));

    $old_password = $app->request()->post('old_password');
    $new_password = $app->request()->post('new_password');

    $response = array();

    $db = new DbHandler();

    global $user_email;

    $userid = $db->getUserIDbyEmail($user_email);

    $result = $db->changePassword($userid, $old_password, $new_password);

    if ($result == USER_CHANGE_PASSWORD_SUCCESS)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else if ($result == USER_CHANGE_PASSWORD_OLD_PASSWORD_WRONG)
    {
        $response["error"] = true;
        $response["message"] = "USER_PASSWORD_WRONG";
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Change user email
 * method POST
 * params - new_email
 * url - /change_email/
 */
$app->post('/change_email', 'authenticate', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('new_email'));

    $newEmail = $app->request()->post('new_email');

    $response = array();

    $db = new DbHandler();

    global $user_email;

    $userid = $db->getUserIDbyEmail($user_email);

    $result = $db->changeEmail($userid, $newEmail);

    if ($result == USER_CHANGE_EMAIL_SUCCESS)
    {
        $response["error"] = false;
        $response["message"] = OPERATION_SUCCESS;
        echoRespnse(200, $response);
    }
    else if ($result == USER_CHANGE_EMAIL_ALREADY_EXIST)
    {
        $response["error"] = true;
        $response["message"] = 'USER_CHANGE_EMAIL_ALREADY_EXIST';
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Add a new subscription expiration date for current user
 * method POST
 * params - expiration_date
 * url - /add_new_expiration_date/
 */
$app->post('/add_new_expiration_date', 'authenticate', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('number_of_month'));

    $number_of_month = $app->request()->post('number_of_month');

    $response = array();

    $db = new DbHandler();

    global $user_email;

    $userid = $db->getUserIDbyEmail($user_email);

    $result = $db->addExpirationDate($userid, $number_of_month);

    if ($result == ADD_EXPIRATION_DATE_SUCCESS)
    {
        $new_date = $db->getLastestExpirationDate($userid);
        
        $response["error"] = false;
        $response["expiration_date"] = $new_date;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = UNKNOWN_ERROR;
        echoRespnse(400, $response);
    }
});

/**
 * Get the lastest subscription expiration date for current user
 * method get
 * url - /get_expiration_date/
 */
$app->get('/get_expiration_date', 'authenticate', function()
{
    $response = array();

    $db = new DbHandler();

    global $user_email;

    $userid = $db->getUserIDbyEmail($user_email);

    $result = $db->getLastestExpirationDate($userid);

    if ($result != NULL)
    {
        $response["error"] = false;
        $response["expiration_date"] = $result;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = TRUE;
        $response["message"] = 'NO_EXPIRATION_DATE_EXIST';
        echoRespnse(200, $response);
    }
});

/**
 * method POST
 * params - year, tax year of the receipts we want to retrive
 * params - allReceipts, true for all receipts in that tax year, false for only selected receipts in that tax year
 * params - receiptIDs, IDs of the selected receipt
 */
$app->post('/request_receipts_info', 'authenticate', function() use ($app)
{
    global $user_email;

    // check for required params
    verifyRequiredParams(array('email'));
    verifyRequiredParams(array('year'));
    verifyRequiredParams(array('allReceipts')); 
    
    $email = $app->request->post('email');
    $taxyear = $app->request->post('year');
    $allReceipts = $app->request->post('allReceipts');
    
    $resultURL = "http://celitax.ca/receipt_view.php?email=$user_email&year=$taxyear&allreceipts=$allReceipts";
    
    if ($allReceipts != 1)
    {
        //http://celitax.ca/receipt_view.php?email=leonchn84%40gmail.com&year=2015&allreceipts=0&receiptIDs=E88CE3B2-5656-4E01-961D-BA4E632529B5,6A01248C-37B8-40C9-8894-03F7259E7B0B
        $receiptIDsString = $app->request->post('receiptIDs');
        
        $resultURL = $resultURL . "&receiptIDs=$receiptIDsString";
    }

    $db = new DbHandler();
    $accountInfoArray = $db->getUserDetailsByEmail($user_email);
    $firstname = $accountInfoArray['first_name'];
    $lastname = $accountInfoArray['last_name'];
        
    $emailer = new EmailHandler();
    
    $result = $emailer->sendReceiptDownloadLink($email, $resultURL, $firstname, $lastname);        
    
    $response["error"] = false;
    $response["resultURL"] = $resultURL;
    echoRespnse(200, $response);
});

/**
 * method POST
 * params - report, the JSON of the complete report data
 */
$app->post('/upload_report_info', 'authenticate', function() use ($app)
{
    global $user_email;

    // check for required params
    verifyRequiredParams(array('email'));
    verifyRequiredParams(array('report'));

    $response = array();

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $emailToSendTo = $app->request->post('email');
    $rawjson = $app->request->post('report');
    
    $reportid = $db->addDataReport($userid, $rawjson);
    
    if ($reportid != NULL)
    {
        //send email to user with link to open the data 
        $accountInfoArray = $db->getUserDetailsByEmail($user_email);
        $firstname = $accountInfoArray['first_name'];
        $lastname = $accountInfoArray['last_name'];

        $emailer = new EmailHandler();

        $resultURL = "http://celitax.ca/year_summary.php?email=$user_email&reportid=$reportid";

        $result = $emailer->sendYearSummaryLink($emailToSendTo, $resultURL, $firstname, $lastname);
        
        $response["error"] = false;
        $response["resultURL"] = $resultURL;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Failed to save data report";
        echoRespnse(400, $response);
    }
});

$app->run();
?>
<?php

require_once '../include/DbHandler.php';
//require_once '../include/EmailHandler.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_email = NULL;
$rootStorageFolder = '../../PhotosStorage/';

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
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - email, password, first_name, last_name, city, postal, counter
 */
$app->post('/register', function() use ($app)
{
    // check for required params
    verifyRequiredParams(array('email', 'password', 'first_name', 'last_name', 'city', 'postal_code', 'country'));

    $response = array();

    // reading post params
    $email = $app->request->post('email');
    $password = $app->request->post('password');
    $first_name = $app->request->post('first_name');
    $last_name = $app->request->post('last_name');
    $city = $app->request->post('city');
    $postal_code = $app->request->post('postal_code');
    $country = $app->request->post('country');

    $email = trim($email);
    $password = trim($password);
    $first_name = trim($first_name);
    $last_name = trim($last_name);

    // validating email address
    validateEmail($email);

    $db = new DbHandler();
    $res = $db->createUser($email, $password, $first_name, $last_name, $country, $city, $postal_code);

    if ($res == USER_CREATED_SUCCESSFULLY)
    {
        $response["error"] = false;
        $response["message"] = "You are successfully registered.";
        echoRespnse(200, $response);
    }
    else if ($res == USER_CREATE_FAILED)
    {
        $response["error"] = true;
        $response["message"] = "Oops! User create failed.";
        echoRespnse(400, $response);
    }
    else if ($res == USER_ALREADY_EXISTED)
    {
        $response["error"] = true;
        $response["message"] = "Sorry, this email already exists.";
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Oops! Unidentified error.";
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
            $response['email'] = $email;
            $response['api_key'] = $user['api_key'];

            $response['first_name'] = $useraccountinfo['first_name'];
            $response['last_name'] = $useraccountinfo['last_name'];
            $response['city'] = $useraccountinfo['city'];
            $response['postal_code'] = $useraccountinfo['postal_code'];
            $response['country'] = $useraccountinfo['country'];
            echoRespnse(200, $response);
        }
        else
        {
            // unknown error occurred
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
            echoRespnse(200, $response);
        }
    }
    else if ($result == USER_LOGIN_EMAIL_DOESNT_EXIST)
    {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = "Login failed. User does not exist";
        echoRespnse(200, $response);
    }
    else if ($result == USER_LOGIN_PASSWORD_WRONG)
    {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = "Login failed. Password incorrect";
        echoRespnse(200, $response);
    }
    else
    {
        // unknown error occurred
        $response['error'] = true;
        $response['message'] = "An error occurred. Please try again";
        echoRespnse(400, $response);
    }
});

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
            $response["message"] = "Sync successfully";
            $response["batchID"] = $dataBatchID;
            echoRespnse(200, $response);
        }
        else
        {
            $response["error"] = true;
            $response["message"] = "Failed to Sync. Please try again";
            echoRespnse(400, $response);
        }
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Error processing input JSON";
        echoRespnse(200, $response);
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
        $response["batchID"] = $dataBatchID;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Server has no data";
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
    verifyRequiredParams(array('numofphotos'));

    $response = array();

    global $user_email;

    $db = new DbHandler();

    $userid = $db->getUserIDbyEmail($user_email);

    $dataDictionary = $db->downloadData($userid);

    $dataBatchID = $db->getDataBatchID($userid);

    if ($dataDictionary != NULL)
    {
        $response["error"] = false;
        $response["batchID"] = $dataBatchID;
        $response["data"] = $dataDictionary;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Something wrong.";
        echoRespnse(400, $response);
    }
});

/**
 * method POST
 * params - photo
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

    if ($uploadResult == UPLOAD_RECEIPT_SUCCESSFULLY_COPIED_TO_STORAGE)
    {
        $response["error"] = false;
        $response["message"] = "File uploaded successfully";
        echoRespnse(200, $response);
    }
    else if ($uploadResult == UPLOAD_FAILED_TO_CREATE_STORAGE_FOLDER)
    {
        $response["error"] = true;
        $response["message"] = "Failed to create receipt storage folder";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_RECEIPT_NO_PHOTOS_UPLOADED)
    {
        $response["error"] = true;
        $response["message"] = "No images to upload";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_RECEIPT_FAILED_TO_COPY_TO_STORAGE)
    {
        $response["error"] = true;
        $response["message"] = "Failed to copy uploaded photos to storage";
        echoRespnse(400, $response);
    }
    else if ($uploadResult == UPLOAD_RECEIPT_STORAGE_FOLDER_NOT_WRITABLE)
    {
        $response["error"] = true;
        $response["message"] = "Storage folder is not writable";
        echoRespnse(400, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Some unidentified error";
        echoRespnse(400, $response);
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
        if (!checkIfFileExist($userid, $filename))
        {
            $namesOfFilesNeedUpload[] = $filename;
        }
    }
    
    $response["error"] = false;
    $response["files_need_upload"] = $namesOfFilesNeedUpload;
    echoRespnse(200, $response);
});

/**
 * Let the user to submit a feedback
 * method PUT
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

    if ($result)
    {
        $response["error"] = false;
        echoRespnse(200, $response);
    }
    else
    {
        $response["error"] = true;
        $response["message"] = "Failed to submit feedback.";
        echoRespnse(200, $response);
    }
});

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
 * Creatr the storage folder for the given user
 * @return Boolean Folder creation success/fail
 */
function createStorageFolder($userFolderPath)
{
    //create a directory named after the userid in rootStorageFolder
    if (!file_exists($userFolderPath))
    {
        $result = mkdir($userFolderPath, 0777, true);
        if ($result)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    else
    {
        //return true if the folder already exists
        return true;
    }
}

/**
 * Check to see if the file exist for user in its storage folder
 * @param Int $userid User id
 * @param Array $filenames Filenames in order
 * @return BOOLEAN true if exist, false otherwise
 */
function checkIfFileExist($userid, $filename)
{
    global $rootStorageFolder;

    $userFolderPath = $rootStorageFolder . $userid;
    
    $filename = $filename . '.jpg';
    
    $filePath = $userFolderPath . '/' . $filename;
    
    return file_exists($filePath);
}

/**
 * Copy the uploaded photo to the storage folder, with the correct file name
 * @param Int $userid User id
 * @param Array $filenames Filenames in order
 * @return Int upload success/fail status
 */
function storeUploadedReceipt($userid, $filename)
{
    global $rootStorageFolder;

    $userFolderPath = $rootStorageFolder . $userid;
    //$_FILES Array elements
    //$_FILES['file']['name'] - the name of the uploaded file (picture.jpg)
    //$_FILES['file']['type'] - the content type (image/jpeg)
    //$_FILES['file']['size'] - size in bytes
    //$_FILES['file']['tmp_name'] - name of temporary file store on server
    //$_FILES['file']['error'] - error code from uploading
    if (createStorageFolder($userFolderPath))
    {
        if (is_dir($userFolderPath) && is_writable($userFolderPath))
        {
            if ($_FILES['photos'])
            {
                $temp_uploaded_file = $_FILES['photos'];

                $filename = $filename . '.jpg';
                $filePath = $userFolderPath . '/' . $filename;

                if (is_uploaded_file($temp_uploaded_file['tmp_name']))
                {
                    if (move_uploaded_file($temp_uploaded_file['tmp_name'], $filePath))
                    {
                        return UPLOAD_RECEIPT_SUCCESSFULLY_COPIED_TO_STORAGE;
                    }
                }
                else
                {
                    return UPLOAD_RECEIPT_FAILED_TO_COPY_TO_STORAGE;
                }
            }
            else
            {
                return UPLOAD_RECEIPT_NO_PHOTOS_UPLOADED;
            }
        }
        else
        {
            return UPLOAD_RECEIPT_STORAGE_FOLDER_NOT_WRITABLE;
        }
    }
    else
    {
        return UPLOAD_FAILED_TO_CREATE_STORAGE_FOLDER;
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

$app->run();
?>
<?php

include_once 'Config.php';
/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Leon Chen
 */
//Data Action Enum
define('DataActionNone', 0);
define('DataActionInsert', 1);
define('DataActionUpdate', 2);
define('DataActionDelete', 3);


//Catagory Dictionary
define('kKeyCatagories', 'Catagories');

define('kKeyIdentifer', 'Identifer');
define('kKeyName', 'Name');
define('kKeyColor', 'Color');
define('kKeyNationalAverageCosts', 'NationalAverageCosts');
define('kKeyDataAction', 'DataAction');
define('kKeyUnitType', 'UnitType');

define('kKeyRed', 'Red');
define('kKeyGreen', 'Green');
define('kKeyBlue', 'Blue');

//Receipt Dictionary
define('kKeyReceipts', 'Receipts');

define('kKeyFileNames', 'FileNames');
define('kKeyDateCreated', 'DateCreated');
define('kKeyTaxYear', 'TaxYear');

//Record Dictionary
define('kKeyRecords', 'Records');

define('kKeyCatagoryID', 'CatagoryID');
define('kKeyReceiptID', 'ReceiptID');
define('kKeyAmount', 'Amount');
define('kKeyQuantity', 'Quantity');

//TaxYear Dictionary
define('kKeyTaxYears', 'TaxYears');

$rootStorageFolder = '../../PhotosStorage/';

class DbHandler
{

    private $conn;

    function __construct()
    {
        require_once 'DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey()
    {
        return md5(uniqid(rand(), true));
    }

    /* ------------- `users` table method ------------------ */

    public function requestUserAccountInfo($email)
    {
        $stmt = $this->conn->prepare("SELECT 
                                        `users`.`first_name`,
                                        `users`.`last_name`,
                                        `users`.`country`,
                                        `users`.`api_key`
                                    FROM `celitax`.`users`
                                    WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute())
        {
            $stmt->bind_result($first_name, $last_name, $country, $api_key);
            $stmt->fetch();
            $account = array(
                "first_name" => $first_name,
                "last_name" => $last_name,
                "country" => $country,
                "api_key" => $api_key
            );
            $stmt->close();
            return $account;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }

    /**
     * Creating new user
     */
    public function createUser($email, $password, $first_name, $last_name, $country)
    {
        // First check if user already existed in db
        if (!$this->isUserExists($email))
        {
            // Generating API key
            $api_key = $this->generateApiKey();

            $passwordhash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $this->conn->prepare('
                SET time_zone = `US/Eastern`
		');
            $stmt->execute();
            $stmt->close();

            // insert query
            $stmt = $this->conn->prepare(
                    'INSERT INTO users'
                    . '(email, password, api_key, first_name, last_name, country) '
                    . 'values(?, ?, ?, ?, ?, ?)');
            $stmt->bind_param("ssssss", $email, $passwordhash, $api_key, $first_name, $last_name, $country);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result)
            {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            }
            else
            {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        }
        else
        {
            // User with same email already existed in the db
            return USER_ALREADY_EXIST;
        }
    }

    /**
     * Checking user login
     * @return Int User login status 
     */
    public function checkLogin($email, $input_password)
    {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password FROM users WHERE email = ?");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        /* @var String $correct_password the correct password for this user */
        $stmt->bind_result($correct_password);
        $stmt->store_result();

        if ($stmt->num_rows > 0)
        {
            // Found user with the email, now verify the password

            $stmt->fetch();
            $stmt->close();

            if (password_verify($input_password, $correct_password))
            {
                // User password is correct
                return USER_LOGIN_SUCCESS;
            }
            else
            {
                // user password is incorrect
                return USER_PASSWORD_WRONG;
            }
        }
        else
        {
            $stmt->close();
            // user not existed with the email
            return USER_DOESNT_EXIST;
        }
    }

    /**
     * Fetching user personal info by email
     * @param String $email User email id
     */
    public function changeUserAccountInfo($userid, $firstname, $lastname, $country)
    {
        $stmt = $this->conn->prepare(
                "UPDATE users 
                 SET `first_name` = ?, `last_name` = ?, `country` = ?
                 WHERE `userid` = ?
                ");
        $stmt->bind_param("sssi", $firstname, $lastname, $country, $userid);
        if ($stmt->execute())
        {
            $stmt->close();
            return UPDATE_ACCOUNT_SUCCESS;
        }
        else
        {
            $stmt->close();
            return UPDATE_ACCOUNT_FAILED;
        }
    }

    /**
     * Checking for duplicate user by email address in user_accounts table
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email)
    {
        $stmt = $this->conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     * @return Dictionary containing userid,email,api_key
     */
    public function getUserByEmail($email)
    {
        $stmt = $this->conn->prepare("SELECT userid,email,api_key FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute())
        {
            $stmt->bind_result($userid, $email, $api_key);
            if ($stmt->fetch())
            {
                $result_user = array(
                    "userid" => $userid,
                    "email" => $email,
                    "api_key" => $api_key
                );
                
                $stmt->close();
                return $result_user;
            }
        }
        else
        {
            $stmt->close();
        }
        
        return NULL;
    }
    
    /**
     * Fetching user personal info by email
     * @param String $email User email id
     * @return Dictionary containing first_name,last_name,city,postal_code,gender
     */
    public function getUserDetailsByEmail($email)
    {
        $stmt = $this->conn->prepare("SELECT first_name,last_name,country FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute())
        {
            $stmt->bind_result($first_name, $last_name, $country);
            if ($stmt->fetch())
            {
                $result_user = array(
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "country" => $country
                );
            }
            $stmt->close();
            return $result_user;
        }
        else
        {
            return NULL;
        }
    }
    
    /**
     * Delete user by userid
     * @param String $userid userid
     * @return BOOLEAN
     */
    public function deleteUser($userid)
    {
        $stmt = $this->conn->prepare("DELETE FROM `celitax`.`users` WHERE userid = ?");
        $stmt->bind_param("i", $userid);
        if ($stmt->execute())
        {
            $stmt->close();
            return TRUE;
        }
        else
        {
            $stmt->close();
            return FALSE;
        }
    }
    
    public function changeEmail($userid, $newEmail) 
    {
        //check if $newEmail already exist as a user
        $result = $this->getUserByEmail($newEmail);
        
        if ($result != NULL)
        {
            return USER_CHANGE_EMAIL_ALREADY_EXIST;
        }
            
        $stmt = $this->conn->prepare('
                UPDATE `celitax`.`users`
                SET `email` = ?
                WHERE `userid` = ?
		');
        $stmt->bind_param("si", $newEmail, $userid);
        
        if ($stmt->execute())
        {
            $stmt->close();
            return USER_CHANGE_EMAIL_SUCCESS;
        }
        else
        {
            $stmt->close();
        }
        
        return USER_CHANGE_EMAIL_FAILED;
    }
    
    public function changePassword($userid, $originalPassword, $newPassword) 
    {
        //find the user email
        $email = $this->getUserEmailByID($userid);
        
        if ($email == NULL)
        {
            return USER_CHANGE_PASSWORD_FAILED;
        }

        //check if originalPassword is right
        $result = $this->checkLogin($email, $originalPassword);
        
        if ($result == USER_PASSWORD_WRONG) 
        {
            return USER_CHANGE_PASSWORD_OLD_PASSWORD_WRONG;
        } 
        else if ($result == USER_LOGIN_SUCCESS) 
        {
            $result = $this->setNewPasswordForUser($userid, $newPassword);
            
            if ($result) 
            {
                return USER_CHANGE_PASSWORD_SUCCESS;
            }
        } 
        
        return USER_CHANGE_PASSWORD_FAILED;
    }
    
    /**
     * @param String $tokenID Token ID of the user's password to change
     * @param String $newpassword Password to change to
     * @return boolean
     */
    public function setNewPasswordForTokenID($tokenID, $newpassword)
    {
        $email = $this->verifyTokenID($tokenID);
        
        if ($email == NULL)
        {
            return false;
        }
        
        $passwordhash = password_hash($newpassword, PASSWORD_BCRYPT);
        
        //modify the existing entry
        $stmt = $this->conn->prepare("
                UPDATE users SET 
                `password` = ?
                WHERE `email` = ?;
        	");
        
        $stmt->bind_param("ss", $passwordhash, $email);

        $result = $stmt->execute();

        $stmt->close();

        // Check for successful update
        if ($result)
        {
            // User successfully update
            return true;
        }
        else
        {
            // Failed to update user
            return false;
        }
    }
    
    public function setNewPasswordForUser($userid, $newpassword) 
    {
        $passwordhash = password_hash($newpassword, PASSWORD_BCRYPT);
        
        //modify the existing entry
        $stmt = $this->conn->prepare("
                UPDATE `celitax`.`users` SET 
                `password` = ?
                WHERE `userid` = ?;
        	");
        $stmt->bind_param("si", $passwordhash, $userid);

        $result = $stmt->execute();

        $stmt->close();

        // Check for successful update
        if ($result) 
        {
            return true;
        } else 
        {
            return false;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id)
    {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE userid = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute())
        {
            $stmt->bind_result($api_key);
            $stmt->fetch();
            $stmt->close();
            return $api_key;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }

    /**
     * Fetching user email by api key
     * @param String $api_key user api key
     */
    public function getUserEmailByKey($api_key)
    {
        $stmt = $this->conn->prepare("SELECT email FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute())
        {
            $stmt->bind_result($user_email);
            $stmt->fetch();
            $stmt->close();
            return $user_email;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }

    /**
     * Fetching user email by userid
     * @param String $userid userid
     */
    public function getUserEmailByID($userid)
    {
        $stmt = $this->conn->prepare("SELECT email FROM users WHERE userid = ?");
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->bind_result($user_email);
            $stmt->fetch();
            $stmt->close();
            return $user_email;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }

    /**
     * Fetching user id by email
     * @param String $email user email
     */
    public function getUserIDbyEmail($email)
    {
        $stmt = $this->conn->prepare("SELECT userid FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);

        if ($stmt->execute())
        {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            $stmt->close();
            return $user_id;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key)
    {
        $stmt = $this->conn->prepare("SELECT email FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
    
    /* ------------- `password_reset_tokens` table method ------------------ */

    /**
     * @param String $email email to check in db
     * @return boolean
     */
    private function isTokenForUserIDExists($userid)
    {
        $stmt = $this->conn->prepare("SELECT userid FROM password_reset_tokens WHERE userid=?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Add a new password reset token for a choosen user 
     * @param String $email Email of the user
     * @return String
     */
    public function generateNewResetToken($email)
    {
        //if this email doesn't exist, bail
        if (!$this->isUserExists($email))
        {
            return NULL;
        }

        //get userid of account
        $userid = $this->getUserIDbyEmail($email);

        //generate a tokenid
        if ($userid)
        {
            $tokenid = md5(1290 * 3 + $userid);
        }
        else
        {
            return NULL;
        }
            
        if ($this->isTokenForUserIDExists($userid))
        {
            //modify the existing entry
            $stmt = $this->conn->prepare("
                UPDATE password_reset_tokens SET 
                `tokenid` = ?, `created_at` = CURRENT_TIMESTAMP
                WHERE `userid` = ?;
        	");
            $stmt->bind_param("si", $tokenid, $userid);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful update
            if ($result)
            {
                // User successfully update
                return $tokenid;
            }
            else
            {
                // Failed to update token
                return NULL;
            }
        }
        else
        {
            // insert new query
            $stmt = $this->conn->prepare("
        	INSERT INTO password_reset_tokens (userid,tokenid) 
        	VALUES (?,?)
        	");
            $stmt->bind_param("is", $userid, $tokenid);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result)
            {
                // token successfully inserted
                return $tokenid;
            }
            else
            {
                // Failed to insert token
                return NULL;
            }
        }
    }
    
    /**
     * Verify that the given tokenid is valid(belongs to a user) and was 
     * generated within 4 hours.
     * @param String $tokenid Token ID of the password reset request
     * @return String Email of the owner of the token
     */
    public function verifyTokenID($tokenid)
    {
        $stmt = $this->conn->prepare("SELECT userid FROM password_reset_tokens WHERE tokenid=? AND created_at > (CURRENT_TIMESTAMP - INTERVAL 4 HOUR)");
        $stmt->bind_param("s", $tokenid);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($userid);
        $stmt->fetch();
        $num_rows = $stmt->num_rows;
        $stmt->close();

        if ($num_rows == 0)
        {
            return NULL;
        }
        
        $email = $this->getUserEmailByID($userid);
            
        return $email;
    }

    /* ------------- common method ------------------ */

    public function syncData($userid, $dataDictionary)
    {
        $catagoryDictionaries = $dataDictionary[kKeyCatagories];
        $receiptDictionaries = $dataDictionary[kKeyReceipts];
        $recordDictionaries = $dataDictionary[kKeyRecords];
        $taxYearDictionaries = $dataDictionary[kKeyTaxYears];

        $this->conn->autocommit(false);

        if ($this->modifyCatagories($userid, $catagoryDictionaries) == MODIFY_SUCCESS)
        {
            if ($this->modifyReceipts($userid, $receiptDictionaries) == MODIFY_SUCCESS)
            {
                if ($this->modifyRecords($userid, $recordDictionaries) == MODIFY_SUCCESS)
                {
                    if ($this->modifyTaxyear($userid, $taxYearDictionaries) == MODIFY_SUCCESS)
                    {
                        $this->conn->autocommit(true);

                        return true;
                    }
                    else
                    {
                        $this->conn->rollback();
                    }
                }
                else
                {
                    $this->conn->rollback();
                }
            }
            else
            {
                $this->conn->rollback();
            }
        }
        else
        {
            $this->conn->rollback();
        }

        return false;
    }

    public function getDataBatchID($userid)
    {
        $data = $this->downloadData($userid);

        if ($data != NULL)
        {
            return md5(serialize($data));
        }

        return NULL;
    }

    public function downloadData($userid)
    {
        $catagoryDictionaries = $this->downloadCatagories($userid);
        $receiptDictionaries = $this->downloadReceipts($userid);
        $recordDictionaries = $this->downloadRecords($userid);
        $taxYearDictionaries = $this->downloadTaxYears($userid);

        if (count($catagoryDictionaries) == 0 &&
                count($receiptDictionaries) == 0 &&
                count($recordDictionaries) == 0 &&
                count($taxYearDictionaries) == 0)
        {
            //no data

            return null;
        }

        $dataDictionaries = array();

        $dataDictionaries[kKeyCatagories] = $catagoryDictionaries;
        $dataDictionaries[kKeyReceipts] = $receiptDictionaries;
        $dataDictionaries[kKeyRecords] = $recordDictionaries;
        $dataDictionaries[kKeyTaxYears] = $taxYearDictionaries;

        return $dataDictionaries;
    }

    /* ------------- `catagory` table method ------------------ */

    /**

     */
    public function existsCatagory($userid, $identifier)
    {
        $stmt = $this->conn->prepare("SELECT catagoryid FROM catagories WHERE userid = ? AND identifier = ?");
        $stmt->bind_param("is", $userid, $identifier);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Go through $catagoryDictionaries, an array containing
     * dictionary of form:
      {
      "ServerID" : 0,
      "Identifer" : "0E35A5B1-62E8-4E01-B35F-D2CBF6D2E0A8",
      "Color" : {
      "Red" : 1,
      "Blue" : 0,
      "Green" : 1
      },
      "DataAction" : 1,
      "NationalAverageCost" : 2,
      "Name" : "Rice"
      }
     * 
     */
    public function modifyCatagories($userid, $catagoryDictionaries)
    {
        //check if user exists
        if ($this->getUserEmailByID($userid) == NULL)
        {
            return MODIFY_USER_NOT_EXIST;
        }

        if (count($catagoryDictionaries) == 0)
        {
            return MODIFY_SUCCESS;
        }

        $insertQuery = 'INSERT INTO `celitax`.`catagories`
                                            (`userid`,
                                             `identifier`,
                                             `name`,
                                             `color`,
                                             `national_average_cost`)
                                            VALUES
                                            (?,
                                             ?,
                                             ?,
                                             ?,
                                             ?);
                                            ';

        $modifiyQuery = 'UPDATE `catagories` SET 
                        `name` = ?,
                        `color` = ?,
                        `national_average_cost` = ?
                        WHERE `identifier` = ? AND `userid` = ?;';

        $deleteQuery = 'DELETE FROM `catagories` WHERE `identifier` = ? AND `userid` = ?';

        for ($i = 0; $i < count($catagoryDictionaries); $i++)
        {
            $catagoryDictionary = $catagoryDictionaries[$i];

            $identifier = $catagoryDictionary[kKeyIdentifer];
            $dataAction = $catagoryDictionary[kKeyDataAction];
            $catagoryName = $catagoryDictionary[kKeyName];
            $nationalAverageCost = $catagoryDictionary[kKeyNationalAverageCosts];
            $colorDictionary = $catagoryDictionary[kKeyColor];

            $colorRed = $colorDictionary[kKeyRed];
            $colorBlue = $colorDictionary[kKeyBlue];
            $colorGreen = $colorDictionary[kKeyGreen];

            $colorString = $colorRed . ',' . $colorBlue . ',' . $colorGreen;

            if ($dataAction == DataActionInsert)
            {
                //if a catagory with identifier already exists, ignore
                if ($this->existsCatagory($userid, $identifier))
                {
                    continue;
                }

                //add the new Catagory
                $stmt = $this->conn->prepare($insertQuery);

                $stmt->bind_param("issss", $userid, $identifier, $catagoryName, $colorString, $nationalAverageCost);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            else if ($dataAction == DataActionUpdate)
            {
                //if this catagory with identifier doesn't exist, ignore
                if (!$this->existsCatagory($userid, $identifier))
                {
                    continue;
                }

                $stmt = $this->conn->prepare($modifiyQuery);

                $stmt->bind_param("ssssi", $catagoryName, $colorString, $nationalAverageCost, $identifier, $userid);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            else if ($dataAction == DataActionDelete)
            {
                //if this catagory with identifier doesn't exist, ignore
                if ($this->existsCatagory($userid, $identifier) == FALSE)
                {
                    continue;
                }

                $stmt = $this->conn->prepare($deleteQuery);

                $stmt->bind_param("si", $identifier, $userid);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
        }

        return MODIFY_SUCCESS;
    }

    public function downloadCatagories($userid)
    {
        $catagories = array();

        $stmt = $this->conn->prepare(
                'SELECT `catagoryid`, `identifier`, `name`, `color`, `national_average_cost`
		FROM `catagories`
                WHERE `userid` = ?
		ORDER BY catagoryid
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->bind_result($catagoryid, $identifier, $name, $color, $national_average_cost);
            while ($stmt->fetch())
            {
                $catagory = array(
                    "catagoryid" => $catagoryid,
                    "identifier" => $identifier,
                    "name" => $name,
                    "color" => $color,
                    "national_average_cost" => $national_average_cost
                );
                $catagories[] = $catagory;
            }
            $stmt->close();
            return $catagories;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }
    
    public function deleteCatagories($userid)
    {
        $stmt = $this->conn->prepare(
                'DELETE FROM `celitax`.`catagories`
                WHERE `userid` = ?
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->close();
            return TRUE;
        }
        else
        {
            $stmt->close();
            return FALSE;
        }
    }

    /* ------------- `receipts` table method ------------------ */

    public function existsReceipt($userid, $identifier)
    {
        $stmt = $this->conn->prepare("SELECT receiptid FROM receipts WHERE userid = ? AND identifier = ?");
        $stmt->bind_param("is", $userid, $identifier);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
    
    public function getReceiptDateAndFilenames($userid, $receiptIDs)
    {
        $receiptInfos = array();
        
        foreach ($receiptIDs as $receiptID)
        {
            $stmt = $this->conn->prepare(
                'SELECT `date_created`, `filenames`
		FROM `receipts`
                WHERE `userid` = ? AND `identifier` = ?
		');
        
            $stmt->bind_param("is", $userid, $receiptID);

            if ($stmt->execute())
            {
                $stmt->bind_result($date_created, $filenames);

                if ( $stmt->fetch() )
                {
                    $receiptInfo = array(
                        "identifier" => $receiptID,
                        "date_created" => $date_created,
                        "filenames" => $filenames
                    );

                    $receiptInfos[] = $receiptInfo;
                }
                
                $stmt->close();
            }
        }
        
        return $receiptInfos;
    }
    
    public function getReceiptInfosOfTaxYear($userid, $taxYear)
    {
        $receiptInfos = array();
        
        $stmt = $this->conn->prepare(
                'SELECT `identifier`, `date_created`, `filenames`
		FROM `receipts`
                WHERE `userid` = ? AND `tax_year` = ?
                ORDER BY `date_created` DESC
		');
        $stmt->bind_param("ii", $userid, $taxYear);

        if ($stmt->execute())
        {
            $stmt->bind_result($receiptID, $date_created, $filenames);

            while ($stmt->fetch())
            {
                $receiptInfo = array(
                    "identifier" => $receiptID,
                    "date_created" => $date_created,
                    "filenames" => $filenames
                );

                $receiptInfos[] = $receiptInfo;
            }
        }

        $stmt->close();
        return $receiptInfos;
    }

    /**
     * Go through $receiptDictionaries, an array containing
     * dictionary of form:
      {
      "ServerID" : 0,
      "Identifer" : "F1563DC1-9618-4E0F-AF1A-00D9373E0BB6",
      "FileNames" : [
      "Receipt-248F1131-2776-472F-9C5E-C5B8C56DE785-1",
      "Receipt-BAC4BA4C-2CE0-4EE9-A056-B1F186CADEFB-2"
      ],
      "DateCreated" : "2013-07-01 05:29:00",
      "DataAction" : 1,
      "TaxYear" : 2014
      }
     * 
     */
    public function modifyReceipts($userid, $receiptDictionaries)
    {
        //check if user exists
        if ($this->getUserEmailByID($userid) == NULL)
        {
            return MODIFY_USER_NOT_EXIST;
        }

        if (count($receiptDictionaries) == 0)
        {
            return MODIFY_SUCCESS;
        }

        $insertQuery = 'INSERT INTO `celitax`.`receipts`
                                            (`userid`,
                                            `identifier`,
                                            `filenames`,
                                            `date_created`,
                                            `tax_year`)
                                            VALUES
                                            (?,
                                             ?,
                                             ?,
                                             ?,
                                             ?);
                                            ';

        $modifiyQuery = 'UPDATE `celitax`.`receipts`
                        SET
                        `filenames` = ?,
                        `tax_year` = ?
                        WHERE `identifier` = ? AND `userid` = ?;';

        $deleteQuery = 'DELETE FROM `receipts` WHERE `identifier` = ? AND `userid` = ?;';

        for ($i = 0; $i < count($receiptDictionaries); $i++)
        {
            $receiptDictionary = $receiptDictionaries[$i];

            $identifier = $receiptDictionary[kKeyIdentifer];
            $fileNames = $receiptDictionary[kKeyFileNames];
            $dataAction = $receiptDictionary[kKeyDataAction];
            $dataCreated = $receiptDictionary[kKeyDateCreated];
            $taxYear = $receiptDictionary[kKeyTaxYear];

            $fileNamesString;

            for ($index = 0; $index < count($fileNames); $index++)
            {
                if ($index == 0)
                {
                    $fileNamesString = $fileNames[$index];
                }
                else
                {
                    $fileNamesString = $fileNamesString . ',' . $fileNames[$index];
                }
            }

            if ($dataAction == DataActionInsert)
            {
                //if a Receipt with identifier already exists, ignore
                if ($this->existsReceipt($userid, $identifier))
                {
                    continue;
                }

                //add the new Receipt
                $stmt = $this->conn->prepare($insertQuery);

                $stmt->bind_param("isssd", $userid, $identifier, $fileNamesString, $dataCreated, $taxYear);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            //if Receipt already exist on server
            else if ($dataAction == DataActionUpdate)
            {
                //if a Receipt with identifier does not exist, ignore
                if ($this->existsReceipt($userid, $identifier) == false)
                {
                    continue;
                }

                $stmt = $this->conn->prepare($modifiyQuery);

                $stmt->bind_param("sdsi", $fileNamesString, $taxYear, $identifier, $userid);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            //if Receipt already exist on server
            else if ($dataAction == DataActionDelete)
            {
                //if a Receipt with identifier does not exist, ignore
                if ($this->existsReceipt($userid, $identifier) == false)
                {
                    continue;
                }

                $stmt = $this->conn->prepare($deleteQuery);

                $stmt->bind_param("si", $identifier, $userid);

                if ($stmt->execute())
                {
                    $stmt->close();

                    //Delete the associated images with the Receipt
                    $this->deleteImagesIn($fileNames, $userid);
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
        }

        return MODIFY_SUCCESS;
    }

    public function deleteImagesIn($fileNames, $userid)
    {
        global $rootStorageFolder;

        $userFolderPath = $rootStorageFolder . $userid;

        for ($index = 0; $index < count($fileNames); $index++)
        {
            $filename = $fileNames[$index];

            $filename = $filename . '.jpg';

            $filePath = $userFolderPath . '/' . $filename;

            if (file_exists($filePath))
            {
                unlink($filePath);
            }
        }
    }

    public function downloadReceipts($userid)
    {
        $receipts = array();

        $stmt = $this->conn->prepare(
                'SELECT `receiptid`, `identifier`, `filenames`, `date_created`, `tax_year`
		FROM `receipts`
                WHERE `userid` = ?
		ORDER BY receiptid
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->bind_result($receiptid, $identifier, $filenames, $date_created, $tax_year);
            while ($stmt->fetch())
            {
                $receipt = array(
                    "receiptid" => $receiptid,
                    "identifier" => $identifier,
                    "filenames" => $filenames,
                    "date_created" => $date_created,
                    "tax_year" => $tax_year
                );
                $receipts[] = $receipt;
            }
            $stmt->close();
            return $receipts;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }

    public function deleteReceipts($userid)
    {
        $stmt = $this->conn->prepare(
                'DELETE FROM `celitax`.`receipts`
                WHERE `userid` = ?
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->close();
            return TRUE;
        }
        else
        {
            $stmt->close();
            return FALSE;
        }
    }
    
    /* ------------- `records` table method ------------------ */

    public function existsRecord($userid, $identifier)
    {
        $stmt = $this->conn->prepare("SELECT recordid FROM records WHERE userid = ? AND identifier = ?");
        $stmt->bind_param("is", $userid, $identifier);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Go through $recordDictionaries, an array containing
     * dictionary of form:
      {
      "Quantity" : 10,
      "Identifer" : "6860440B-F049-4B55-99DD-A770640EBE9E",
      "ReceiptID" : "F1563DC1-9618-4E0F-AF1A-00D9373E0BB6",
      "Amount" : 8.6,
      "DataAction" : 1,
      "CatagoryID" : "A3046BCF-910A-4F7B-A122-400C3A962B6F",
      "ServerID" : 0
      }
     * 
     */
    public function modifyRecords($userid, $recordDictionaries)
    {
        //var_dump($recordDictionaries);
        //check if user exists
        if ($this->getUserEmailByID($userid) == NULL)
        {
            //var_dump('MODIFY_USER_NOT_EXIST');
            return MODIFY_USER_NOT_EXIST;
        }

        if (count($recordDictionaries) == 0)
        {
            return MODIFY_SUCCESS;
        }

        $insertQuery = 'INSERT INTO `records`
                                            (
                                            `userid`,
                                            `identifier`,
                                            `catagoryid`,
                                            `receiptid`,
                                            `amount`,
                                            `quantity`,
                                            `unit_type`)
                                            VALUES
                                            (?,
                                             ?,
                                             ?,
                                             ?,
                                             ?,
                                             ?,
                                             ?);
                                            ';

        $modifiyQuery = 'UPDATE `celitax`.`records`
                        SET
                        `catagoryid` = ?,
                        `receiptid` = ?,
                        `amount` = ?,
                        `quantity` = ?,
                        `unit_type` = ?
                        WHERE `identifier` = ? AND `userid` = ?;';

        $deleteQuery = 'DELETE FROM `records` WHERE `identifier` = ? AND `userid` = ?;';

        for ($i = 0; $i < count($recordDictionaries); $i++)
        {
            $receiptDictionary = $recordDictionaries[$i];

            $identifier = $receiptDictionary[kKeyIdentifer];
            $receiptID = $receiptDictionary[kKeyReceiptID];
            $catagoryID = $receiptDictionary[kKeyCatagoryID];
            $amount = $receiptDictionary[kKeyAmount];
            $quantity = $receiptDictionary[kKeyQuantity];
            $dataAction = $receiptDictionary[kKeyDataAction];
            $unitType = $receiptDictionary[kKeyUnitType];

            if ($dataAction == DataActionInsert)
            {
                //if a Record with identifier already exists, ignore
                if ($this->existsRecord($userid, $identifier))
                {
                    continue;
                }

                //add the new Record
                $stmt = $this->conn->prepare($insertQuery);

                $stmt->bind_param("isssdii", $userid, $identifier, $catagoryID, $receiptID, $amount, $quantity, $unitType);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            //if Record already exist on server
            else if ($dataAction == DataActionUpdate)
            {
                //if a Record with identifier does not exist, ignore
                if ($this->existsRecord($userid, $identifier) == false)
                {
                    continue;
                }
                
                $stmt = $this->conn->prepare($modifiyQuery);
                $stmt->bind_param("ssdiisi", $catagoryID, $receiptID, $amount, $quantity, $unitType, $identifier, $userid);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            //if Record already exist on server
            else if ($dataAction == DataActionDelete)
            {
                //if a Record with identifier does not exist, ignore
                if ($this->existsRecord($userid, $identifier) == false)
                {
                    continue;
                }

                $stmt = $this->conn->prepare($deleteQuery);

                $stmt->bind_param("si", $identifier, $userid);
                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
        }

        return MODIFY_SUCCESS;
    }

    public function downloadRecords($userid)
    {
        $records = array();

        $stmt = $this->conn->prepare(
                'SELECT `recordid`, `identifier`, `catagoryid`, `receiptid`, `amount`, `quantity`, `unit_type`
		FROM `records`
                WHERE `userid` = ?
		ORDER BY recordid
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->bind_result($recordid, $identifier, $catagoryid, $receiptid, $amount, $quantity, $unitType);
            while ($stmt->fetch())
            {
                $record = array(
                    "recordid" => $recordid,
                    "identifier" => $identifier,
                    "catagoryid" => $catagoryid,
                    "receiptid" => $receiptid,
                    "amount" => $amount,
                    "quantity" => $quantity,
                    "unit_type" => $unitType
                );
                $records[] = $record;
            }
            $stmt->close();
            return $records;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }
    
    public function deleteRecords($userid)
    {
        $stmt = $this->conn->prepare(
                'DELETE FROM `celitax`.`records`
                 WHERE `userid` = ?
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->close();
            return TRUE;
        }
        else
        {
            $stmt->close();
            return FALSE;
        }
    }

    /* ------------- `taxyears` table method ------------------ */

    public function existsTaxyear($userid, $taxyear)
    {
        $stmt = $this->conn->prepare("SELECT yearid FROM taxyears WHERE userid = ? AND year = ?");
        $stmt->bind_param("ii", $userid, $taxyear);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Go through $recordDictionaries, an array containing
     * dictionary of form:
      {
      "TaxYear" : 2013,
      "DataAction" : 1
      }
     * 
     */
    public function modifyTaxyear($userid, $taxyearDictionaries)
    {
        //check if user exists
        if ($this->getUserEmailByID($userid) == NULL)
        {
            return MODIFY_USER_NOT_EXIST;
        }

        if (count($taxyearDictionaries) == 0)
        {
            return MODIFY_SUCCESS;
        }

        $insertQuery = 'INSERT INTO `celitax`.`taxyears`
                                        (`userid`,
                                         `year`)
                                        VALUES
                                        (?,
                                         ?);
                                        ';

        $deleteQuery = 'DELETE FROM `taxyears` WHERE `userid` = ? AND `year` = ?;';

        for ($i = 0; $i < count($taxyearDictionaries); $i++)
        {
            $taxyearDictionary = $taxyearDictionaries[$i];

            $taxYear = $taxyearDictionary[kKeyTaxYear];
            $dataAction = $taxyearDictionary[kKeyDataAction];

            //DataAction must be Insert
            if ($dataAction == DataActionInsert)
            {
                //if a TaxYear with identifier already exists, ignore
                if ($this->existsTaxyear($userid, $taxYear))
                {
                    continue;
                }

                //add the new Record
                $stmt = $this->conn->prepare($insertQuery);
                $stmt->bind_param("ii", $userid, $taxYear);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            else if ($dataAction == DataActionDelete)
            {
                //add the new Record
                $stmt = $this->conn->prepare($deleteQuery);
                $stmt->bind_param("ii", $userid, $taxYear);

                if ($stmt->execute())
                {
                    $stmt->close();
                }
                else
                {
                    $stmt->close();
                    return MODIFY_FAILED;
                }
            }
            else
            {
                // ignore
            }
        }

        return MODIFY_SUCCESS;
    }

    public function downloadTaxYears($userid)
    {
        $taxyears = array();

        $stmt = $this->conn->prepare(
                'SELECT `year`
		FROM `taxyears`
                WHERE `userid` = ?
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->bind_result($taxyear);
            while ($stmt->fetch())
            {
                $taxyears[] = $taxyear;
            }
            $stmt->close();
            return $taxyears;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }
    
    public function deleteTaxYears($userid)
    {
        $stmt = $this->conn->prepare(
                'DELETE FROM `celitax`.`taxyears`
                WHERE `userid` = ?
		');
        $stmt->bind_param("i", $userid);

        if ($stmt->execute())
        {
            $stmt->close();
            return TRUE;
        }
        else
        {
            $stmt->close();
            return FALSE;
        }
    }

    /* ------------- `feedbacks` table method ------------------ */

    /**
     * return true for operation success, false for operation failure
     */
    public function newFeedback($userid, $feedback)
    {
        $stmt = $this->conn->prepare("INSERT INTO feedbacks(userid, feedback) values(?, ?)");
        $stmt->bind_param("is", $userid, $feedback);

        if ($stmt->execute())
        {
            $stmt->close();

            return true;
        }
        else
        {
            $stmt->close();
            
            return false;
        }
    }

    /* ------------- `subscription_ expiration_dates` table method ------------------ */

    /**
     * return true for operation success, false for operation failure
     */
    public function addExpirationDate($userid, $numberOfMonth)
    {
        $existingExpirationDate = $this->getLastestExpirationDate($userid);
       
        if ($existingExpirationDate != NULL)
        {
            $stmt = $this->conn->prepare("
            SELECT `expiration_date`
            FROM `subscription_expiration_dates`
            WHERE userid = ? AND `expiration_date` > curdate()
            ORDER BY subscriptionid DESC LIMIT 1;
            ");
            $stmt->bind_param("i", $userid);
            if ($stmt->execute())
            {
                $stmt->store_result();
                
                //If the user has an expiration date in the future, this function to generate a 
                //new expiration date that's X number of month after the previous expiration date.  
                if ($stmt->num_rows > 0)
                {
                    $stmt = $this->conn->prepare("
                        SET @lastest_date = ( SELECT `expiration_date`
                                              FROM `subscription_expiration_dates`
                                              WHERE userid = ? AND `expiration_date` > curdate()
                                              ORDER BY subscriptionid DESC LIMIT 1 );");
                    
                    $stmt->bind_param("i", $userid);

                    if ($stmt->execute())
                    {
                        $stmt = $this->conn->prepare("
                            INSERT INTO subscription_expiration_dates(userid, expiration_date) 
                            values(?, DATE_ADD(@lastest_date, INTERVAL ? MONTH) );");
                    
                        $stmt->bind_param("ii", $userid, $numberOfMonth);

                        if ($stmt->execute())
                        {
                            $stmt->close();

                            return ADD_EXPIRATION_DATE_SUCCESS;
                        }
                        else
                        {
                            $stmt->close();

                            return ADD_EXPIRATION_DATE_FAILED;
                        }
                    }
                    else
                    {
                        $stmt->close();

                        return ADD_EXPIRATION_DATE_FAILED;
                    }
                }
            }
            else
            {
                $stmt->close();
                return ADD_EXPIRATION_DATE_FAILED;
            }
        }
        
        //If the user doesn't have a expiration date at all or has one that's expired(in the past)
        //this function will generate a new expiration date that's X number of month after today
        
        $stmt = $this->conn->prepare("
            INSERT INTO subscription_expiration_dates(userid, expiration_date) 
            values(?, DATE_ADD(curdate(), INTERVAL ? MONTH) );");
        $stmt->bind_param("ii", $userid, $numberOfMonth);

        if ($stmt->execute())
        {
            $stmt->close();

            return ADD_EXPIRATION_DATE_SUCCESS;
        }
        else
        {
            $stmt->close();
            
            return ADD_EXPIRATION_DATE_FAILED;
        }
    }
    
    /**
     * return true for operation success, false for operation failure
     */
    public function getLastestExpirationDate($userid)
    {
        $stmt = $this->conn->prepare("
            SELECT `expiration_date`
            FROM `subscription_expiration_dates`
            WHERE userid = ?
            ORDER BY subscriptionid DESC LIMIT 1;
            ");
        $stmt->bind_param("i", $userid);
        if ($stmt->execute())
        {
            $stmt->bind_result($expiration_date);
            $stmt->fetch();
            $stmt->close();
            return $expiration_date;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }
    
    /* ------------- `saved_reports` table methods ------------------ */
    
    public function getDataReport($userid, $reportid)
    {
        //grab the lastest report ID and return it
        $stmt = $this->conn->prepare("
            SELECT data FROM `saved_reports` 
            WHERE userid = ? AND reportid = ?
            ");
        $stmt->bind_param("ii", $userid, $reportid);
        if ($stmt->execute())
        {
            $stmt->bind_result($data);
            $stmt->fetch();
            $stmt->close();
            return $data;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }
    
    public function addDataReport($userid, $data)
    {
        //find how many existing save data reports are there for this user
        $num_rows = 0;
        
        $stmt = $this->conn->prepare("SELECT userid FROM saved_reports WHERE userid = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        
        if ($num_rows >= 5)
        {
            //delete the oldest data report for this user if there are 5 or more existing reports for the user
            $stmt = $this->conn->prepare("
            DELETE FROM `saved_reports` 
            WHERE reportid IS NOT NULL 
            ORDER BY reportid ASC 
            LIMIT 1;
            ");
            if (!$stmt->execute())
            {
                //delete failed
                $stmt->close();
                return NULL;
            }
        }
        
        //add the new data report for this user
        $stmt = $this->conn->prepare("
            INSERT INTO `celitax`.`saved_reports`
            (`userid`,
             `data`)
            VALUES
            (?,
             ?);
            ");
        $stmt->bind_param("is", $userid, $data);
        if ($stmt->execute())
        {
            //grab the lastest report ID and return it
            $stmt = $this->conn->prepare("
            SELECT reportid FROM `saved_reports` 
            WHERE reportid IS NOT NULL AND userid = ? 
            ORDER BY reportid DESC 
            LIMIT 1;
            ");
            $stmt->bind_param("i", $userid);
            if ($stmt->execute())
            {
                $stmt->bind_result($reportID);
                $stmt->fetch();
                $stmt->close();
                return $reportID;
            }
            else
            {
                $stmt->close();
                return NULL;
            }

            $stmt->close();
            return NULL;
        }
        else
        {
            $stmt->close();
            return NULL;
        }
    }
}

?>
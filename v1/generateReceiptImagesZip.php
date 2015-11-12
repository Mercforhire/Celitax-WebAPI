<?php

header('Access-Control-Allow-Origin: *');

require_once '../include/DbHandler.php';
require_once '../include/Utils.php';

if (isset($_POST['email']) && isset($_POST['year']) && isset($_POST['receiptIDs']))
{
    $email = $_POST['email'];
    $year = $_POST['year'];
    $receiptIDsString = $_POST['receiptIDs'];
    
    $db = new DbHandler();
    
    $userid = $db->getUserIDbyEmail($email);
    
    $receiptIDs = explode(",", $receiptIDsString);
        
    // for each Receipt ID, we need its receipt creation date, and receipt images filenames
    $receiptsDateAndFilenames = $db->getReceiptDateAndFilenames($userid, $receiptIDs);
    
    $receiptInfos = array();
    
    //delete all temp files for this user first
    deleteTempFolderForUser($userid);
    
    if (is_array($receiptsDateAndFilenames))
    {
        // process each convert filename in receiptInfo to the actual URL
        foreach ($receiptsDateAndFilenames as $receiptDateAndFilenames)
        {
            $receiptImagesURLs = array();

            $filenamesString = $receiptDateAndFilenames['filenames'];
            $filenames = explode(",", $filenamesString);

            $i = 0;
            
            foreach ($filenames as $filename)
            {
                $copySuccess = false;
                
                if ( createReceiptFolder($userid, $receiptDateAndFilenames['date_created']) )
                {
                    if ( copyReceiptImage($userid, $receiptDateAndFilenames['date_created'], $filename, $i) )
                    {
                        $copySuccess = true;
                    }
                }
                
                $i++;
            }
        }
        
        echo zipReceipts($userid);
    }
    else
    {
        echo "ERROR";
    }
}
else
{
    echo "ERROR";
}
    
?>
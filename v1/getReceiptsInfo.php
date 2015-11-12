<?php

header('Access-Control-Allow-Origin: *');

require_once '../include/DbHandler.php';
require_once '../include/Utils.php';

if (isset($_POST['email']) && isset($_POST['year']) && isset($_POST['allReceipts']))
{
    $email = $_POST['email'];
    $year = $_POST['year'];
    $allReceipts = $_POST['allReceipts'];
    
    $receiptsDateAndFilenames = NULL;
    
    $db = new DbHandler();
    
    $userid = $db->getUserIDbyEmail($email);
    
    if ($allReceipts != 1)
    {
        if (isset($_POST['receiptIDs']))
        {
            $receiptIDsString = $_POST['receiptIDs'];
            $receiptIDs = explode(",", $receiptIDsString);
        
            // for each Receipt ID, we need its receipt creation date, and receipt images filenames
            $receiptsDateAndFilenames = $db->getReceiptDateAndFilenames($userid, $receiptIDs);
        }
        else
        {
            //error
            echo "ERROR";
            die();
        }
    }
    else
    {
        // for each Receipt ID, we need its receipt creation date, and receipt images filenames
        $receiptsDateAndFilenames = $db->getReceiptInfosOfTaxYear($userid, $year);
    }
    
    $receiptInfos = array();
    
    if (is_array($receiptsDateAndFilenames))
    {
        // process each convert filename in receiptInfo to the actual URL
        foreach ($receiptsDateAndFilenames as $receiptDateAndFilenames)
        {
            $receiptImagesURLs = array();
            $receiptImagesDimensions = array();

            $filenamesString = $receiptDateAndFilenames['filenames'];
            $filenames = explode(",", $filenamesString);

            foreach ($filenames as $filename)
            {
                $filePath = getImageFilePath($userid, $filename);

                if ($filePath != NULL)
                {
                    $filePath = str_replace("../..", "", $filePath);
                    $filePath = '/crave' . $filePath;
                    $fileURL = path2url($filePath);

                    $receiptImagesURLs[] = $fileURL;
                    
                    list($width, $height) = getimagesize(path2url($filePath));
                    
                    $receiptImagesDimensions[] = [$width, $height];
                }
            }

            $receiptInfo = array(
                "identifier" => $receiptDateAndFilenames['identifier'],
                "date_created" => $receiptDateAndFilenames['date_created'],
                "imageURLs" => $receiptImagesURLs,
                "imageDimensions" => $receiptImagesDimensions
            );
            
            $receiptInfos[] = $receiptInfo;
        }
        
        echo json_encode($receiptInfos, JSON_PRETTY_PRINT);
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
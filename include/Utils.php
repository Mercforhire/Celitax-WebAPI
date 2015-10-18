<?php

/* 
 * Put all Misc functions here for DBHandler.php and index.php to access
 */

$rootStorageFolder = '../../PhotosStorage/';
$profileImagesFolder = '../../ProfileImages/';
$tempFolder = '../../Temp/';

/**
 * Create the storage folder for the given user
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
                        return UPLOAD_SUCCESSFULLY;
                    }
                }
                else
                {
                    return UPLOAD_FAILED_TO_COPY_TO_FOLDER;
                }
            }
            else
            {
                return UPLOAD_RECEIPT_NO_FILE_TO_UPLOAD;
            }
        }
        else
        {
            return UPLOAD_FOLDER_NOT_WRITABLE;
        }
    }
    else
    {
        return UPLOAD_FAILED_TO_CREATE_FOLDER;
    }
}

/**
 * Copy the uploaded photo to the profile image folder, with the correct file name
 * @param Int $userid User id
 * @return Int upload success/fail status
 */
function storeUploadedProfileImage($userid)
{
    global $profileImagesFolder;

    //$_FILES Array elements
    //$_FILES['file']['name'] - the name of the uploaded file (picture.jpg)
    //$_FILES['file']['type'] - the content type (image/jpeg)
    //$_FILES['file']['size'] - size in bytes
    //$_FILES['file']['tmp_name'] - name of temporary file store on server
    //$_FILES['file']['error'] - error code from uploading
    if (createStorageFolder($profileImagesFolder))
    {
        if (is_dir($profileImagesFolder) && is_writable($profileImagesFolder))
        {
            if ($_FILES['photos'])
            {
                $temp_uploaded_file = $_FILES['photos'];

                $filename = 'USER_' . $userid . '.jpg';
                $filePath = $profileImagesFolder . '/' . $filename;

                if ( is_uploaded_file($temp_uploaded_file['tmp_name']) )
                {
                    if( file_exists($filePath) ) 
                    {
                        chmod($filePath,0755); //Change the file permissions if allowed
                        unlink($filePath); //remove the file
                    }

                    if (move_uploaded_file($temp_uploaded_file['tmp_name'], $filePath))
                    {
                        return UPLOAD_SUCCESSFULLY;
                    }
                    else
                    {
                        return UPLOAD_FAILED_TO_COPY_TO_FOLDER;
                    }
                }
                else
                {
                    return UPLOAD_FAILED_TO_COPY_TO_FOLDER;
                }
            }
            else
            {
                return UPLOAD_RECEIPT_NO_FILE_TO_UPLOAD;
            }
        }
        else
        {
            return UPLOAD_FOLDER_NOT_WRITABLE;
        }
    }
    else
    {
        return UPLOAD_FAILED_TO_CREATE_FOLDER;
    }
}

/**
 * Check to see if the file exist for user in its storage folder
 * @param Int $userid User id
 * @param Array $filenames Filenames in order
 * @return BOOLEAN true if exist, false otherwise
 */
function checkIfImageFileExist($userid, $filename)
{
    global $rootStorageFolder;

    $userFolderPath = $rootStorageFolder . $userid;

    $filename = $filename . '.jpg';

    $filePath = $userFolderPath . '/' . $filename;

    return file_exists($filePath);
}

/**
 * Return the list of all image files owned by userid
 * @param Int $userid User id
 * @return BOOLEAN true if exist, false otherwise
 */
function getListOfFilesOwnedBy($userid)
{
    global $rootStorageFolder;

    $userFolderPath = $rootStorageFolder . $userid;

    if (file_exists($userFolderPath))
    {
        $files = scandir($userFolderPath);

        if (count($files) > 0)
        {
            return $files;
        }
    }

    return NULL;
}

function getProfileImageFilePath($userid)
{
    global $profileImagesFolder;

    $filename = 'USER_' . $userid . '.jpg';

    $filePath = $profileImagesFolder . $filename;

    if (file_exists($filePath))
    {
        return $filePath;
    }
    else
    {
        return NULL;
    }
}

function getImageFilePath($userid, $filename)
{
    global $rootStorageFolder;

    $userFolderPath = $rootStorageFolder . $userid;

    $filename = $filename . '.jpg';

    $filePath = $userFolderPath . '/' . $filename;

    if (file_exists($filePath))
    {
        return $filePath;
    }
    else
    {
        return NULL;
    }
}

function deleteImagesOfUser($userid)
{
    $filesOwnedByUser = getListOfFilesOwnedBy($userid);

    if ($filesOwnedByUser == NULL)
    {
        return;
    }
    
    // Delete $filenamesToDelete
    foreach ($filesOwnedByUser as $filenameToDelete)
    {
        if (startsWith($filenameToDelete, 'Receipt'))
        {
            deleteImage($filenameToDelete, $userid);
        }
    }
}

/**
 * Delete the user's profile image
 * @param Int $userid User id
 * @return Int delete success/fail status
 */
function deleteProfileImage($userid)
{
    global $profileImagesFolder;
    
    if (createStorageFolder($profileImagesFolder))
    {
            $filename = 'USER_' . $userid . '.jpg';
            $filePath = $profileImagesFolder . '/' . $filename;

            if (file_exists($filePath))
            {
                chmod($filePath, 0755); //Change the file permissions if allowed

                //remove the file
                if ( !unlink($filePath) )
                {
                    return DELETE_PROFILE_IMAGE_FAILED;
                }
            }
    }
    
    return DELETE_PROFILE_IMAGE_SUCCESSFULLY;
}

function startsWith($haystack, $needle) 
{
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

function deleteImage($filename, $userid)
{
    global $rootStorageFolder;

    $userFolderPath = $rootStorageFolder . $userid;

    $filePath = $userFolderPath . '/' . $filename;

    if (file_exists($filePath))
    {
        unlink($filePath);
    }
}

function path2url($file, $Protocol = 'https://')
{
    return $Protocol . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
}

function createReceiptFolder($userid, $receiptCreationDate)
{
    global $tempFolder;
    
    if ( createStorageFolder($tempFolder))
    {
        $userTempFolderPath = $tempFolder . $userid;
        
        if ( createStorageFolder($userTempFolderPath) )
        {
            $folderName = str_replace(" ", "_", $receiptCreationDate);
            
            $folderName = str_replace(":", "_", $folderName);
            
            $receiptFolderPath = $userTempFolderPath . '/' . $folderName;
            
            if ( createStorageFolder($receiptFolderPath) )
            {
                return true;
            }
        }
    }
    
    return false;
}

function deleteTempFolderForUser($userid)
{
    global $tempFolder;
    
    $userTempFolderPath = $tempFolder . $userid;
    
    if ( createStorageFolder($userTempFolderPath))
    {
        //delete the $userTempFolderPath
        $it = new RecursiveDirectoryIterator($userTempFolderPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
        {
            if ($file->isDir())
            {
                rmdir($file->getRealPath());
            }
            else
            {
                unlink($file->getRealPath());
            }
        }
        rmdir($userTempFolderPath);
    }
}

function copyReceiptImage($userid, $receiptCreationDate, $filename)
{
    global $tempFolder;
    
    $userTempFolderPath = $tempFolder . $userid;
    
    $folderName = str_replace(" ", "_", $receiptCreationDate);

    $folderName = str_replace(":", "_", $folderName);

    $receiptFolderPath = $userTempFolderPath . '/' . $folderName;

    if (is_dir($receiptFolderPath) && is_writable($receiptFolderPath))
    {
        //copy the file from '../../PhotosStorage/' to ''../../Temp/[USERID]/[RECEIPTID]/';
        $originalFilepath = getImageFilePath($userid, $filename);
        
        if ($originalFilepath != NULL)
        {
            //TODO: rename filename to something more user friendly
            $filenameComponents = explode("-", $filename);
            
            // sample: Receipt-D2C0F9E2-67AF-4E54-A421-A8BC5B05EABA-1
            
            $receiptNumber = $filenameComponents[count($filenameComponents) - 1];
            
            $filename = 'Receipt-' . $receiptNumber . '.jpg';

            $filePath = $receiptFolderPath . '/' . $filename;
    
            copy($originalFilepath, $filePath);
            
            return true;
        }
    }
    
    return false;
}

function zipReceipts($userid)
{
    global $tempFolder;
    
    $userTempFolderPath = $tempFolder . $userid;
    
    if (is_dir($userTempFolderPath) && is_writable($userTempFolderPath))
    {
        //zip up $userTempFolderPath and all its contents and put it in $tempFolder
        
        $rootPath = $tempFolder;
        
        $zipFilename = 'ReceiptImagesForUser-' . $userid . '.zip';

        if (file_exists($rootPath . $zipFilename))
        {
            chmod($rootPath . $zipFilename, 0755); //Change the file permissions if allowed
            
            //remove the file
            unlink($rootPath . $zipFilename);
        }

        // define some basics
        $archiveName = $rootPath . $zipFilename;

        if (extension_loaded('zip'))
        {
            if (file_exists($userTempFolderPath))
            {
                $zip = new ZipArchive();
                if ($zip->open($archiveName, ZIPARCHIVE::CREATE))
                {
                    $source = $userTempFolderPath;
                    
                    if (is_dir($source))
                    {
                        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
                        
                        foreach ($files as $file)
                        {
                            if (is_dir($file))
                            {
                                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                            }
                            else if (is_file($file))
                            {
                                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                            }
                        }
                    }
                    else if (is_file($source))
                    {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }
                return $zip->close();
            }
        }
        return false;
    }
}

?>
<?php

header('Access-Control-Allow-Origin: *');

require_once '../include/DbHandler.php';

if (isset($_POST['email']) && isset($_POST['reportid']))
{
    $email = $_POST['email'];
    $reportid = $_POST['reportid'];
    
    $db = new DbHandler();
    
    $userid = $db->getUserIDbyEmail($email);
    
    if ($userid == null)
    {
        echo "ERROR";
        die();
    }
    
    $data = $db->getDataReport($userid, $reportid);
    
    if ($data == null)
    {
        echo "ERROR";
        die();
    }
    
    echo $data;
}
else
{
    echo "ERROR";
}
    
?>
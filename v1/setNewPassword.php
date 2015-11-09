<?php

header('Access-Control-Allow-Origin: *');

require_once '../include/DbHandler.php';

if (isset($_POST['tokenid']) && isset($_POST['password']))
{
    $tokenid = $_POST['tokenid'];
    $password = $_POST['password'];

    $db = new DbHandler();

    if ($db->setNewPasswordForTokenID($tokenid, $password))
    {
        echo CHANGE_PASSWORD_SUCCESS;
    }
    else
    {
        echo CHANGE_PASSWORDT_FAILURE;
    }
}
else
{
    echo CHANGE_PASSWORDT_FAILURE;
}

?>
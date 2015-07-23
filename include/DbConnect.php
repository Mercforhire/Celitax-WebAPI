<?php

/**
 * Handling database connection
 *
 * @author Leon Chen
 */
class DbConnect
{

    private $conn;

    function __construct()
    {
        
    }

    /**
     * Establishing database connection
     * @return database connection handler
     */
    function connect()
    {
        include_once 'Config.php';

        // Connecting to mysql database
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

        // Check for database connection error
        if (mysqli_connect_errno())
        {
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }

        // returing connection resource
        return $this->conn;
    }

}

?>

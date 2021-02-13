<?php

// [REF: https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]

class Connection
{
    private $connection;

    /**
     * @return mysqli
     * Attempts to establish a connection to a MYSQLI database using given database parameters.
     * If the connection fails the corresponding error message is returned.
     */
    function connect()
    {
        $this->connection = new mysqli("localhost", "root", "", "tennisladder");
        if (mysqli_connect_errno())
        {
            echo "Connection failed: " . mysqli_connect_error();
        }
        return $this->connection;
    }
}
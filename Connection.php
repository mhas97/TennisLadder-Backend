<?php

// [REF: https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]

class Connection
{
    /**
     * @return mysqli
     * Attempts to establish a connection to a MYSQLI database using given database parameters.
     * If the connection fails, output the corresponding error.
     */
    function connect()
    {
        $connection = new mysqli("localhost", "root", "", "tennisladder");
        if (mysqli_connect_errno())
        {
            echo "Connection failed: " . mysqli_connect_error();
        }
        return $connection;
    }
}
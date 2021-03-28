<?php

/**
 * Class Connection
 * Returns the connection to the tennis_database in the form of a mysqli object. In the case
 * of an error, returns its code.
 *
 * The structure of this API is discussed in the following article:
 * https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial
 */
class Connection
{
    /**
     * @return mysqli
     * Attempts to establish a connection to a MYSQLI database using given database parameters.
     * If the connection fails, output the corresponding error.
     */
    function connect(): mysqli {
        $connection = new mysqli("localhost", "root", "", "tennisladder");
        if (mysqli_connect_errno())  {
            echo "Connection failed: " . mysqli_connect_error();
        }
        return $connection;
    }
}
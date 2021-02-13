<?php

// [REF: https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]

class Action
{
    private $connection;

    /**
     * Action constructor.
     * Obtains a connection object via the Connection class.
     */
    function __construct()
    {
        require_once dirname(__FILE__) . "/Connection.php";
        $db = new Connection();
        $this->connection = $db->connect();
    }

    function login($email, $password)
    {
        $statement = $this->connection->prepare("SELECT password FROM player WHERE email = ?");
        $statement->bind_param("s",  $email);
        $statement->execute();
        $statement->bind_result($hashed_password);
        $statement->fetch();
        if (password_verify($password, $hashed_password))
        {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * Add a new player to the database.
     * Binds given parameters to SQL statement and attempts to execute.
     * Returns success status.
     */
    function create_player($email, $password, $contactno, $fname, $lname, $clubname)
    {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $statement = $this->connection->prepare("INSERT INTO player (email, password, contactno, fname, lname, clubid) VALUES (?, ?, ?, ?, ?, (SELECT clubid from club WHERE name = ?))");
        $statement->bind_param("sssssi", $email, $hashed_password, $contactno, $fname, $lname, $clubname);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * Add a new club to the database.
     * Binds given parameters to SQL statement and attempts to execute.
     * Returns success status.
     */
    function create_club($name, $address)
    {
        $statement = $this->connection->prepare("INSERT INTO club (name, clubid) VALUES (?,?)");
        $statement->bind_param("ss", $name, $address);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }

    /**
     * @return array
     * Retrieves player info for a given ID.
     * This will likely be used upon signing in to fetch the users info.
     */
    function get_player_data($playerid)
    {
        $statement = $this->connection->prepare("SELECT email, password, contactno, fname, lname, clubid, elo FROM player WHERE playerid = ?");
        $statement->bind_param("i", $playerid);
        $statement->execute();
        $statement->bind_result($email, $password, $contactno, $fname, $lname, $clubid, $elo);
        $statement->fetch();
        $player = array();
        $player["email"] = $email;
        $player["password"] = $password;
        $player["contactno"] = $contactno;
        $player["fname"] = $fname;
        $player["lname"] = $lname;
        $player["clubid"] = $clubid;
        $player["elo"] = $elo;
        return $player;
    }

    /**
     * @return array
     * Acquires necessary ladder info for each player.
     * May need some additional info in the future (e.g. club), to specify location.
     * This will prevent unnecessarily large amounts of player data from being retrieved.
     */
    function get_ladder_data()
    {
        $statement = $this->connection->prepare("SELECT fname, lname, clubid, elo  FROM player");
        $statement->execute();
        $statement->bind_result($fname, $lname, $clubid, $elo);
        $players = array();
        while ($statement->fetch()) {
            $player = array();
            $player["fname"] = $fname;
            $player["lname"] = $lname;
            $player["clubid"] = $clubid;
            $player["elo"] = $elo;
            array_push($players, $player);
        }
        return $players;
    }


    /**
     * TODO: Implement update functionality
     */
/*   function update_player($playerid, $email, $password, $contactno, $fname, $lname, $clubid, $elo)
    {
        $statement = $this->connection->prepare("UPDATE player SET email = ?, password = ?, contactno = ?, fname = ?, lname = ?, clubid = ?, elo = ? WHERE playerid = ?");
        $statement->bind_param("sssssii", $email, $password, $contactno, $fname, $lname, $clubid, $elo, $playerid);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }*/

    /**
     * @return bool
     *  Delete a player with associated ID.
     */
    function delete_player($playerid)
    {
        $statement = $this->connection->prepare("DELETE from player WHERE playerid = ?");
        $statement->bind_param("i", $playerid);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }
}

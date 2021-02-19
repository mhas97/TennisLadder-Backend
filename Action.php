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
        $statement->bind_param("ssssss", $email, $hashed_password, $contactno, $fname, $lname, $clubname);
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
    function get_player_data($email)
    {
        $statement = $this->connection->prepare("SELECT playerid, contactno, fname, lname, (SELECT name from club where player.clubid = club.clubid), elo FROM player WHERE email = ?");
        $statement->bind_param("s", $email);
        $statement->execute();
        $statement->bind_result($playerid, $contactno, $fname, $lname, $clubname, $elo);
        $statement->fetch();
        $player = array();
        $player["playerid"] = $playerid;
        $player["email"] = $email;
        $player["contactno"] = $contactno;
        $player["fname"] = $fname;
        $player["lname"] = $lname;
        $player["clubname"] = $clubname;
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
        $statement = $this->connection->prepare("SELECT playerid, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), elo, hotstreak FROM player");
        $statement->execute();
        $statement->bind_result($playerid, $fname, $lname, $clubname, $elo, $hotstreak);
        $players = array();
        while ($statement->fetch()) {
            $player = array();
            $player["playerid"] = $playerid;
            $player["fname"] = $fname;
            $player["lname"] = $lname;
            $player["clubname"] = $clubname;
            $player["elo"] = $elo;
            $player["hotstreak"] = $hotstreak;
            array_push($players, $player);
        }
        return $players;
    }

    function create_challenge($clubname, $date, $time)
    {
        $date = intval($date);
        $mysqldate = date("Y-m-d", $date);
        echo $mysqldate;
        $statement = $this->connection->prepare("INSERT INTO challenge (clubid, date, time) VALUES ((SELECT clubid from club WHERE name = ?), ?, ?)");
        $statement->bind_param("sss", $clubname, $date, $time);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }

    function create_player_challenge($challengeid, $playerid, $didinitiate)
    {
        $challengeid = intval($challengeid);
        $playerid = intval($playerid);
        $didinitiate = intval($didinitiate);
        $statement = $this->connection->prepare("INSERT INTO player_challenge (challengeid, playerid, didinitiate) VALUES (?, ?, ?)");
        $statement->bind_param("iii", $challengeid, $playerid, $didinitiate);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }

    function get_challenge_id() {
        $statement = $this->connection->prepare("SELECT challengeid FROM challenge ORDER BY challengeid DESC LIMIT 1"); // BAD!
        $statement->execute();
        $statement->bind_result($challengeid);
        $statement->fetch();
        $challengeid = strval($challengeid); // Must return a string.
        return $challengeid;
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

    /**
     * @return array
     * Return a list of valid clubs
     */
    function get_clubs()
    {
        $statement = $this->connection->prepare("SELECT name FROM club");
        $statement->execute();
        $statement->bind_result($club);
        $clubs = array();
        while ($statement->fetch()) {
            array_push($clubs, $club);
        }
        return $clubs;
    }
}

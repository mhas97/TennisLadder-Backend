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
        $statement = $this->connection->prepare("SELECT playerid, contactno, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), elo FROM player WHERE email = ?");
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
        $statement = $this->connection->prepare("INSERT INTO challenge (clubid, date, time) VALUES ((SELECT clubid from club WHERE name = ?), ?, ?)");
        $statement->bind_param("sss", $clubname, $mysqldate, $time);
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

    /**
     * Upon creation of a challenge, returns the newly auto-generated challenge ID.
     * This is further used as a reference in the player_challenge table, as part
     * of a compound key.
     *
     * The limitation of this function is that it returns the latest entry, which will
     * run into concurrency issues if the app gets busy.
     */
    function get_challenge_id() {
        $statement = $this->connection->prepare("SELECT challengeid FROM challenge ORDER BY challengeid DESC LIMIT 1"); // BAD!
        $statement->execute();
        $statement->bind_result($challengeid);
        $statement->fetch();
        $challengeid = strval($challengeid); // Must return a string.
        return $challengeid;
    }

    /**
     * This function was the original method used to get a list of challenges for a given player id.
     * This has since been refactored and improved, querying more efficiently as well as returning
     * data in recognisable JSON format which is simpler to parse (see get_challenges below).
     */
 /*   function get_challenges($playerid)
    {
        $result = array();
        $playerid = intval($playerid);
        $statement = $this->connection->prepare("SELECT challengeid FROM player_challenge WHERE playerid = ?");
        $statement->bind_param("i", $playerid);
        $statement->execute();
        $statement->bind_result($challengeid);
        $challengeids = array();
        while ($statement->fetch()) {
            array_push($challengeids, strval($challengeid));
        }
        $statement->close();
        $result["challengeid"] = $challengeids;
        $opponent_ids = array();
        $opponent_fnames = array();
        $opponent_lnames = array();
        foreach ($challengeids as $c) {
            $statement = $this->connection->prepare("SELECT playerid FROM player_challenge WHERE challengeid = ? AND playerid != ?");
            $statement_getdetails = $this->connection->prepare("SELECT fname, lname FROM player WHERE playerid = ?");
            $statement->bind_param("ii", $c, $playerid);
            $statement->execute();
            $statement->bind_result($opponentid);
            $statement->fetch();
            $statement->close();
            $statement_getdetails->bind_param("i", $opponentid);
            $statement_getdetails->execute();
            $statement_getdetails->bind_result($opponent_fname, $opponent_lname);
            $statement_getdetails->fetch();
            $statement_getdetails->close();
            array_push($opponent_ids, strval($opponentid));
            array_push($opponent_fnames, $opponent_fname);
            array_push($opponent_lnames, $opponent_lname);
        }
        $result["opponentid"] = $opponent_ids;
        $result["opponent_fname"] = $opponent_fnames;
        $result["opponent_lname"] = $opponent_lnames;
        $dates = array();
        $times = array();
        foreach ($challengeids as $c) {
            $statement = $this->connection->prepare("SELECT date, time FROM challenge WHERE challengeid = ?");
            $statement->bind_param("i", $c);
            $statement->execute();
            $statement->bind_result($date, $time);
            $statement->fetch();
            $statement->close();
            array_push($dates, $date);
            array_push($times, $time);
        }
        $result["date"] = $dates;
        $result["time"] = $times;
        return $result;
    }*/

    /**
     * The improved get challenges method.
     * First get a list of challenge ID's and results (win/loss) from the challenge table, insert these into an array of keypair values.
     * Using the challenge ID's we just obtained, query for other data relating to the challenge, player and opponent.
     * As we go through, append the data from the first array (which was used to identify challenge ID's) to the array which is returned.
     * This results in final array being ready for JSON encoding.
     */
    function get_challenges($playerid) {
        $statement_get_challenge_ids = $this->connection->prepare("SELECT challengeid, didwin FROM player_challenge WHERE playerid = ?");
        $playerid = intval($playerid);
        $statement_get_challenge_ids->bind_param("i", $playerid);
        $statement_get_challenge_ids->execute();
        $statement_get_challenge_ids->bind_result($challengeid, $didwin);
        $challengeids_locs = array();
        while ($statement_get_challenge_ids->fetch()) {
            $challengeid_loc = array();
            $challengeid_loc["challengeid"] = $challengeid;
            $challengeid_loc["didwin"] = $didwin;
            array_push($challengeids_locs, $challengeid_loc);
        }

        $challenges = array();

        foreach ($challengeids_locs as $c) {
            $statement_get_opponent_id = $this->connection->prepare("SELECT playerid FROM player_challenge WHERE challengeid = ? AND playerid != ?");
            $statement_get_opponent_data = $this->connection->prepare("SELECT fname, lname FROM player WHERE playerid = ?");
            $statement_get_challenge_data = $this->connection->prepare("SELECT date, time, (SELECT name FROM club WHERE challenge.clubid = club.clubid ), score FROM challenge WHERE challengeid = ?");
            $challenge = array();
            $challenge["challengeid"] = strval($c["challengeid"]);
            $challenge["didwin"] = strval($c["didwin"]);

            $statement_get_opponent_id->bind_param("ii", $c["challengeid"], $playerid);
            $statement_get_opponent_id->execute();
            $statement_get_opponent_id->bind_result($opponentid);
            $statement_get_opponent_id->fetch();
            $challenge["opponentid"] = strval($opponentid);
            $statement_get_opponent_id->close();

            $statement_get_opponent_data->bind_param("i", $opponentid);
            $statement_get_opponent_data->execute();
            $statement_get_opponent_data->bind_result($fname, $lname);
            $statement_get_opponent_data->fetch();
            $challenge["fname"] = $fname;
            $challenge["lname"] = $lname;
            $statement_get_opponent_data->close();

            $statement_get_challenge_data->bind_param("i", $c["challengeid"]);
            $statement_get_challenge_data->execute();
            $statement_get_challenge_data->bind_result($date, $time, $location, $score);
            $statement_get_challenge_data->fetch();
            $challenge["date"] = $date;
            $challenge["time"] = $time;
            $challenge["location"] = $location;
            $challenge["score"] = $score;
            $statement_get_challenge_data->close();

            array_push($challenges, $challenge);
        }
        return $challenges;
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
}

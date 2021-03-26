<?php /** @noinspection ALL */

// [REF: https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]

/**
 * Class Action
 */
class Action {

    private $connection;

    /**
     * Obtains a mysqli connection object via the Connection class.
     */
    function __construct(){
        require_once dirname(__FILE__) . "/Connection.php";
        $tennisDatabase = new Connection();
        $this->connection = $tennisDatabase->connect();
    }

    /**
     * Providing the email exists, checks the provided hashed password against
     * the hashed password stored in the database. A maximum of 1 user can be
     * retrieved, as the email is ensured to be unique upon signup.
     */
    function login($email, $password) {
        /* Prepare statement. */
        $statementLogin = $this->connection->prepare("SELECT password FROM player WHERE email = ?");
        $statementLogin->bind_param("s",  $email);

        /* Execute and fetch results. */
        $statementLogin->execute();
        $statementLogin->bind_result($hashedPassword);
        $statementLogin->fetch();
        $statementLogin->close();

        /* Verify the hashed passwords match. */
        if (password_verify($password, $hashedPassword)) {
            return true;
        }
        return false;
    }

    /**
     * Attempt to create a player using the provided parameters. If the email is
     * already in use, alert the user. The password is hashed prior to database entry.
     */
    function create_player($email, $password, $contactNo, $fname, $lname, $clubName) {
        /* Hash the password and prepare the statement. */
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $statementSignup = $this->connection->prepare(
            "INSERT INTO player (email, password, contactno, fname, lname, clubid) 
                    VALUES (?, ?, ?, ?, ?, (SELECT clubid from club WHERE name = ?))");
        $statementSignup->bind_param("ssssss", $email, $hashedPassword, $contactNo, $fname, $lname, $clubName);

        /* Execute the statement and return the error code, where 0 indicates success. */
        $statementSignup->bind_param("ssssss", $email, $hashedPassword, $contactNo, $fname, $lname, $clubName);
        $statementSignup->execute();
        $errorNo = $statementSignup->errno;
        $statementSignup->close();
        return $errorNo;
    }

    /**
     * Return a list of valid clubs.
     */
    function get_clubs() {
        /* Prepare statement. */
        $statementClubs = $this->connection->prepare("SELECT name FROM club");
        $statementClubs->execute();
        $statementClubs->bind_result($club);
        $clubs = array();

        /* Execute statement and return clubs array. */
        while ($statementClubs->fetch()) {
            array_push($clubs, $club);
        }
        $statementClubs->close();
        return $clubs;
    }

    /**
     * Create a new club that can be selected upon user signup.
     */
    function create_club($name, $address) {
        /* Prepare and execute statement. */
        $statementCreateClub = $this->connection->prepare("INSERT INTO club (name, address) VALUES (?,?)");
        $statementCreateClub->bind_param("ss", $name, $address);
        if ($statementCreateClub->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Upon a successful login attempt, this function is used to return
     * the necessary user data to perform tasks during the login session.
     */
    function get_player_data($email)
    {
        /* Prepare statement. */
        $statementGetPlayerData = $this->connection->prepare(
            "SELECT playerid, contactno, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), 
       elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player WHERE email = ?"
        );
        $statementGetPlayerData->bind_param("s", $email);

        /* Execute statement and fetch the results. */
        $statementGetPlayerData->execute();
        $statementGetPlayerData->bind_result($playerID, $contactNo, $fname, $lname,
            $clubName, $elo, $winStreak, $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);
        $statementGetPlayerData->fetch();
        $statementGetPlayerData->close();

        /* Create the resulting user data array. */
        $player = array();
        $player["playerid"] = $playerID;
        $player["email"] = $email;
        $player["contactno"] = $contactNo;
        $player["fname"] = $fname;
        $player["lname"] = $lname;
        $player["clubname"] = $clubName;
        $player["elo"] = $elo;
        $player["winstreak"] = $winStreak;
        $player["hotstreak"] = $hotStreak;
        $player["matchesplayed"] = $matchesPlayed;
        $player["wins"] = $numWins;
        $player["losses"] = $numLosses;
        $player["highestelo"] = $highestElo;
        $player["clubchamp"] = $clubChamp;
        return $player;
    }

    /**
     * Fetch ladder data for display in the ladder fragment. This
     * includes returning necessary data to view a players profile
     * as well as create a challenge.
     */
    function get_ladder_profile_data() {
        /* Prepare statement. */
        $statementLadder = $this->connection->prepare(
            "SELECT playerid, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), 
       elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player"
        );

        /* Execute statement and fetch results */
        $statementLadder->execute();
        $statementLadder->bind_result($playerID, $fname, $lname, $clubName, $elo,
            $winStreak, $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);

        /* Create the resulting array of ladder data */
        $players = array();
        while ($statementLadder->fetch()) {
            $player = array();
            $player["playerid"] = $playerID;
            $player["fname"] = $fname;
            $player["lname"] = $lname;
            $player["clubname"] = $clubName;
            $player["elo"] = $elo;
            $player["winstreak"] = $winStreak;
            $player["hotstreak"] = $hotStreak;
            $player["matchesplayed"] = $matchesPlayed;
            $player["wins"] = $numWins;
            $player["losses"] = $numLosses;
            $player["highestelo"] = $highestElo;
            $player["clubchamp"] = $clubChamp;
            array_push($players, $player);
        }
        $statementLadder->close();
        return $players;
    }

    /**
     * @param $time String provided in unix time in seconds, this makes conversion for database storage easier.
     * Create an entry in the challenge table containing challenge metadata.
     */
    function create_challenge($clubName, $date, $time) {
        $date = intval($date);
        date_default_timezone_set("Europe/London");     // Ensure correct timezone.
        $mysqlDate = date("Y-m-d", $date);      // Format date for database entry.

        /* Prepare and execute statement. */
        $statementChallenge = $this->connection->prepare(
            "INSERT INTO challenge (clubid, date, time) VALUES ((SELECT clubid from club WHERE name = ?), ?, ?)"
        );
        $statementChallenge->bind_param("sss", $clubName, $mysqlDate, $time);
        if ($statementChallenge->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Upon creation of a challenge, returns the newly auto-generated challenge ID.
     * This is further used as a reference in the player_challenge table, as part
     * of a compound key. The limitation of this function is that it returns the
     * latest entry, which will run into concurrency issues if the app gets busy.
     */
    function get_challenge_id() {
        /* Prepare and execute statement */
        $statementGetID = $this->connection->prepare(
            "SELECT challengeid FROM challenge ORDER BY challengeid DESC LIMIT 1"
        ); // BAD!
        $statementGetID->bind_result($challengeID);
        $statementGetID->execute();
        $statementGetID->fetch();
        $statementGetID->close();
        $challengeID = strval($challengeID); // Returned in string format as it is fed back as a parameter
        return $challengeID;
    }

    /**
     * @param $challengeid String autogenerated challenge ID, originally returned from create_challenge.
     * Use the autogenerated challenge ID to create player_challenge entries for both players.
     */
    function create_player_challenge($challengeID, $playerID, $opponentID) {
        $challengeID = intval($challengeID);

        /* Prepare and execute the user entry, 1 indicates that they initiated the challenge from their client */
        $playerID = intval($playerID);
        $statementUser = $this->connection->prepare(
            "INSERT INTO player_challenge (challengeid, playerid, didinitiate) VALUES (?, ?, 1)"
        );
        $statementUser->bind_param("ii", $challengeID, $playerID);
        if (!$statementUser->execute()) {
            return false;
        }
        $statementUser->close();

        /* Prepare and execute the opponent entry, 0 indicates that they are recieving the challenge */
        $opponentID = intval($opponentID);
        $statementOpponent = $this->connection->prepare(
            "INSERT INTO player_challenge (challengeid, playerid, didinitiate) VALUES (?, ?, 0)"
        );
        $statementOpponent->bind_param("ii", $challengeid, $opponentid);
        if (!$statementOpponent->execute()) {
            return false;
        }
        return true;    // Both statements executed successfully.

    }

    /**
     * Updated get_challenges method. First obtains a list of challenge ID's and initiation status'
     * from the player_challenge table, and insert these into an array of keypair values. Using the challenge
     * ID's, query for challenge metadata, as well as user and opponent data. As
     * we iterate, append the data from the first array (which was used to identify challenge ID's)
     * to the array which is returned. This results in final array being correctly formatted for JSON
     * encoding.
     */
    function get_challenges($playerID) {
        /* Construct a list of challenge ID's an initiation status' */
        $statementGetIDList = $this->connection->prepare(
            "SELECT challengeid, didinitiate FROM player_challenge WHERE playerid = ? AND didwin = -1"
        );
        $playerID = intval($playerID);
        $statementGetIDList->bind_param("i", $playerID);
        $statementGetIDList->execute();
        $statementGetIDList->bind_result($challengeID, $didInitiate);
        $challengeList = array();
        while ($statementGetIDList->fetch()) {
            $locatedChallenge = array();
            $locatedChallenge["challengeid"] = $challengeID;
            $locatedChallenge["didinitiate"] = $didInitiate;
            array_push($challengeList, $locatedChallenge);
        }
        $statementGetIDList->close();

        $challenges = array();  // The final challenges array to be returned.

        foreach ($challengeids_locs as $c) {
            $statementGetOpponentID = $this->connection->prepare(
                "SELECT playerid FROM player_challenge WHERE challengeid = ? AND playerid != ?"
            );
            $statement_get_opponent_data = $this->connection->prepare("SELECT fname, lname, elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player WHERE playerid = ?");
            $statement_get_challenge_data = $this->connection->prepare("SELECT date, time, (SELECT name FROM club WHERE challenge.clubid = club.clubid ), accepted FROM challenge WHERE challengeid = ?");
            $challenge = array();
            $challenge["challengeid"] = $c["challengeid"];
            $challenge["didinitiate"] = $c["didinitiate"];

            $statement_get_opponent_id->bind_param("ii", $c["challengeid"], $playerid);
            $statement_get_opponent_id->execute();
            $statement_get_opponent_id->bind_result($opponentid);
            $statement_get_opponent_id->fetch();
            $challenge["opponentid"] = $opponentid;
            $statement_get_opponent_id->close();

            $statement_get_opponent_data->bind_param("i", $opponentid);
            $statement_get_opponent_data->execute();
            $statement_get_opponent_data->bind_result($fname, $lname, $elo, $winstreak, $hotstreak, $matchesplayed, $wins, $losses, $highestelo, $clubchamp);
            $statement_get_opponent_data->fetch();
            $challenge["fname"] = $fname;
            $challenge["lname"] = $lname;
            $challenge["elo"] = $elo;
            $challenge["winstreak"] = $winstreak;
            $challenge["hotstreak"] = $hotstreak;
            $challenge["matchesplayed"] = $matchesplayed;
            $challenge["wins"] = $wins;
            $challenge["losses"] = $losses;
            $challenge["highestelo"] = $highestelo;
            $challenge["clubchamp"] = $clubchamp;
            $statement_get_opponent_data->close();

            $statement_get_challenge_data->bind_param("i", $c["challengeid"]);
            $statement_get_challenge_data->execute();
            $statement_get_challenge_data->bind_result($date, $time, $location, $accepted);
            $statement_get_challenge_data->fetch();
            $challenge["date"] = $date;
            $challenge["time"] = $time;
            $challenge["location"] = $location;
            $challenge["accepted"] = $accepted;
            $statement_get_challenge_data->close();
            array_push($challenges, $challenge);
        }
        return $challenges;
    }

    /*TODO*/
    // Remove unnecessary returns.
    /**
     * Operates similarly to get_challenges(), this simply returns data related to completed matches
     * rather than challenges, where a "match" is a challenge that has been accepted, played, with a score reported.
     */
    function get_match_history($playerid) {
        $statement_get_challenge_ids = $this->connection->prepare("SELECT challengeid, didwin FROM player_challenge WHERE playerid = ? AND didwin != -1");
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
            $challenge["challengeid"] = $c["challengeid"];
            $challenge["didwin"] = $c["didwin"];

            $statement_get_opponent_id->bind_param("ii", $c["challengeid"], $playerid);
            $statement_get_opponent_id->execute();
            $statement_get_opponent_id->bind_result($opponentid);
            $statement_get_opponent_id->fetch();
            $challenge["opponentid"] = $opponentid;
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
    function delete_player($playerid) {
        $statement = $this->connection->prepare("DELETE FROM player WHERE playerid = ?");
        $statement->bind_param("i", $playerid);
        if ($statement->execute())
        {
            return true;
        }
        return false;
    }

    function accept_challenge($challengeid) {
        $statement = $this->connection->prepare("UPDATE challenge SET accepted = 1 WHERE challengeid = ?");
        $statement->bind_param("i", $challengeid);
        if ($statement->execute()) {
            return true;
        }
        return false;
    }

    function cancel_challenge($challengeid) {
        $statement_player_challenge = $this->connection->prepare("DELETE FROM player_challenge WHERE challengeid = ?");
        $statement_player_challenge->bind_param("i", $challengeid);
        $statement_challenge = $this->connection->prepare("DELETE FROM challenge WHERE challengeid = ?");
        $statement_challenge->bind_param("i", $challengeid);
        if (!$statement_player_challenge->execute()) {
            return false;
        }
        if (!$statement_challenge->execute()) {
            return false;
        }
        return true;
    }

    function post_result($challengeid, $winnerid, $loserid, $score, $winnerelo, $loserelo, $newhighestelo, $hotstreak) {

        $challengeid = intval($challengeid);
        $winnerid = intval($winnerid);
        $loserid = intval($loserid);
        $winnerelo = intval($winnerelo);
        $loserelo = intval($loserelo);
        $newhighestelo = intval($newhighestelo);
        $hotstreak = intval($hotstreak);

        $current_club_champion = $this->get_club_champion($winnerid);
        if ($winnerelo >= $current_club_champion) {
            $clubchampion = 1;
            $this->remove_champion_status($current_club_champion);
        }
        else {
            $clubchampion = 0;
        }

        $statement = $this->connection->prepare("UPDATE challenge SET score = ? WHERE challengeid = ?");
        $statement->bind_param("si", $score, $challengeid);
        if (!$statement->execute()) {
            return false;
        }

        $statement_winner = $this->connection->prepare("UPDATE player_challenge SET didwin = 1 WHERE challengeid = ? AND playerid = ?");
        $statement_winner->bind_param("ii", $challengeid, $winnerid);
        if (!$statement_winner->execute()) {
            return false;
        }

        $statement_loser = $this->connection->prepare("UPDATE player_challenge SET didwin = 0 WHERE challengeid = ? AND playerid = ?");
        $statement_loser->bind_param("ii", $challengeid, $loserid);
        if (!$statement_loser->execute()) {
            return false;
        }

        $statement_winner_player = $this->connection->prepare("UPDATE player SET elo = ?, winstreak = winstreak + 1, hotstreak = ?, matchesplayed = matchesplayed + 1, wins = wins + 1, highestelo = ?, clubchamp = ? WHERE playerid = ?");
        $statement_winner_player->bind_param("iiiii", $winnerelo, $hotstreak, $newhighestelo, $clubchampion, $winnerid);
        if (!$statement_winner_player->execute()) {
            return false;
        }

        $statement_loser_player = $this->connection->prepare("UPDATE player SET elo = ?, winstreak = 0, hotstreak = 0, matchesplayed = matchesplayed + 1, losses = losses + 1 WHERE playerid = ?");
        $statement_loser_player->bind_param("ii", $loserelo, $loserid);
        if (!$statement_loser_player->execute()) {
            return false;
        }

        return true;
    }

    function get_club_champion($winnerid) {
        $statement_get_club = $this->connection->prepare("SELECT clubid FROM player WHERE playerid = ?");
        $statement_get_club->bind_param("i", $winnerid);
        $statement_get_club->execute();
        $statement_get_club->bind_result($clubid);
        $statement_get_club->fetch();
        $statement_get_club->close();

        $statement_get_elos = $this->connection->prepare("SELECT playerid, elo FROM player WHERE clubid = ?");
        $statement_get_elos->bind_param("i", $clubid);
        $statement_get_elos->execute();
        $statement_get_elos->bind_result($playerid, $elo);
        $elos = array();
        while ($statement_get_elos->fetch()) {
            if ($playerid != $winnerid) {
                array_push($elos, $elo);
            }
        }
        return max($elos);
    }

    function remove_champion_status($currentclubchampion) {
        $statement = $this->connection->prepare("UPDATE player SET clubchamp = 0 WHERE elo = ?");
        $statement->bind_param("i", $currentclubchampion);
        $statement->execute();
    }

    /**
     * This function was the original method used to get a list of challenges for a given player id.
     * This has since been refactored and improved, querying more efficiently as well as returning
     * data in a format suitable for JSON encoding.
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
               array_push($challengeids, $challengeid);
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
               array_push($opponent_ids, $opponentid);
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
}

<?php

/**
 * The API architecture is discussed in the following article:
 * https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]
 */
require_once dirname(__FILE__) . "/Action.php";

/**
 * @param $params
 * Check for necessary parameters.
 */
function check_for_parameters($params) {

    $complete = true;
    /* Check for each parameter. */
    foreach ($params as $p) {
        if (!isset($_POST[$p]) || mb_strlen($_POST[$p]) == 0) {
            $complete = false;
        }
    }
    /* Log error and return. */
    if (!$complete) {
        $response = array();
        $response["error"] = true;
        $response['message'] = 'Please enter all necessary fields';
        echo json_encode($response);
        die();
    }
}

/* Holds the response for a request. */
$response = array();

/**
 * Determine the type of API request. If the request
 * is unrecognised, return an error message.
 */
if (isset($_GET["tennisapi"]))
{
    switch($_GET["tennisapi"]) {

        /* Login request. */
        case "login":
            check_for_parameters(array("email", "password"));   // Check for parameters.
            $db = new Action();
            $success = $db->login($_POST["email"], $_POST["password"]);
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Login successful";
                $response["player"] = $db->get_player_data($_POST["email"]);    // Fetch associated player data.
            }
            else {
                $response["error"] = true;
                $response["message"] = "Invalid username or password";
            }
            break;

            /* Signup request. */
        case "create_player":
            check_for_parameters(array("email", "password", "contactno", "fname", "lname", "clubname"));    // Check for parameters.
            $db = new Action();
            $errorCode = $db->create_player(
                $_POST["email"],
                $_POST["password"],
                $_POST["contactno"],
                $_POST["fname"],
                $_POST["lname"],
                $_POST["clubname"]
            );
            if ($errorCode == 0) {
                $response["error"] = false;
                $response["message"] = "Player created";
            }
            else if ($errorCode == 1062) {
                $response["error"] = true;
                $response["message"] = "Email is already in use";
            }
            break;

        /* Obtains a list of valid clubs to populate menu selection. */
        case "get_clubs":
            $db = new Action();
            $response["clubs"] = $db->get_clubs();
            $response["error"] = false;
            $response["message"] = "Club data retrieved";
            break;

            /* Use the player ID to fetch player data upon a successful login. */
        case "get_player_data":
            if (isset($_GET["playerid"])) {
                $db = new Action();
                $response["player"] = $db->get_player_data($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Player data retrieved";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

            /* Fetch the data required to populate the ladder fragment. */
        case "get_ladder_profile_data":
            $db = new Action();
            $response["players"] = $db->get_ladder_profile_data();
            $response["error"] = false;
            $response["message"] = "Ladder data retrieved";
            break;

            /* Creates an entry to the challenge table containing
            challenge metadata. If this is successful, return the
            autogenerated challenge ID. */
        case "create_challenge":
            check_for_parameters(array("clubname", "date", "time"));    // Check for parameters.
            $db = new Action();
            $success = $db->create_challenge($_POST["clubname"], $_POST["date"], $_POST["time"]);
            if ($success) {
                $response["challengeid"] = $db->get_challenge_id(); // Return the newly created challenge ID.
                $response["error"] = false;
                $response["message"] = "Challenge created";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Error creating challenge";
            }
            break;

            /* Creates a player_challenge database entry for both players
            using the autogenerated challenge ID from the create_challenge
            API call. */
        case "create_player_challenge":
            check_for_parameters(array("challengeid", "playerid", "opponentid"));   // Check for parameters.
            $db = new Action();
            $success = $db->create_player_challenge($_POST["challengeid"], $_POST["playerid"], $_POST["opponentid"]);
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Challenge created";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Error creating challenge";
            }
            break;

            /* Fetches the match history for a given player ID. */
        case "get_match_history":
            if (isset($_GET["playerid"])) {
                $db = new Action();
                $response["challenges"] = $db->get_match_history($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Match history retrieved";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

            /* Deletes a player with a given ID. */
        case "delete_player":
            if (isset($_GET["playerid"])) {
                $db = new Action();
                $db->delete_player($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Player deleted";
            } else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

        /* Fetches active challenges for a given player ID. */
        case "get_challenges":
            if (isset($_GET["playerid"])) {
                $db = new Action();
                $response["challenges"] = $db->get_challenges($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Challenges retrieved";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

            /* Accepts a challenge with a given ID. */
        case "accept_challenge":
            if (isset($_GET["challengeid"])) {
                $db = new Action();
                $db->accept_challenge($_GET["challengeid"]);
                $response["error"] = false;
                $response["message"] = "Challenge accepted";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a challenge ID";
            }
            break;

            /* Cancels (shares functionality with decline) a challenge with a given ID. */
        case "cancel_challenge":
            if (isset($_GET["challengeid"])) {
                $db = new Action();
                $db->cancel_challenge($_GET["challengeid"]);
                $response["error"] = false;
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a challenge ID";
            }
            break;

            /* Posts a result for given challenge ID. This also includes updating
            player data including Elo and win/loss for achievement checking. */
        case "post_result":
            check_for_parameters(array("challengeid", "winnerid", "loserid", "score", "winnerelo", "loserelo", "newhighestelo", "hotstreak"));
            $db = new Action();
            $success = $db->post_result
            (
                $_POST["challengeid"],
                $_POST["winnerid"],
                $_POST["loserid"],
                $_POST["score"],
                $_POST["winnerelo"],
                $_POST["loserelo"],
                $_POST["newhighestelo"],
                $_POST["hotstreak"]
            );
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Result submitted";

            }
            else {
                $response["error"] = true;
                $response["message"] = "Error submitting result";
            }
            break;

            /* If no matching API call is found, return an error. */
        default:
            $response["error"] = true;
            $response["message"] = "API functionality does not exist";
    }
}
/* If the API name is invalid, return error status. */
else {
    $response["error"] = true;
    $response["message"] = "Invalid API call";
}
echo json_encode($response);    // Return the JSON encoded response.
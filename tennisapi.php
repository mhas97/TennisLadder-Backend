<?php

// [REF: https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]

require_once dirname(__FILE__) . "/Action.php";

/**
 * @param $params
 * Check that all required parameters are present.
 * Generate an error message that lists any missing parameters.
 */
function check_for_parameters($params)
{
    $complete = true;
    $missing = "";

    foreach ($params as $p)
    {
        if (!isset($_POST[$p]) || mb_strlen($_POST[$p]) == 0)
        {
            $complete = false;
            $missing = $missing . ", " . $p;
        }
    }

    if (!$complete)
    {
        $response = array();
        $response["error"] = true;
        $response['message'] = 'Parameters: ' . substr($missing, 1, mb_strlen($missing)) . ' missing';
        echo json_encode($response);
        die();
    }
}

$response = array();

/**
 * Determine the nature of an API call and execute its contents
 */
if (isset($_GET["tennisapi"]))
{
    switch($_GET["tennisapi"]) {
        case "login":
            check_for_parameters(array("email", "password"));
            $db = new Action();
            $result = $db->login
            (
                $_POST["email"],
                $_POST["password"]
            );
            if ($result)
            {
                $response["error"] = false;
                $response["message"] = "Login successful";
            } else {
                $response["error"] = true;
                $response["message"] = "Login failed";
            }
            break;
        case "create_player":
            check_for_parameters(array("email", "password", "contactno", "fname", "lname", "clubname"));
            $db = new Action();
            $result = $db->create_player
            (
                $_POST["email"],
                $_POST["password"],
                $_POST["contactno"],
                $_POST["fname"],
                $_POST["lname"],
                $_POST["clubname"]
            );
            if ($result)
            {
                $response["error"] = false;
                $response["message"] = "Player created successfully";
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create player";
            }
            break;
        case "get_player_data":
            if (isset($_GET["playerid"])) {
                $db = new Action();
                $response["error"] = false;
                $response["message"] = "Player info successfully retrieved";
                $response["player"] = $db->get_player_data($_GET["playerid"]);
            } else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;
        case "get_ladder_data":
            $db = new Action();
            $response["error"] = false;
            $response["message"] = "Ladder info successfully retrieved";
            $response["players"] = $db->get_ladder_data();
            break;
        case "delete_player":
            if (isset($_GET["playerid"])) {
                $db = new Action();
                $response["error"] = false;
                $response["message"] = "Player successfully deleted";
                $db->delete_player($_GET["playerid"]);
            } else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;
        case "get_clubs":
            $db = new Action();
            $response["error"] = false;
            $response["message"] = "Club info successfully retrieved";
            $response["clubs"] = $db->get_clubs();
            break;
        default:
            $response["error"] = true;
            $response["message"] = "Stated API functionality does not exist";
    }
}
else {
    $response["error"] = true;
    $response["message"] = "Invalid API call";
}
echo json_encode($response);
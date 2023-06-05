<?php
require_once "vendor/autoload.php";

use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Logger as LoggerSettings;

// A function to setup Logger Settings
function setupLoggerSettings()
{
    return (new LoggerSettings())
        ->setType(Logger::FILE_LOGGER)
        ->setExtra("controller.log")
        ->setMaxSize(10 * 1024 * 1024);
}

// A function to create a new instance of the MadelineProto client
function createMadelineClient($settings)
{
    return new \danog\MadelineProto\API("session.madeline", $settings);
}

// A function to load the last run time and service index from the state file
function loadState($client, $stateFile)
{
    $state = [];
    if (file_exists($stateFile)) {
        $client->logger("State FILE Exist");
        $state = json_decode(file_get_contents($stateFile), true);
    } else {
        $client->logger("No State FILE");
        $state = [
            "lastRunTime" => time(),
            "serviceIndex" => 0,
        ];
    }
    return $state;
}

// A function to check if it's time to stop a service
function isTimeToRotate($client, $lastRunTime, $serviceRunTime)
{
    return time() >= $lastRunTime + $serviceRunTime;
}

// A function to save the current time and service index to the state file
function saveState($serviceIndex, $stateFile)
{
    $state = [
        "lastRunTime" => time(),
        "serviceIndex" => $serviceIndex,
    ];
    file_put_contents($stateFile, json_encode($state));
}

// Helper function to start a service
function startService($client, $telegramChatID, $service)
{
    $client->messages->sendMessage([
        "peer" => $telegramChatID,
        "message" => "/start $service",
    ]);
    $client->logger("Starting $service");
}

// Helper function to stop a service
function stopService($client, $telegramChatID, $service)
{
    $client->messages->sendMessage([
        "peer" => $telegramChatID,
        "message" => "/stop $service",
    ]);
    $client->logger("Stoping $service");
}

// A function to rotate the services
function rotateServices($client, $state, $services, $serviceRunTime, $telegramChatID, $stateFile)
{
    $lastRunTime = $state["lastRunTime"];
    $serviceIndex = $state["serviceIndex"];
    if (isTimeToRotate($client, $lastRunTime, $serviceRunTime)) {
        $client->logger("TIME TO ROTATE");
        stopService($client, $telegramChatID, $services[$serviceIndex]);
        // Get the next service in the rotation list
        $serviceIndex = ($serviceIndex + 1) % count($services);
        // Start the service
        startService($client, $telegramChatID, $services[$serviceIndex]);
        saveState($serviceIndex, $stateFile);
        $client->logger("Saved STATE");
    }
}

// Main Program
$settings = setupLoggerSettings();
$MadelineProto = createMadelineClient($settings);
$MadelineProto->start();


//Get your LordsBot chat ID and set it here
$telegramChatID = 5754561852;
//Save the names of the accounts you are rotating
$services = ["FoodHyper", "WoodHyper", "OreHyper", "StoneHyper", "GoldHyper"];
$serviceRunTime = 20 * 60; // each account will run for: 20 minutes in seconds
$stateFile = "script_state.json";

$state = loadState($MadelineProto, $stateFile);
rotateServices($MadelineProto, $state, $services, $serviceRunTime, $telegramChatID, $stateFile);

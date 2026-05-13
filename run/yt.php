<?php
/** @noinspection PhpUnhandledExceptionInspection */

require 'utils.php';

use Controllers\Youtube;
use Doctrine\DBAL\Connection;

require __DIR__ . '/bootstrap.php';
/** @var Connection $conn */
global $conn;

$controller = new Youtube($conn);
$options = getopt("mM:n:v:S:s:d:c:f:l:", ['rawName']);
$command = $options["M"] ?? '';
if (isset($options["m"])) {
    $command = 'manual';
}

//if (empty($command))
switch ($command) {
    case 'manual':
        if (empty($options['v'])) {
            die('Missing url');
        }
        $controller->downloadManual(
            $options['v'],
            $options['d'] ?? '',
            $options['s'] ?? '',
            $options['S'] ?? '720',
            $options['rawName'] ?? null
        );
        break;
    case 'fetch':
        updateFeeds($controller, $options["f"] ?? null);
        break;
    case 'download':
        downloadVideos($controller, $options["f"] ?? null);
        break;
    case 'skipSubs':
        downloadVideos($controller, $options["f"] ?? null, true);
        break;
    case 'manage':
        $controller->manageVideos();
        break;
    case 'clear':
        $controller->clearPending();
        break;
    case 'add':
//        if (2 !== count(explode('@', $argv[2]))) {
//            die('Invalid channel link');
//        } todo full link sometimes required
        if (empty($options["s"])) {
            die('Choose language');
        }
        if (empty($options["S"])) {
            die('Choose res');
        }
        if (empty($options["c"])) {
            die('Choose category');
        }
        $controller->addChannel($options["f"], $options["s"], $options["S"], $options["c"], $options["n"] ?? '');
        break;
    case 'add-playlist':
        if (!isset($options['f'], $options['n'], $options['s'], $options['S'], $options['c'])) {
            dd('Missing argument... fnlSc');
        }
        $controller->addPlaylist($options['f'], $options['n'], $options['s'], $options['S'], $options['c']);
        break;
    case 'help':
        printLn("NYI :P");
        break;
    default:
        updateFeeds($controller, $options["f"] ?? null);
        printLn("Managing videos...");
        $controller->manageVideos();
        printLn("Downloading...");
        $controller->downloadVideos();
}

function updateFeeds(Youtube $controller, string $feedName = null): void
{
    if (empty($feedName)) {
        printLn("Updating stale feeds...");
        $controller->updateFeeds();
    } else {
        printLn("Looking up matching feeds...");
        $feeds = $controller->findFeeds($feedName);
        $count = count($feeds);
        printLn("Found {$count} feeds");
        foreach ($feeds as $feed) {
            printLn("Updating {$feed['name']}...");
            $controller->updateFeed($feed['id']);
        }
    }
}

function downloadVideos(Youtube $controller, string $feedName = null, bool $skipSubs = false): void
{
    if (empty($feedName)) {
        printLn("Downloading videos...");
        $controller->downloadVideos(null, $skipSubs);
    } else {
        printLn("Looking up matching feeds...");
        $feeds = $controller->findFeeds($feedName);
        $count = count($feeds);
        printLn("Found {$count} feeds");
        foreach ($feeds as $feed) {
            printLn("Downloading {$feed['name']}...");
            $controller->downloadVideos($feed['id'], $skipSubs);
        }
    }
}

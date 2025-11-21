<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('ETag: "' . md5(time() . rand()) . '"'); // Force unique ETag to prevent caching

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Not authenticated', 'needsAuth' => true]);
    exit();
}

// Check if user has Google access token
if (!isset($_SESSION['google_access_token'])) {
    echo json_encode(['error' => 'Google authentication required', 'needsGoogleAuth' => true]);
    exit();
}

$events = [];
$error = null;

try {
    $client = new Google_Client();
    $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
    $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
    $client->setDeveloperKey($_ENV['DEVELOPER_KEY']);
    
    // Set access token (can be array or JSON string)
    $accessToken = $_SESSION['google_access_token'];
    if (is_string($accessToken)) {
        $accessToken = json_decode($accessToken, true);
        if (!$accessToken) {
            $accessToken = ['access_token' => $_SESSION['google_access_token']];
        }
    }
    $client->setAccessToken($accessToken);

    // Refresh token if expired
    if ($client->isAccessTokenExpired()) {
        if (isset($_SESSION['google_refresh_token'])) {
            try {
                $client->refreshToken($_SESSION['google_refresh_token']);
                $newToken = $client->getAccessToken();
                if ($newToken) {
                    $_SESSION['google_access_token'] = $newToken;
                    if (is_array($newToken) && isset($newToken['refresh_token'])) {
                        $_SESSION['google_refresh_token'] = $newToken['refresh_token'];
                    }
                }
            } catch (Exception $refreshError) {
                echo json_encode(['error' => 'Token refresh failed', 'needsGoogleAuth' => true]);
                exit();
            }
        } else {
            echo json_encode(['error' => 'Token expired', 'needsGoogleAuth' => true]);
            exit();
        }
    }

    $service = new Google_Service_Calendar($client);

    // Get events from today onwards (including today) for the next 60 days
    // Use ISO 8601 format with current time to ensure fresh data
    $timeMin = date('c', strtotime('today'));
    $timeMax = date('c', strtotime('+60 days'));
    
    // Check if we have a sync token for incremental updates
    $syncToken = isset($_SESSION['calendar_sync_token']) ? $_SESSION['calendar_sync_token'] : null;

    $calendarList = $service->calendarList->listCalendarList();
    $calendarsProcessed = [];
    $calendarsSkipped = [];
    $totalCalendars = count($calendarList->getItems());
    
    foreach ($calendarList->getItems() as $calendar) {
        $calendarId = $calendar->getId();
        $calendarName = $calendar->getSummary() ?: 'Unknown';
        
        // Include all calendars (don't skip based on selected status)
        // This ensures we get all events from all calendars
        $optParams = array(
            'maxResults' => 250, // Increased to get more events
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'showDeleted' => true, // Show deleted events so we can properly filter them
        );
        
        // Use sync token for incremental updates if available
        // This gets only changed events since last sync
        $calendarSyncToken = isset($_SESSION['calendar_sync_tokens'][$calendarId]) 
            ? $_SESSION['calendar_sync_tokens'][$calendarId] 
            : null;
        
        // Use sync token for incremental updates if available
        if ($calendarSyncToken) {
            // When using syncToken, we can't use timeMin/timeMax, orderBy, or singleEvents
            $optParams = array(
                'syncToken' => $calendarSyncToken,
                'maxResults' => 250,
                'showDeleted' => true,
            );
        }
        
        try {
            $results = $service->events->listEvents($calendarId, $optParams);
            $calendarEvents = $results->getItems();
            
            // Store sync token for next incremental update
            $nextSyncToken = $results->getNextSyncToken();
            if ($nextSyncToken) {
                if (!isset($_SESSION['calendar_sync_tokens'])) {
                    $_SESSION['calendar_sync_tokens'] = [];
                }
                $_SESSION['calendar_sync_tokens'][$calendarId] = $nextSyncToken;
            }
            
            // Handle pagination if there are more results
            $pageToken = $results->getNextPageToken();
            while ($pageToken) {
                $optParams['pageToken'] = $pageToken;
                $nextResults = $service->events->listEvents($calendarId, $optParams);
                $calendarEvents = array_merge($calendarEvents, $nextResults->getItems());
                $pageToken = $nextResults->getNextPageToken();
            }
            
            $eventsFromThisCalendar = 0;
            $cancelledCount = 0;
            $endedCount = 0;
            $allEventsFound = [];
            
            foreach ($calendarEvents as $event) {
                $start = $event->getStart()->getDateTime();
                if (empty($start)) {
                    $start = $event->getStart()->getDate();
                }
                
                $end = $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate();
                $eventStatus = $event->getStatus();
                $eventSummary = $event->getSummary() ?: 'No Title';
                
                // Track all events for debugging
                $allEventsFound[] = [
                    'summary' => $eventSummary,
                    'start' => $start,
                    'end' => $end,
                    'status' => $eventStatus,
                ];
                
                // Skip cancelled/deleted events
                if ($eventStatus === 'cancelled') {
                    $cancelledCount++;
                    continue;
                }
                
                // Parse the start date to check if it's in the future
                $startTime = strtotime($start);
                $now = time();
                $endTime = strtotime($end);
                
                // Include events that haven't ended yet
                if ($endTime >= $now) {
                    $events[] = [
                        'summary' => $eventSummary,
                        'start' => $start,
                        'end' => $end,
                        'description' => $event->getDescription() ?: '',
                        'calendar' => $calendarName,
                        'calendarId' => $calendarId,
                        'status' => $eventStatus,
                    ];
                    $eventsFromThisCalendar++;
                } else {
                    $endedCount++;
                }
            }
            
            // Always add calendar to processed list with detailed info (even if no events)
            $calendarsProcessed[] = [
                'name' => $calendarName,
                'id' => $calendarId,
                'selected' => $calendar->getSelected(),
                'accessRole' => $calendar->getAccessRole(),
                'totalFound' => count($calendarEvents),
                'active' => $eventsFromThisCalendar,
                'cancelled' => $cancelledCount,
                'ended' => $endedCount,
                'events' => $allEventsFound
            ];
        } catch (Exception $calendarError) {
            // Log error but continue with other calendars
            $calendarsSkipped[] = $calendarName . ": " . $calendarError->getMessage();
            error_log("Error fetching calendar {$calendarId}: " . $calendarError->getMessage());
            continue;
        }
    }
    
    // Sort events by start time
    usort($events, function($a, $b) {
        return strtotime($a['start']) - strtotime($b['start']);
    });
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'lastUpdated' => date('c'),
        'debug' => [
            'calendarsProcessed' => $calendarsProcessed,
            'calendarsSkipped' => $calendarsSkipped,
            'totalEvents' => count($events),
            'totalCalendars' => $totalCalendars,
            'calendarsChecked' => count($calendarsProcessed),
            'timeRange' => [
                'from' => $timeMin,
                'to' => $timeMax,
                'now' => date('c')
            ]
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error fetching calendar events: ' . $e->getMessage(),
        'events' => []
    ]);
}
?>


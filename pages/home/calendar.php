<?php
session_start();
require_once __DIR__ . '/../../database/database.php';

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login/login.php");
    exit();
}

// Initialize variables
$events = [];
$error = null;
$needsGoogleAuth = false;

// Check if user has Google access token
if (!isset($_SESSION['google_access_token'])) {
    $needsGoogleAuth = true;
} else {
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
                    // Refresh failed, need to re-authenticate
                    $needsGoogleAuth = true;
                }
            } else {
                $needsGoogleAuth = true;
            }
        }

        if (!$needsGoogleAuth) {
            $service = new Google_Service_Calendar($client);

            // Get events from today onwards (including today) for the next 30 days
            $timeMin = date('Y-m-d\T00:00:00\Z', strtotime('today'));
            $timeMax = date('Y-m-d\T23:59:59\Z', strtotime('+30 days'));

            $calendarList = $service->calendarList->listCalendarList();
            
            foreach ($calendarList->getItems() as $calendar) {
                // Include all calendars (don't skip based on selected status)
                // This ensures we get all events from all calendars
                $calendarId = $calendar->getId();
                $calendarName = $calendar->getSummary() ?: 'Unknown';
                $optParams = array(
                    'maxResults' => 250, // Increased to get more events
                    'orderBy' => 'startTime',
                    'singleEvents' => true,
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax,
                );
                
                try {
                    $results = $service->events->listEvents($calendarId, $optParams);
                    $calendarEvents = $results->getItems();
                    
                    // Handle pagination if there are more results
                    $pageToken = $results->getNextPageToken();
                    while ($pageToken) {
                        $optParams['pageToken'] = $pageToken;
                        $nextResults = $service->events->listEvents($calendarId, $optParams);
                        $calendarEvents = array_merge($calendarEvents, $nextResults->getItems());
                        $pageToken = $nextResults->getNextPageToken();
                    }
                    
                    foreach ($calendarEvents as $event) {
                        // Skip cancelled events
                        if ($event->getStatus() === 'cancelled') {
                            continue;
                        }
                        
                        $start = $event->getStart()->getDateTime();
                        if (empty($start)) {
                            $start = $event->getStart()->getDate();
                        }
                        
                        $end = $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate();
                        $endTime = strtotime($end);
                        $now = time();
                        
                        // Only include events that haven't ended yet
                        if ($endTime >= $now) {
                            $events[] = [
                                'summary' => $event->getSummary() ?: 'No Title',
                                'start' => $start,
                                'end' => $end,
                                'description' => $event->getDescription() ?: '',
                                'calendar' => $calendarName,
                            ];
                        }
                    }
                } catch (Exception $calendarError) {
                    // Log error but continue with other calendars
                    error_log("Error fetching calendar {$calendarId}: " . $calendarError->getMessage());
                    continue;
                }
            }
            
            // Sort events by start time
            usort($events, function($a, $b) {
                return strtotime($a['start']) - strtotime($b['start']);
            });
        }
    } catch (Exception $e) {
        $error = "Error fetching calendar events: " . $e->getMessage();
        $events = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="home.css">
</head>

<body class="min-h-screen antialiased flex flex-col items-center">

    <div class="max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8">

        <?php include("../components/header.php") ?>

        <main id="app-container" class="bg-white p-4 sm:p-6 rounded-xl shadow-lg border border-gray-100 min-h-[300px]">

            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h2 class="text-2xl font-bold text-gray-900">Google Calendar</h2>
                    <button id="refresh-calendar" class="p-2 text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition duration-200" title="Refresh Calendar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                    <span id="last-updated" class="text-xs text-gray-500"></span>
                    <span id="sync-status" class="text-xs text-green-500 hidden ml-2">🔄 Syncing...</span>
                </div>
                <a href="home.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                    ← Back to Tasks
                </a>
            </div>

            <?php if ($needsGoogleAuth): ?>
                <div class="text-center py-12">
                    <p class="text-gray-600 mb-4 text-lg">Please sign in with Google to view your calendar.</p>
                    <a href="../login/google-login.php" class="inline-block px-6 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 transition duration-200">
                        Sign in with Google
                    </a>
                </div>
            <?php elseif (isset($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php if (empty($events)): ?>
                    <div class="text-center py-12">
                        <p class="text-gray-500 text-lg">Unable to load calendar events.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$needsGoogleAuth): ?>
                <div id="calendar-events-container" class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $startDate = new DateTime($event['start']);
                            $endDate = new DateTime($event['end']);
                            $isAllDay = strlen($event['start']) == 10; // Date-only format
                            ?>
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100 hover:shadow-md transition duration-200">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                            <?php echo htmlspecialchars($event['summary'] ?: 'No Title'); ?>
                                        </h3>
                                        <div class="text-sm text-gray-600 mb-2">
                                            <span class="font-medium">📅</span>
                                            <?php if ($isAllDay): ?>
                                                <?php echo $startDate->format('F j, Y'); ?>
                                                <?php if ($startDate->format('Y-m-d') != $endDate->format('Y-m-d')): ?>
                                                    - <?php echo $endDate->format('F j, Y'); ?>
                                                <?php endif; ?>
                                                <span class="ml-2 text-blue-600">(All Day)</span>
                                            <?php else: ?>
                                                <?php echo $startDate->format('F j, Y g:i A'); ?>
                                                - <?php echo $endDate->format('g:i A'); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($event['description'])): ?>
                                            <p class="text-sm text-gray-500 mt-2 line-clamp-2">
                                                <?php echo htmlspecialchars(substr($event['description'], 0, 150)); ?>
                                                <?php echo strlen($event['description']) > 150 ? '...' : ''; ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-400 mt-2">
                                            Calendar: <?php echo htmlspecialchars($event['calendar']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <p class="text-gray-500 text-lg">No upcoming events in the next 60 days.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div id="loading-indicator" class="hidden text-center py-4">
                <div class="inline-flex items-center text-indigo-600">
                    <svg class="animate-spin h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Syncing calendar...</span>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                <button id="signout" name="logout_user"
                    class="text-sm font-medium text-gray-500 hover:text-indigo-600 transition duration-150">
                    Sign Out
                </button>
            </div>

        </main>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let refreshInterval;
        let isRefreshing = false;

        function formatEventDate(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);
            const isAllDay = start.length === 10; // Date-only format
            
            if (isAllDay) {
                const startFormatted = startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                if (startDate.toDateString() !== endDate.toDateString()) {
                    const endFormatted = endDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    return `${startFormatted} - ${endFormatted} <span class="ml-2 text-blue-600">(All Day)</span>`;
                }
                return `${startFormatted} <span class="ml-2 text-blue-600">(All Day)</span>`;
            } else {
                const startFormatted = startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) + 
                                      ' ' + startDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                const endFormatted = endDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                return `${startFormatted} - ${endFormatted}`;
            }
        }

        function renderEvents(events) {
            const container = $('#calendar-events-container');
            container.empty();
            
            if (!events || events.length === 0) {
                container.html('<div class="text-center py-12"><p class="text-gray-500 text-lg">No upcoming events in the next 60 days.</p></div>');
                return;
            }
            
            events.forEach(function(event) {
                const eventHtml = `
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                    ${event.summary || 'No Title'}
                                </h3>
                                <div class="text-sm text-gray-600 mb-2">
                                    <span class="font-medium">📅</span> ${formatEventDate(event.start, event.end)}
                                </div>
                                ${event.description ? `
                                    <p class="text-sm text-gray-500 mt-2 line-clamp-2">
                                        ${event.description.substring(0, 150)}${event.description.length > 150 ? '...' : ''}
                                    </p>
                                ` : ''}
                                <p class="text-xs text-gray-400 mt-2">
                                    Calendar: ${event.calendar}
                                </p>
                            </div>
                        </div>
                    </div>
                `;
                container.append(eventHtml);
            });
        }

        function updateLastUpdated(time) {
            if (time) {
                const date = new Date(time);
                const timeStr = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
                $('#last-updated').text(`Last synced: ${timeStr}`);
            } else {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
                $('#last-updated').text(`Last synced: ${timeStr}`);
            }
        }

        function refreshCalendar(showLoading = true, isAutoRefresh = false) {
            if (isRefreshing) return;
            isRefreshing = true;
            
            // Show sync status for auto-refresh (subtle indicator)
            if (isAutoRefresh) {
                $('#sync-status').removeClass('hidden');
                // Auto-hide after 2 seconds
                setTimeout(function() {
                    $('#sync-status').addClass('hidden');
                }, 2000);
            }
            
            if (showLoading) {
                $('#loading-indicator').removeClass('hidden');
            }
            
            // Add timestamp to prevent any caching
            const timestamp = new Date().getTime();
            const random = Math.random().toString(36).substring(7);
            
            $.ajax({
                url: 'calendar-api.php',
                type: 'GET',
                dataType: 'json',
                cache: false, // Prevent caching
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                data: {
                    _t: timestamp,
                    _r: random // Additional cache buster
                },
                success: function(response) {
                    $('#loading-indicator').addClass('hidden');
                    
                    if (response.needsGoogleAuth) {
                        $('#sync-status').addClass('hidden');
                        window.location.href = '../login/google-login.php';
                        return;
                    }
                    
                    if (response.error && !response.events) {
                        $('#sync-status').addClass('hidden');
                        if (!isAutoRefresh) {
                            $('#calendar-events-container').html(
                                '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">' +
                                response.error + '</div>'
                            );
                        }
                        return;
                    }
                    
                    if (response.success && response.events) {
                        renderEvents(response.events);
                        updateLastUpdated(response.lastUpdated);
                        
                        // Log debug info to console (for troubleshooting)
                        if (response.debug) {
                            console.log('Calendar Sync Debug:', response.debug);
                            console.log('Total Calendars:', response.debug.totalCalendars);
                            console.log('Calendars Processed:', response.debug.calendarsProcessed);
                            if (response.debug.calendarsProcessed.length > 0) {
                                response.debug.calendarsProcessed.forEach(function(cal) {
                                    if (typeof cal === 'object') {
                                        console.log(`Calendar: ${cal.name}`, {
                                            'Total Found': cal.totalFound,
                                            'Active Events': cal.active,
                                            'Cancelled': cal.cancelled,
                                            'Ended': cal.ended,
                                            'All Events': cal.events
                                        });
                                    }
                                });
                            }
                        }
                        
                        // Hide sync status after successful update
                        setTimeout(function() {
                            $('#sync-status').addClass('hidden');
                        }, 500);
                        
                        // Brief visual feedback for auto-refresh
                        if (isAutoRefresh) {
                            $('#last-updated').fadeOut(50).fadeIn(50);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $('#loading-indicator').addClass('hidden');
                    $('#sync-status').addClass('hidden');
                    if (!isAutoRefresh) {
                        console.error('Error fetching calendar:', error);
                        $('#calendar-events-container').html(
                            '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">' +
                            'Error syncing calendar. Please try again.</div>'
                        );
                    }
                },
                complete: function() {
                    isRefreshing = false;
                }
            });
        }

        $(document).ready(function() {
            // Manual refresh button
            $('#refresh-calendar').on('click', function() {
                refreshCalendar(true);
            });
            
            // Logout button
            $('#signout').on('click', function() {
                window.location.href = '../login/logout.php';
            });
            
            // Auto-refresh every 2 seconds for near real-time sync (2000 ms)
            // Very frequent refresh to catch changes as soon as Google API has them
            refreshInterval = setInterval(function() {
                refreshCalendar(false, true); // true indicates auto-refresh
            }, 2000);
            
            // Initial load if no events are shown (for users without Google auth initially)
            <?php if (empty($events) && !$needsGoogleAuth): ?>
                refreshCalendar(true);
            <?php else: ?>
                updateLastUpdated('<?php echo date('c'); ?>');
            <?php endif; ?>
            
            // Refresh when page becomes visible (user switches back to tab)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Page became visible, refresh calendar immediately
                    refreshCalendar(false, true);
                }
            });
            
            // Also refresh when window gains focus
            $(window).on('focus', function() {
                refreshCalendar(false, true);
            });
        });
        
        // Clean up interval on page unload
        $(window).on('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>

</body>

</html>


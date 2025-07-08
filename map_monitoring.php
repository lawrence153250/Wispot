<?php
// Include your database configuration
require_once('config.php');

// Check if this is an AJAX request for locations data
if (isset($_GET['get_locations']) && $_GET['get_locations'] == '1') {
    header('Content-Type: application/json');
    
    try {
        // Get event locations with booking details
        $sql = "SELECT 
                    b.bookingId,
                    b.eventLocation,
                    b.dateOfBooking,
                    b.dateOfReturn,
                    b.bookingStatus,
                    b.connectionStatus,
                    c.username AS customerName,
                    p.packageName
                FROM booking b
                LEFT JOIN customer c ON b.customerId = c.customerId
                LEFT JOIN package p ON b.packageId = p.packageId
                WHERE b.eventLocation IS NOT NULL AND b.eventLocation != ''";
        
        // Add status filter if provided
        if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
            $status = $conn->real_escape_string($_GET['status_filter']);
            $sql .= " AND b.bookingStatus = '$status'";
        }
                
        $result = $conn->query($sql);
        
        $locations = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
        }
        
        echo json_encode($locations);
        exit();
    } catch(Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Booking Locations Map</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }
        #map-container {
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        #map {
            height: 600px;
            width: 100%;
        }
        .controls {
            background: #3498db;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        .controls-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .controls-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        #refresh, #filter-btn, #search-btn {
            background: white;
            color: #3498db;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        #refresh:hover, #filter-btn:hover, #search-btn:hover {
            background: #f8f9fa;
        }
        #status {
            font-size: 14px;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: none;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .map-info-window {
            padding: 15px;
            min-width: 250px;
        }
        .map-info-window h3 {
            margin-top: 0;
            color: #3498db;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .booking-detail {
            margin-bottom: 8px;
        }
        .booking-detail-label {
            font-weight: bold;
            color: #555;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        .status-confirmed {
            background-color: #D4EDDA;
            color: #155724;
        }
        .status-in-progress {
            background-color: #CCE5FF;
            color: #004085;
        }
        .status-completed {
            background-color: #D1ECF1;
            color: #0C5460;
        }
        .status-cancelled {
            background-color: #F8D7DA;
            color: #721C24;
        }
        .status-for-approval {
            background-color: #E2E3E5;
            color: #383D41;
        }
        .connection-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .connected {
            background-color: #d4edda;
            color: #155724;
        }

        .connecting {
            background-color: #cce5ff;
            color: #004085;
        }

        .connection-error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .unknown {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        #status-filter, #location-search {
            padding: 7px;
            border-radius: 4px;
            border: none;
            font-size: 14px;
            width: 200px;
        }
        
        .highlighted-marker {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.3);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div id="map-container">
        <div class="controls">
            <div class="controls-left">
                <h2>Booking Event Locations</h2>
                <select id="status-filter">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="In-progress">In-progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="For Approval">For Approval</option>
                </select>
                <button id="filter-btn">Filter</button>
            </div>
            <div class="controls-right">
                <input type="text" id="location-search" placeholder="Search location...">
                <button id="search-btn">Search</button>
                <button id="refresh">Refresh</button>
                <span id="status">Loading map...</span>
            </div>
        </div>
        <div id="map"></div>
        <div class="loader" id="loader"></div>
    </div>
    
    <script>
        let map;
        let markers = [];
        let infoWindows = [];
        const philippines = { lat: 12.8797, lng: 121.7740 };

        // Status badge classes mapping
        const statusClasses = {
            'Pending': 'status-pending',
            'Confirmed': 'status-confirmed',
            'In-progress': 'status-in-progress',
            'Completed': 'status-completed',
            'Cancelled': 'status-cancelled',
            'For Approval': 'status-for-approval'
        };

        // Initialize the map
        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 6,
                center: philippines,
                mapTypeId: 'roadmap'
            });
            
            // Load initial markers
            loadMarkers();
            
            // Set up refresh button
            document.getElementById('refresh').addEventListener('click', loadMarkers);
            
            // Set up filter button
            document.getElementById('filter-btn').addEventListener('click', loadMarkers);
            
            // Set up search button
            document.getElementById('search-btn').addEventListener('click', searchLocation);
            
            // Also search when pressing Enter in the search field
            document.getElementById('location-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchLocation();
                }
            });
            
            // Auto-refresh every 30 seconds
            setInterval(loadMarkers, 60000);
        }

        // Search for a specific location
        function searchLocation() {
            const searchQuery = document.getElementById('location-search').value.trim();
            if (!searchQuery) return;
            
            document.getElementById('status').textContent = 'Searching for location...';
            document.getElementById('loader').style.display = 'block';
            
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ address: searchQuery + ', Philippines' }, (results, status) => {
                document.getElementById('loader').style.display = 'none';
                
                if (status === "OK" && results[0]) {
                    const location = results[0].geometry.location;
                    const bounds = new google.maps.LatLngBounds();
                    
                    // First try to find an exact match among our markers
                    let foundExactMatch = false;
                    markers.forEach(markerObj => {
                        const marker = markerObj.marker;
                        if (marker.getTitle().toLowerCase().includes(searchQuery.toLowerCase())) {
                            bounds.extend(marker.getPosition());
                            foundExactMatch = true;
                            
                            // Highlight the matching marker
                            marker.setAnimation(google.maps.Animation.BOUNCE);
                            setTimeout(() => {
                                marker.setAnimation(null);
                            }, 2000);
                            
                            // Open its info window
                            markerObj.infowindow.open(map, marker);
                        }
                    });
                    
                    if (foundExactMatch) {
                        map.fitBounds(bounds);
                        if (map.getZoom() > 15) map.setZoom(15);
                        document.getElementById('status').textContent = `Showing results for "${searchQuery}"`;
                    } else {
                        // If no exact match found, just center on the searched location
                        map.setCenter(location);
                        map.setZoom(15);
                        document.getElementById('status').textContent = `Showing area for "${searchQuery}"`;
                    }
                } else {
                    document.getElementById('status').textContent = 'Location not found';
                }
            });
        }

        // Load markers from server
        function loadMarkers() {
            document.getElementById('status').textContent = 'Loading locations...';
            document.getElementById('loader').style.display = 'block';
            
            // Clear existing markers
            clearMarkers();
            
            // Get selected status filter
            const statusFilter = document.getElementById('status-filter').value;
            
            // Fetch locations from server
            let url = window.location.href + '?get_locations=1';
            if (statusFilter) {
                url += '&status_filter=' + encodeURIComponent(statusFilter);
            }
            
            fetch(url)
                .then(response => response.json())
                .then(locations => {
                    if (locations.error) {
                        document.getElementById('status').textContent = 'Error: ' + locations.error;
                        return;
                    }
                    
                    document.getElementById('status').textContent = `Showing ${locations.length} locations (updated: ${new Date().toLocaleTimeString()})`;
                    
                    if (locations.length === 0) {
                        document.getElementById('loader').style.display = 'none';
                        return;
                    }
                    
                    // Geocode each location and add marker
                    let geocodedCount = 0;
                    const geocoder = new google.maps.Geocoder();
                    
                    locations.forEach(location => {
                        const address = location.eventLocation + ', Philippines';
                        geocoder.geocode({ address: address }, (results, status) => {
                            if (status === "OK" && results[0]) {
                                const position = results[0].geometry.location;

                                // Create marker
                                const marker = new google.maps.Marker({
                                    map: map,
                                    position: position,
                                    title: location.eventLocation
                                });

                                // Determine radius by package (in meters)
                                let radius = 0;
                                switch ((location.packageName || '').toLowerCase()) {
                                    case 'basic kit':
                                        radius = 9.73; // 1km radius
                                        break;
                                    case 'boost kit':
                                        radius = 12.58; // 2km radius
                                        break;
                                    case 'robust kit':
                                        radius = 16.88; // 3km radius
                                        break;
                                    case 'advanced kit':
                                        radius = 20.33; // 5km radius
                                        break;
                                    default:
                                        radius = 10; // default 500m radius
                                }

                                // Determine circle color based on connectionStatus
                                let circleColor;
                                if (location.connectionStatus) {
                                    switch (location.connectionStatus.toLowerCase()) {
                                        case 'connected':
                                            circleColor = '#28a745'; // Green
                                            break;
                                        case 'connecting':
                                            circleColor = '#007BFF'; // Blue
                                            break;
                                        case 'connection error':
                                            circleColor = '#dc3545'; // Red
                                            break;
                                        default:
                                            circleColor = '#6c757d'; // Gray (default)
                                    }
                                } else {
                                    circleColor = '#6c757d'; // Gray if no status
                                }

                                // Draw circle
                                const circle = new google.maps.Circle({
                                    strokeColor: circleColor,
                                    strokeOpacity: 0.8,
                                    strokeWeight: 2,
                                    fillColor: circleColor,
                                    fillOpacity: 0.2,
                                    map: map,
                                    center: position,
                                    radius: radius // in meters
                                });

                                // Info window setup
                                const statusClass = statusClasses[location.bookingStatus] || 'status-pending';
                                const connectionStatus = location.connectionStatus || 'Unknown';
                                const connectionStatusClass = connectionStatus.toLowerCase().replace(' ', '-');
                                
                                const infoContent = `
                                    <div class="map-info-window">
                                        <h3>${location.packageName || 'Package'}</h3>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Customer:</span>
                                            ${location.customerName || 'N/A'}
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Location:</span>
                                            ${location.eventLocation || 'N/A'}
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Status:</span>
                                            <span class="status-badge ${statusClass}">
                                                ${location.bookingStatus || 'Pending'}
                                            </span>
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Connection:</span>
                                            <span class="connection-status ${connectionStatusClass}">
                                                ${connectionStatus}
                                            </span>
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Coverage:</span>
                                            ${(radius/1000).toFixed(1)} km radius
                                        </div>
                                    </div>
                                `;

                                const infowindow = new google.maps.InfoWindow({
                                    content: infoContent
                                });

                                marker.addListener("click", () => {
                                    infowindow.open(map, marker);
                                });

                                // Store both marker and circle if you need to clear them later
                                markers.push({
                                    marker: marker,
                                    circle: circle,
                                    infowindow: infowindow
                                });
                            }

                            geocodedCount++;
                            if (geocodedCount === locations.length) {
                                document.getElementById('loader').style.display = 'none';
                                if (markers.length > 0) {
                                    const bounds = new google.maps.LatLngBounds();
                                    markers.forEach(obj => {
                                        bounds.extend(obj.marker.getPosition());
                                    });
                                    map.fitBounds(bounds);
                                    if (map.getZoom() > 15) map.setZoom(15);
                                }
                            }
                        });
                    });
                })
                .catch(error => {
                    document.getElementById('status').textContent = 'Error loading locations';
                    document.getElementById('loader').style.display = 'none';
                    console.error('Error:', error);
                });
        }

        // Format date for display
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }

        // Clear all markers and circles from the map
        function clearMarkers() {
            markers.forEach(obj => {
                if (obj.marker) obj.marker.setMap(null);
                if (obj.circle) obj.circle.setMap(null);
                if (obj.infowindow) obj.infowindow.close();
            });
            markers = [];
        } 
    </script>
    
    <!-- Load Google Maps API with your API key -->
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCFx7Z_5qK__AetA_wIPEFEpuAhIxIsouI&callback=initMap">
    </script>
</body>
</html>
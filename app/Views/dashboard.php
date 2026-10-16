<?php $user = auth()->user(); ?>
<?php if ($user && ($user->can('dashboard.view'))): ?>

<div class="card">
    <div class="card-body">
    <!-- Welcome Section -->
    <div class="d-flex flex-column justify-content-center align-items-center mb-4">
        <h1 class="text-center">
            WELCOME TO <span style="color: #ffc107;"><b> <?= $app_name ?? 'My Application' ?>-</b> <?= $app_version ?? '1.0.0' ?></span>
        </h1>
    </div>

    <!-- Info Boxes Section -->
    <div class="row d-flex flex-wrap justify-content-center">
        <!-- User Registrations Box -->
        <div class="col-md-2 col-sm-6 col-12 mb-4">
            <div class="info-box shadow-lg bg-light">
              <span class="info-box-icon bg-danger"><i class="far fa-star"></i></span>

              <div class="info-box-content">
                <span class="info-box-text">Shadows</span>
                <span class="info-box-number">Large</span>
              </div>
              <!-- /.info-box-content -->
            </div>
        </div>

        <!-- All Devices Box -->
        <div class="col-md-2 col-sm-6 col-12 mb-4">
            <div class="info-box bg-gradient-primary">
                <span class="info-box-icon"><i class="fas fa-server fa-lg"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><storng>All Devices</storng></span>
                    <h3 class="info-box-number"><storng><?= $deviceCounts['All'] ?></storng></h3>
                </div>
            </div>
        </div>

        <!-- Running Devices Box -->
        <div class="col-md-2 col-sm-6 col-12 mb-4">
            <div class="info-box bg-gradient-success">
                <span class="info-box-icon"><i class="fas fa-play-circle fa-lg"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><storng>Running</storng></span>
                    <h3 class="info-box-number"><storng><?= $deviceCounts['Running'] ?></storng></h3>
                </div>
            </div>
        </div>

        <!-- Bypassed Devices Box -->
        <div class="col-md-2 col-sm-6 col-12 mb-4">
            <div class="info-box bg-gradient-info">
                <span class="info-box-icon"><i class="fas fa-exclamation-circle fa-lg"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><storng>Bypassed</storng></span>
                    <h3 class="info-box-number"><storng><?= $deviceCounts['Bypassed'] ?></storng></h3>
                </div>
            </div>
        </div>

        <!-- Stopped Devices Box -->
        <div class="col-md-2 col-sm-6 col-12 mb-4">
            <div class="info-box bg-gradient-warning">
                <span class="info-box-icon"><i class="fas fa-stop-circle fa-lg"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><storng>Stopped</storng></span>
                    <h3 class="info-box-number"><storng><?= $deviceCounts['Stopped'] ?></storng></h3>
                </div>
            </div>
        </div>

        <!-- Unknown Devices Box -->
        <div class="col-md-2 col-sm-6 col-12 mb-4">
            <div class="info-box bg-gradient-danger">
                <span class="info-box-icon"><i class="fas fa-question-circle fa-lg"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><storng>Unknown</storng></span>
                    <h3 class="info-box-number"><storng><?= $deviceCounts['Unknown'] ?></storng></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="left"><i class="far fa-flag"></i> Map</h3>
        <button id="detach-map-btn" class="btn btn-warning btn-sm">Detach Map</button>
    </div>

    <!-- Add a div to hold the map -->
    <div id="map" style="height: 500px; width: 100%;"></div>

    <!-- Floating map container -->
    <div id="floating-map-container" style="display: none; position: fixed; top: 50px; right: 50px; width: 600px; height: 400px; z-index: 1000; background: white; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.5);">
        <div id="floating-map-header" style="padding: 10px; background: #f8f9fa; border-bottom: 1px solid #ccc;">
            <button id="expand-map-btn" class="btn btn-secondary btn-sm">Expand</button>
            <button id="close-map-btn" class="btn btn-danger btn-sm float-right">Close</button>
        </div>
        <div id="floating-map" style="height: calc(100% - 50px); width: 100%;"></div>
    </div>

    <!-- Include the Google Maps JavaScript API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($google_maps_api_key, ENT_QUOTES, 'UTF-8'); ?>"></script>

    <script>
        var map, floatingMap;
        var isMapDetached = false;
        var isMapExpanded = false;
        var markers = []; // Array to store markers

        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 9,
                center: { lat: 20.69557, lng: 76.9369852 } // Default center
            });

            // Ensure locations are properly JSON encoded
            var locations = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            if (locations.length > 0) {
                map.setCenter({ lat: parseFloat(locations[0].latitude), lng: parseFloat(locations[0].longitude) });

                // Add markers to the original map
                locations.forEach(function (location) {
                    if (location.latitude && location.longitude) {
                        var markerIcon = {
                            url: (location.health && location.health.toLowerCase() === 'online') 
                                ? 'http://maps.google.com/mapfiles/ms/icons/green-dot.png' 
                                : 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
                            scaledSize: new google.maps.Size(50, 50) // Adjust size here (width, height)
                        };

                        var marker = new google.maps.Marker({
                            position: { lat: parseFloat(location.latitude), lng: parseFloat(location.longitude) },
                            map: map,
                            title: location.name,
                            icon: markerIcon
                        });

                        var infoWindow = new google.maps.InfoWindow({
                            content: `<div style="color: #000;">
                                        <h3 style="margin: 0; font-size: 16px; font-weight: bold;">${location.name}</h3>
                                        <p style="margin: 5px 0;"><strong><i class="fas fa-building"></i> </strong> ${location.address || 'N/A'}</p>
                                        <p style="margin: 5px 0;"><strong><i class="fas fa-envelope"></i> </strong> ${location.email || 'N/A'}</p>
                                        <p style="margin: 5px 0;"><strong><i class="fas fa-phone"></i> </strong> ${location.phone || 'N/A'}</p>
                                        <p style="margin: 5px 0;"><strong>Health:</strong> ${location.health || 'N/A'}</p>
                                      </div>`
                        });

                        marker.addListener('click', function () {
                            infoWindow.open(map, marker);
                        });

                        // Store the marker for later use
                        markers.push({ marker: marker, infoWindow: infoWindow });
                    }
                });
            }
        }

        function detachMap() {
            if (!isMapDetached) {
                document.getElementById('map').style.display = 'none';
                document.getElementById('floating-map-container').style.display = 'block';
                floatingMap = new google.maps.Map(document.getElementById('floating-map'), {
                    zoom: 7,
                    center: map.getCenter()
                });

                // Add markers to the detached map
                markers.forEach(function (markerData) {
                    var marker = new google.maps.Marker({
                        position: markerData.marker.getPosition(),
                        map: floatingMap,
                        title: markerData.marker.getTitle(),
                        icon: markerData.marker.getIcon()
                    });

                    var infoWindow = new google.maps.InfoWindow({
                        content: markerData.infoWindow.getContent()
                    });

                    marker.addListener('click', function () {
                        infoWindow.open(floatingMap, marker);
                    });
                });

                isMapDetached = true;
            } else {
                document.getElementById('map').style.display = 'block';
                document.getElementById('floating-map-container').style.display = 'none';
                isMapDetached = false;
            }
        }

        function expandMap() {
            var floatingMapContainer = document.getElementById('floating-map-container');
            if (!isMapExpanded) {
                floatingMapContainer.style.width = '80%';
                floatingMapContainer.style.height = '80%';
                floatingMapContainer.style.top = '10%';
                floatingMapContainer.style.right = '10%';
                isMapExpanded = true;
            } else {
                floatingMapContainer.style.width = '600px';
                floatingMapContainer.style.height = '400px';
                floatingMapContainer.style.top = '50px';
                floatingMapContainer.style.right = '50px';
                isMapExpanded = false;
            }
        }

        document.getElementById('detach-map-btn').addEventListener('click', detachMap);
        document.getElementById('expand-map-btn').addEventListener('click', expandMap);
        document.getElementById('close-map-btn').addEventListener('click', function() {
            document.getElementById('floating-map-container').style.display = 'none';
            document.getElementById('map').style.display = 'block';
            isMapDetached = false;
        });

        google.maps.event.addDomListener(window, 'load', initMap);
    </script>

    <!--END MAP TEST-->
    <!-- END CODING HERE -->
    </div>
</div>

<?php else: ?>
    <div class="alert alert-danger" role="alert">
        You are not authorized to access this page.
    </div>
<?php endif; ?>
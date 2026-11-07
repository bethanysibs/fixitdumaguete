@props([
    'locations',
    'selected' => null,
    'latitude' => null,
    'longitude' => null,
    'editable' => true,
])

@php
    $centers = [];

    foreach ($locations as $location) {
        if ($location->map_latitude !== null && $location->map_longitude !== null) {
            $centers[$location->id] = [
                'lat' => (string) $location->map_latitude,
                'lng' => (string) $location->map_longitude,
                'name' => $location->name,
            ];
        }
    }

    $mapsKey = config('services.google.maps_key');
    $useGoogle = filled($mapsKey);
@endphp

<div class="visit-map-wrap mb-3">
    <div
        id="visit-map"
        class="visit-map"
        role="application"
        aria-label="{{ $editable ? 'Map. Click to mark the home.' : 'Map of the home for this visit.' }}"
        data-centers='@json($centers)'
        data-selected="{{ $selected }}"
        data-latitude="{{ $latitude }}"
        data-longitude="{{ $longitude }}"
        data-editable="{{ $editable ? '1' : '0' }}"
        data-geocode="{{ route('maps.geocode') }}"
        data-reverse="{{ route('maps.reverse') }}"
    ></div>
    @if ($editable)
        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $latitude) }}">
        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $longitude) }}">
        <div class="form-text">Click the map to mark the home. Drag the pin if you need to adjust it. The pin stays with this booking and is shown only to you, the worker, and an admin.</div>
        @error('latitude')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @endif
</div>

@if (! $useGoogle)
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endpush
@endif

@push('scripts')
    <script>
        window.initVisitMap = function () {
            const el = document.getElementById('visit-map');
            if (!el || !window.google || !google.maps) {
                return;
            }

            const centers = JSON.parse(el.dataset.centers || '{}');
            const editable = el.dataset.editable === '1';
            const locationSelect = document.getElementById('location_id');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const startId = locationSelect ? locationSelect.value : el.dataset.selected;
            const savedLat = latInput ? latInput.value : el.dataset.latitude;
            const savedLng = lngInput ? lngInput.value : el.dataset.longitude;
            const center = (savedLat && savedLng)
                ? { lat: Number(savedLat), lng: Number(savedLng) }
                : (centers[startId] ? { lat: Number(centers[startId].lat), lng: Number(centers[startId].lng) } : { lat: 9.30684, lng: 123.305447 });

            const map = new google.maps.Map(el, {
                center: center,
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            let marker = null;
            let lookupTimer = null;
            const addressInput = document.getElementById('address');
            const geocoder = new google.maps.Geocoder();

            function status(message) {
                const note = document.getElementById('address-lookup');
                if (note) {
                    note.textContent = message || '';
                }
            }

            function place(lat, lng, fillAddress) {
                const position = { lat: lat, lng: lng };
                if (!marker) {
                    marker = new google.maps.Marker({ map: map, position: position, draggable: editable });
                    if (editable) {
                        marker.addListener('dragend', function (event) {
                            place(event.latLng.lat(), event.latLng.lng(), true);
                        });
                    }
                } else {
                    marker.setPosition(position);
                }
                write(lat, lng);
                if (fillAddress) {
                    geocoder.geocode({ location: position }, function (results, resultStatus) {
                        if (resultStatus === 'OK' && results[0] && addressInput) {
                            addressInput.value = results[0].formatted_address;
                            status('');
                        } else {
                            status('The pin is set. Type the address if it did not fill in.');
                        }
                    });
                }
            }

            function write(lat, lng) {
                if (!latInput || !lngInput) {
                    return;
                }
                latInput.value = Number(lat).toFixed(7);
                lngInput.value = Number(lng).toFixed(7);
            }

            if (savedLat && savedLng) {
                place(Number(savedLat), Number(savedLng), false);
            }

            if (editable) {
                map.addListener('click', function (event) {
                    clearTimeout(lookupTimer);
                    map.panTo(event.latLng);
                    place(event.latLng.lat(), event.latLng.lng(), true);
                });

                if (addressInput && google.maps.places) {
                    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                        componentRestrictions: { country: 'ph' },
                        fields: ['formatted_address', 'geometry'],
                    });
                    autocomplete.addListener('place_changed', function () {
                        clearTimeout(lookupTimer);
                        const picked = autocomplete.getPlace();
                        if (!picked.geometry || !picked.geometry.location) {
                            return;
                        }
                        if (picked.formatted_address) {
                            addressInput.value = picked.formatted_address;
                        }
                        map.panTo(picked.geometry.location);
                        map.setZoom(17);
                        place(picked.geometry.location.lat(), picked.geometry.location.lng(), false);
                        status('');
                    });
                    addressInput.addEventListener('input', function () {
                        clearTimeout(lookupTimer);
                        lookupTimer = setTimeout(function () {
                            if (addressInput.value.trim().length < 3) {
                                return;
                            }
                            const area = centers[locationSelect ? locationSelect.value : ''] || {};
                            status('Looking up that address…');
                            geocoder.geocode({
                                address: addressInput.value + (area.name ? ', ' + area.name + ', Philippines' : ', Philippines'),
                                componentRestrictions: { country: 'PH' },
                            }, function (results, resultStatus) {
                                if (resultStatus !== 'OK' || !results[0]) {
                                    status('That address could not be found. You can pin the home on the map.');
                                    return;
                                }
                                addressInput.value = results[0].formatted_address;
                                map.panTo(results[0].geometry.location);
                                map.setZoom(17);
                                place(results[0].geometry.location.lat(), results[0].geometry.location.lng(), false);
                                status('');
                            });
                        }, 700);
                    });
                }

                if (locationSelect) {
                    locationSelect.addEventListener('change', function () {
                        const next = centers[locationSelect.value];
                        if (!next) {
                            return;
                        }
                        map.panTo({ lat: Number(next.lat), lng: Number(next.lng) });
                        map.setZoom(14);
                        if (marker) {
                            marker.setMap(null);
                            marker = null;
                        }
                        if (latInput) {
                            latInput.value = '';
                        }
                        if (lngInput) {
                            lngInput.value = '';
                        }
                        if (addressInput) {
                            addressInput.value = '';
                        }
                    });
                }
            }
        };

        window.initVisitMapFallback = function () {
            const el = document.getElementById('visit-map');
            if (!el || !window.L) {
                return;
            }

            const centers = JSON.parse(el.dataset.centers || '{}');
            const editable = el.dataset.editable === '1';
            const locationSelect = document.getElementById('location_id');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const startId = locationSelect ? locationSelect.value : el.dataset.selected;
            const savedLat = latInput ? latInput.value : el.dataset.latitude;
            const savedLng = lngInput ? lngInput.value : el.dataset.longitude;
            const center = (savedLat && savedLng)
                ? [Number(savedLat), Number(savedLng)]
                : (centers[startId] ? [Number(centers[startId].lat), Number(centers[startId].lng)] : [9.30684, 123.305447]);

            const map = L.map(el).setView(center, savedLat ? 16 : 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            let marker = null;
            let lookupTimer = null;
            const addressInput = document.getElementById('address');

            function status(message) {
                const note = document.getElementById('address-lookup');
                if (note) {
                    note.textContent = message || '';
                }
            }

            function place(lat, lng, fillAddress) {
                if (!marker) {
                    marker = L.marker([lat, lng], { draggable: editable }).addTo(map);
                    if (editable) {
                        marker.on('dragend', function () {
                            const point = marker.getLatLng();
                            place(point.lat, point.lng, true);
                        });
                    }
                } else {
                    marker.setLatLng([lat, lng]);
                }
                write(lat, lng);
                if (fillAddress) {
                    const url = new URL(el.dataset.reverse, window.location.origin);
                    url.searchParams.set('latitude', lat);
                    url.searchParams.set('longitude', lng);
                    status('Finding the address for that pin…');
                    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            if (data && data.address && addressInput) {
                                addressInput.value = data.address;
                                status('');
                            } else {
                                status('The pin is set. Type the address if it did not fill in.');
                            }
                        })
                        .catch(function () {
                            status('The pin is set. Type the address if it did not fill in.');
                        });
                }
            }

            function write(lat, lng) {
                if (!latInput || !lngInput) {
                    return;
                }
                latInput.value = Number(lat).toFixed(7);
                lngInput.value = Number(lng).toFixed(7);
            }

            if (savedLat && savedLng) {
                place(Number(savedLat), Number(savedLng), false);
            }

            if (editable) {
                map.on('click', function (event) {
                    clearTimeout(lookupTimer);
                    place(event.latlng.lat, event.latlng.lng, true);
                });

                if (addressInput) {
                    addressInput.addEventListener('input', function () {
                        clearTimeout(lookupTimer);
                        lookupTimer = setTimeout(function () {
                            if (addressInput.value.trim().length < 3) {
                                return;
                            }
                            const url = new URL(el.dataset.geocode, window.location.origin);
                            url.searchParams.set('q', addressInput.value);
                            if (locationSelect && locationSelect.value) {
                                url.searchParams.set('location_id', locationSelect.value);
                            }
                            status('Looking up that address…');
                            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(function (response) { return response.ok ? response.json() : null; })
                                .then(function (data) {
                                    if (!data || !data.latitude) {
                                        status('That address could not be found. You can pin the home on the map.');
                                        return;
                                    }
                                    addressInput.value = data.address;
                                    map.setView([Number(data.latitude), Number(data.longitude)], 17);
                                    place(Number(data.latitude), Number(data.longitude), false);
                                    status('');
                                })
                                .catch(function () {
                                    status('That address could not be found. You can pin the home on the map.');
                                });
                        }, 700);
                    });
                }

                if (locationSelect) {
                    locationSelect.addEventListener('change', function () {
                        const next = centers[locationSelect.value];
                        if (!next) {
                            return;
                        }
                        map.setView([Number(next.lat), Number(next.lng)], 14);
                        if (marker) {
                            map.removeLayer(marker);
                            marker = null;
                        }
                        if (latInput) {
                            latInput.value = '';
                        }
                        if (lngInput) {
                            lngInput.value = '';
                        }
                        if (addressInput) {
                            addressInput.value = '';
                        }
                    });
                }
            }

            setTimeout(function () { map.invalidateSize(); }, 200);
        };
    </script>
    @if ($useGoogle)
        <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&callback=initVisitMap"></script>
    @else
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>initVisitMapFallback();</script>
    @endif
@endpush

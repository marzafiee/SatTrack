// dashboard.js - the main file that handles the cesium globe and all the satellite stuff
// honestly cesium is wild, took me a while to figure this out

// need this token for cesium to work, got it from their website
Cesium.Ion.defaultAccessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiIyOWZiNDRjMS0wNmU5LTRkMDQtYWYwNi02NmFjYjE1ZmZhMjIiLCJpZCI6MzczNDk5LCJpYXQiOjE3NjcwMjE0OTl9.8O5W7-_BPMuE1x7XuXUBrWnLpMOKPTRCY0JQHT8j3Yc';

// gonna initialize cesium after the page loads, learned that the hard way
let viewer;

// catch errors and show a little banner, helps when debugging
window.addEventListener('error', (ev) => {
    console.error('Dashboard runtime error:', ev.error || ev.message, ev);
    try {
        const bannerId = 'dashErrorBanner';
        if (!document.getElementById(bannerId)) {
            const b = document.createElement('div');
            b.id = bannerId;
            b.style.cssText = 'position:fixed;bottom:12px;right:12px;z-index:9999;background:rgba(255,80,80,0.95);color:white;padding:8px 12px;border-radius:6px;font-size:13px;box-shadow:0 6px 18px rgba(0,0,0,0.4)';
            b.textContent = 'Dashboard error: see console.';
            document.body.appendChild(b);
            setTimeout(() => { b.remove(); }, 6000);
        }
    } catch (e2) {
        // whatever
    }
});

window.addEventListener('unhandledrejection', (ev) => {
    console.error('Unhandled promise rejection:', ev.reason);
});

// keeping track of all the satellites on the globe
const satelliteEntities = new Map();
const satelliteColors = new Map(); // each satellite gets its own color
let showLabels = true;
let showOrbits = true;

// give each satellite a unique color based on its NORAD ID
function getSatelliteColor(norad_id, satellite_type) {
    if (satelliteColors.has(norad_id)) {
        return satelliteColors.get(norad_id);
    }

    // found this golden angle trick online, makes colors spread out nicely
    const hue = (norad_id * 137.508) % 360;
    const saturation = 0.7;
    const lightness = 0.6;

    const color = Cesium.Color.fromHsl(hue / 360, saturation, lightness, 1.0);
    satelliteColors.set(norad_id, color);
    return color;
}

// make a little satellite icon SVG, looks like a cross with a circle in the middle
function createSatelliteIcon(color) {
    const r = Math.floor(color.red * 255);
    const g = Math.floor(color.green * 255);
    const b = Math.floor(color.blue * 255);
    const rgb = `rgb(${r},${g},${b})`;

    const svg = `
        <svg width="32" height="32" xmlns="http://www.w3.org/2000/svg">
            <circle cx="16" cy="16" r="8" fill="${rgb}" stroke="white" stroke-width="2"/>
            <rect x="12" y="6" width="8" height="4" fill="${rgb}" opacity="0.8"/>
            <rect x="12" y="22" width="8" height="4" fill="${rgb}" opacity="0.8"/>
            <rect x="6" y="12" width="4" height="8" fill="${rgb}" opacity="0.8"/>
            <rect x="22" y="12" width="4" height="8" fill="${rgb}" opacity="0.8"/>
        </svg>
    `;
    return 'data:image/svg+xml;base64,' + btoa(svg);
}

// fetch satellites from the API and add them to the globe
async function loadSatellites() {
    try {
        // ajax call to get TLE data, still getting used to this async stuff
        const response = await fetch('api/get_tle_data.php');
        const data = await response.json();

        if (!data.success) {
            console.error('failed to load tle data');
            return;
        }

        // show a modal if any satellites are inactive
        if (data.inactive_satellites && data.inactive_satellites.length > 0) {
            data.inactive_satellites.forEach(sat => {
                showInactiveSatelliteModal(sat);
            });
        }

        // add each satellite to the globe
        if (data.satellites && data.satellites.length) {
            data.satellites.forEach(sat => addSatelliteToGlobe(sat));
        } else {
            // fallback if API doesn't return anything
            console.warn('no TLE satellites returned; falling back to watchlist from PHP');
            if (watchlistSatellites && watchlistSatellites.length) {
                watchlistSatellites.forEach((s, idx) => {
                    addSatelliteToGlobe({
                        norad_id: s.norad_id || (100000 + idx),
                        name: s.name,
                        tle_line1: '',
                        tle_line2: '',
                        satellite_type: s.satellite_type || 'Unknown'
                    });
                });
            }
        }

        startSatelliteTracking();

    } catch (error) {
        console.error('error loading satellites:', error);
        // if the API fails, at least show something on the globe
        if (watchlistSatellites && watchlistSatellites.length) {
            watchlistSatellites.forEach((s, idx) => {
                addSatelliteToGlobe({
                    norad_id: s.norad_id || (100000 + idx),
                    name: s.name,
                    tle_line1: '',
                    tle_line2: '',
                    satellite_type: s.satellite_type || 'Unknown'
                });
            });
            startSatelliteTracking();
        }
    }
}

// add a single satellite to the cesium globe
function addSatelliteToGlobe(satellite) {
    if (!viewer) {
        console.warn('Viewer not initialized yet, cannot add satellite');
        return;
    }
    const { norad_id, name, tle_line1, tle_line2, satellite_type } = satellite;

    const color = getSatelliteColor(norad_id, satellite_type);

    // if we don't have TLE data, just show a placeholder so the globe isn't empty
    if (!tle_line1 || !tle_line2) {
        console.warn(`Missing TLE data for ${name} (NORAD: ${norad_id}); rendering placeholder marker`);
        const lon = ((norad_id % 360) - 180);
        const lat = (((norad_id * 97) % 180) - 90);
        const altitude = 550000;
        const placeholder = viewer.entities.add({
            id: `sat_${norad_id}`,
            name: name,
            position: Cesium.Cartesian3.fromDegrees(lon, lat, altitude),
            point: {
                pixelSize: 10,
                color: color,
                outlineColor: Cesium.Color.WHITE,
                outlineWidth: 1.5
            },
            label: {
                text: name,
                font: '12px sans-serif',
                fillColor: color,
                outlineColor: Cesium.Color.BLACK,
                outlineWidth: 2,
                style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
                pixelOffset: new Cesium.Cartesian2(0, -12),
                show: showLabels
            },
            billboard: {
                image: createSatelliteIcon(color),
                scale: 1.2,
                verticalOrigin: Cesium.VerticalOrigin.CENTER
            }
        });
        satelliteEntities.set(norad_id, placeholder);
        if (viewer.scene && typeof viewer.scene.requestRender === 'function') viewer.scene.requestRender();
        return;
    }

    // cesium needs a position property that changes over time for the orbit path to work
    // this was confusing at first but makes sense now
    const positionProperty = new Cesium.SampledPositionProperty();
    positionProperty.setInterpolationOptions({
        interpolationDegree: 1,
        interpolationAlgorithm: Cesium.LinearApproximation
    });
    
    const entity = viewer.entities.add({
        id: `sat_${norad_id}`,
        name: name,
        position: positionProperty,
        availability: new Cesium.TimeIntervalCollection([
            new Cesium.TimeInterval({
                start: Cesium.JulianDate.now(),
                stop: Cesium.JulianDate.addDays(Cesium.JulianDate.now(), 1, new Cesium.JulianDate())
            })
        ]),
        point: {
            pixelSize: 8,
            color: color,
            outlineColor: Cesium.Color.WHITE,
            outlineWidth: 1.5,
            heightReference: Cesium.HeightReference.NONE
        },
        label: {
            text: name,
            font: '14px sans-serif',
            fillColor: color,
            outlineColor: Cesium.Color.BLACK,
            outlineWidth: 3,
            style: Cesium.LabelStyle.FILL_AND_OUTLINE,
            verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
            pixelOffset: new Cesium.Cartesian2(0, -15),
            show: showLabels,
            scale: 1.0
        },
        path: {
            show: showOrbits,
            leadTime: 0,
            trailTime: 0,
            width: 3,
            resolution: 120,
            material: new Cesium.PolylineGlowMaterialProperty({
                glowPower: 0.5,
                color: color.withAlpha(0.9)
            }),
            clampToGround: false
        },
        billboard: {
            image: createSatelliteIcon(color),
            scale: 1.5,
            verticalOrigin: Cesium.VerticalOrigin.CENTER,
            disableDepthTestDistance: Number.POSITIVE_INFINITY
        }
    });

    satelliteEntities.set(norad_id, entity);

    console.log('added satellite to globe:', norad_id, name);

    // calculate where the satellite is and draw its orbit path
    updateSatellitePosition(norad_id, tle_line1, tle_line2);

    setTimeout(refreshWatchlistButtons, 150);
}

// figure out where the satellite is and draw its orbit path
// this uses satellite.js to parse the TLE data and calculate positions
function updateSatellitePosition(norad_id, tle1, tle2) {
    if (!viewer) return;
    const entity = satelliteEntities.get(norad_id);
    if (!entity || !entity.position) return;

    try {
        // parse the TLE lines into something satellite.js can work with
        const satrec = satellite.twoline2satrec(tle1, tle2);

        const now = new Date();
        const nowJulian = Cesium.JulianDate.fromDate(now);

        // figure out how long one orbit takes
        const meanMotion = satrec.no; // how many times it goes around per day
        const periodSeconds = (86400 / meanMotion);

        // sample points along the orbit to draw the path
        const samples = 180; // seems like enough points for a smooth line
        const timeStep = periodSeconds / samples;

        // clear out old samples if there are any
        try {
            entity.position.removeAllSamples();
        } catch (e) {
            // no big deal if there weren't any
        }

        // tell cesium when this satellite exists
        const endJulian = Cesium.JulianDate.addSeconds(nowJulian, periodSeconds * 2, new Cesium.JulianDate());
        entity.availability = new Cesium.TimeIntervalCollection([
            new Cesium.TimeInterval({
                start: nowJulian,
                stop: endJulian
            })
        ]);

        // calculate positions for 2 full orbits so the path looks complete
        for (let orbit = 0; orbit < 2; orbit++) {
            for (let i = 0; i <= samples; i++) {
                const timeOffset = (orbit * periodSeconds) + (i * timeStep);
                const time = Cesium.JulianDate.addSeconds(nowJulian, timeOffset, new Cesium.JulianDate());
                const date = Cesium.JulianDate.toDate(time);

                // use satellite.js to figure out where it is at this time
                const positionAndVelocity = satellite.propagate(satrec, date);

                if (positionAndVelocity.position) {
                    const positionEci = positionAndVelocity.position;

                    // convert from ECI coordinates to lat/lng (took me forever to understand this)
                    const gmst = satellite.gstime(date);

                    let positionGd;
                    if (typeof satellite.eciToGeodetic === 'function') {
                        positionGd = satellite.eciToGeodetic(positionEci, gmst);
                    } else {
                        // older version of satellite.js uses different functions
                        const positionEcf = satellite.eciToEcf(positionEci, gmst);
                        positionGd = satellite.ecfToGeodetic(positionEcf);
                    }

                    // convert to cesium's coordinate system
                    const longitude = Cesium.Math.toDegrees(positionGd.longitude);
                    const latitude = Cesium.Math.toDegrees(positionGd.latitude);
                    const height = (positionGd.height || 0) * 1000; // km to meters

                    const position = Cesium.Cartesian3.fromDegrees(longitude, latitude, height);
                    entity.position.addSample(time, position);
                }
            }
        }

        // make sure the satellite icon shows up at the current position
        const currentPos = satellite.propagate(satrec, now);
        if (currentPos.position) {
            const currentEci = currentPos.position;
            const currentGmst = satellite.gstime(now);
            let currentGd;
            if (typeof satellite.eciToGeodetic === 'function') {
                currentGd = satellite.eciToGeodetic(currentEci, currentGmst);
            } else {
                const currentEcf = satellite.eciToEcf(currentEci, currentGmst);
                currentGd = satellite.ecfToGeodetic(currentEcf);
            }
            const currentLon = Cesium.Math.toDegrees(currentGd.longitude);
            const currentLat = Cesium.Math.toDegrees(currentGd.latitude);
            const currentHeight = (currentGd.height || 0) * 1000;
            const currentPosition = Cesium.Cartesian3.fromDegrees(currentLon, currentLat, currentHeight);
            
            entity.availability.addInterval(new Cesium.TimeInterval({
                start: nowJulian,
                stop: endJulian
            }));
        }

        // force cesium to redraw
        if (viewer.scene && typeof viewer.scene.requestRender === 'function') {
            viewer.scene.requestRender();
        }

    } catch (error) {
        console.error(`Error calculating orbit for satellite ${norad_id}:`, error);
        const sat = watchlistSatellites.find(s => s.norad_id == norad_id);
        const satName = sat ? sat.name : `Satellite ${norad_id}`;
        showSatelliteError(satName, norad_id, 'Failed to calculate orbit. TLE data may be invalid or expired.');

        // if the TLE is broken, just draw a fake circular orbit so something shows up
        const now = Cesium.JulianDate.now();
        const period = 90 * 60; // typical orbit is about 90 minutes
        const samples = 60;

        for (let i = 0; i < samples; i++) {
            const time = Cesium.JulianDate.addSeconds(now, i * period / samples, new Cesium.JulianDate());
            const angle = (i / samples) * 2 * Math.PI;
            const altitude = 550000;
            const lat = Math.sin(angle) * 51.6;
            const lng = (angle * 180 / Math.PI) % 360 - 180;
            const position = Cesium.Cartesian3.fromDegrees(lng, lat, altitude);
            entity.position.addSample(time, position);
        }
    }
}

// show error message for satellites that can't be displayed
function showSatelliteError(satelliteName, norad_id, errorMessage) {
    // create error modal if it doesn't exist
    let errorModal = document.getElementById('satelliteErrorModal');
    if (!errorModal) {
        errorModal = document.createElement('div');
        errorModal.id = 'satelliteErrorModal';
        errorModal.className = 'modal';
        errorModal.style.cssText = `
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        `;
        document.body.appendChild(errorModal);
    }

    const modalContent = document.createElement('div');
    modalContent.className = 'logout-container';
    modalContent.style.cssText = `
        max-width: 500px;
        width: 90%;
        position: relative;
        z-index: 10;
    `;

    modalContent.innerHTML = `
        <div class="logout-icon" style="font-size: 4rem;">⚠️</div>
        <p class="logout-subtitle">Satellite Display Error</p>
        <h1 class="logout-title">Cannot Display Satellite</h1>
        <p class="logout-description">
            ${errorMessage}
        </p>
        
        <div style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid var(--color-card-border); border-radius: 12px; padding: 1.5rem; margin: 2rem 0; text-align: left;">
            <h3 style="font-size: 1.3rem; margin-bottom: 1rem; color: var(--color-text-heading); font-weight: 600;">${satelliteName}</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.95rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-subheading);">NORAD ID:</span>
                    <span style="color: var(--color-text-heading); font-weight: 500;">${norad_id}</span>
                </div>
            </div>
        </div>
        
        <div style="background: rgba(99, 96, 255, 0.1); border-left: 3px solid var(--color-accent); padding: 1rem; border-radius: 6px; margin: 1.5rem 0;">
            <p style="color: var(--color-text-body); font-size: 0.95rem; line-height: 1.7; margin: 0;">
                This satellite may have invalid or expired TLE data. Pass predictions may still be available, but the satellite cannot be visualized on the globe.
            </p>
        </div>

        <div class="logout-actions">
            <button onclick="closeSatelliteErrorModal()" class="btn btn-primary" style="width: 100%;">
                Understood
            </button>
        </div>
    `;

    errorModal.innerHTML = '';
    errorModal.appendChild(modalContent);
    errorModal.style.display = 'flex';
}

// make function globally accessible
window.closeSatelliteErrorModal = function () {
    const modal = document.getElementById('satelliteErrorModal');
    if (modal) {
        modal.style.display = 'none';
    }
};

// close modal on outside click
document.addEventListener('click', (e) => {
    const modal = document.getElementById('satelliteErrorModal');
    if (modal && e.target === modal) {
        window.closeSatelliteErrorModal();
    }
});

// update satellite positions periodically
// not really using this yet but keeping it for later
function startSatelliteTracking() {
    setInterval(() => {
        watchlistSatellites.forEach(sat => {
            const entity = satelliteEntities.get(sat.norad_id);
            if (entity && entity.position) {
                // could refresh TLE data here if needed
            }
        });
    }, 30000);
}

// toggle satellite visibility (Show / Hide)
window.toggleSatelliteVisibility = function (norad_id, btn) {
    if (!viewer) return;
    const id = Number(norad_id);
    const entity = satelliteEntities.get(id);
    if (entity) {
        entity.show = !entity.show;
        // ensure orbit path is also shown/hidden with the satellite
        if (entity.path) {
            entity.path.show = entity.show && showOrbits;
        }
        if (entity.label) {
            entity.label.show = entity.show && showLabels;
        }
        btn.textContent = entity.show ? 'Hide' : 'Show';

        // request render to update display
        if (viewer.scene && typeof viewer.scene.requestRender === 'function') {
            viewer.scene.requestRender();
        }
        return;
    }

    // not present on globe yet — try to add from watchlist
    const sat = watchlistSatellites.find(s => Number(s.norad_id) === id || s.norad_id == id);
    if (sat) {
        addSatelliteToGlobe({
            norad_id: sat.norad_id,
            name: sat.name,
            tle_line1: sat.tle_line1 || '',
            tle_line2: sat.tle_line2 || '',
            satellite_type: sat.satellite_type || ''
        });
        // optimistic UI change
        btn.textContent = 'Hide';
        return;
    }

    alert('Satellite data is not available locally; please reload the page.');
}

// update watchlist show/hide buttons based on loaded entities
function refreshWatchlistButtons() {
    document.querySelectorAll('.watchlist-item').forEach(el => {
        const norad = el.dataset.norad;
        const btn = el.querySelector('.btn-show');
        if (!btn) return;
        const entity = satelliteEntities.get(Number(norad));
        if (entity) {
            btn.textContent = (entity.show === false) ? 'Show' : 'Hide';
        } else {
            btn.textContent = 'Show';
        }
    });
}

// focus camera on specific satellite
window.focusSatellite = function (norad_id) {
    if (!viewer) return;
    const entity = satelliteEntities.get(norad_id);
    if (!entity) {
        console.log('satellite not loaded yet');
        return;
    }

    // fly camera to satellite
    viewer.trackedEntity = entity;

    // show info panel
    showSatelliteInfo(norad_id);
}

// show satellite info overlay
function showSatelliteInfo(norad_id) {
    const sat = watchlistSatellites.find(s => s.norad_id == norad_id);
    if (!sat) return;

    // create info panel if doesn't exist
    let infoPanel = document.getElementById('satInfo');
    if (!infoPanel) {
        infoPanel = document.createElement('div');
        infoPanel.id = 'satInfo';
        infoPanel.style.cssText = `
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            min-width: 300px;
            z-index: 100;
        `;
        document.querySelector('.globe-container').appendChild(infoPanel);
    }

    infoPanel.innerHTML = `
        <h3 style="margin-bottom: 0.5rem;">${sat.name}</h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
            NORAD: ${sat.norad_id} | Type: ${sat.satellite_type}
        </p>
        <button onclick="closeSatInfo()" style="background: var(--bg-card); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; width: 100%;">
            close
        </button>
    `;
}

window.closeSatInfo = function () {
    const panel = document.getElementById('satInfo');
    if (panel) panel.remove();
    if (viewer) viewer.trackedEntity = undefined;
}

// control functions
function resetCamera() {
    if (!viewer) return;
    viewer.trackedEntity = undefined;
    viewer.camera.flyTo({
        destination: Cesium.Cartesian3.fromDegrees(
            userLocation.lng,
            userLocation.lat,
            16000000
        ),
        duration: 2
    });
    closeSatInfo();
}

function toggleSatelliteLabels() {
    showLabels = !showLabels;
    satelliteEntities.forEach(entity => {
        if (entity.label) {
            entity.label.show = showLabels;
        }
    });

    // update button highlight
    const btn = document.querySelector('button[onclick="toggleSatelliteLabels()"]');
    if (btn) {
        if (showLabels) {
            btn.classList.add('active');
            btn.style.background = 'var(--accent)';
            btn.style.color = 'white';
        } else {
            btn.classList.remove('active');
            btn.style.background = 'rgba(0, 0, 0, 0.7)';
            btn.style.color = 'white';
        }
    }
}

function toggleOrbits() {
    showOrbits = !showOrbits;
    satelliteEntities.forEach(entity => {
        if (entity.path) {
            entity.path.show = showOrbits;
        }
    });
    // request render to update display
    if (viewer && viewer.scene && typeof viewer.scene.requestRender === 'function') {
        viewer.scene.requestRender();
    }

    // update button highlight
    const btn = document.querySelector('button[onclick="toggleOrbits()"]');
    if (btn) {
        if (showOrbits) {
            btn.classList.add('active');
            btn.style.background = 'var(--accent)';
            btn.style.color = 'white';
        } else {
            btn.classList.remove('active');
            btn.style.background = 'rgba(0, 0, 0, 0.7)';
            btn.style.color = 'white';
        }
    }
}

// expose control functions globally
window.resetCamera = resetCamera;
window.toggleSatelliteLabels = toggleSatelliteLabels;
window.toggleOrbits = toggleOrbits;

// show modal for inactive satellites
function showInactiveSatelliteModal(satellite) {
    // create modal if it doesn't exist
    let modalContainer = document.getElementById('inactiveSatelliteModal');
    if (!modalContainer) {
        modalContainer = document.createElement('div');
        modalContainer.id = 'inactiveSatelliteModal';
        modalContainer.className = 'modal';
        modalContainer.style.cssText = `
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        `;
        document.body.appendChild(modalContainer);
    }

    // format last updated date
    let lastActiveText = 'Unknown';
    if (satellite.last_updated) {
        const lastDate = new Date(satellite.last_updated);
        const now = new Date();
        const diffMs = now - lastDate;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

        if (diffDays > 0) {
            lastActiveText = `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        } else if (diffHours > 0) {
            lastActiveText = `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else {
            lastActiveText = 'Recently';
        }

        lastActiveText += ` (${lastDate.toLocaleDateString()} ${lastDate.toLocaleTimeString()})`;
    }

    // create modal content matching logout.php style
    const modalContent = document.createElement('div');
    modalContent.className = 'logout-container';
    modalContent.style.cssText = `
        max-width: 500px;
        width: 90%;
        position: relative;
        z-index: 10;
    `;

    modalContent.innerHTML = `
        <div class="logout-icon" style="font-size: 4rem;">⚠️</div>
        <p class="logout-subtitle">Satellite Status</p>
        <h1 class="logout-title">Satellite Inactive</h1>
        <p class="logout-description">
            This satellite is not currently active or transmitting data.
        </p>
        
        <div style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid var(--color-card-border); border-radius: 12px; padding: 1.5rem; margin: 2rem 0; text-align: left;">
            <h3 style="font-size: 1.3rem; margin-bottom: 1rem; color: var(--color-text-heading); font-weight: 600;">${satellite.name}</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.95rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-subheading);">NORAD ID:</span>
                    <span style="color: var(--color-text-heading); font-weight: 500;">${satellite.norad_id}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-subheading);">Type:</span>
                    <span style="color: var(--color-text-heading); font-weight: 500;">${satellite.satellite_type || 'Unknown'}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-subheading);">Last Active:</span>
                    <span style="color: var(--color-text-heading); font-weight: 500;">${lastActiveText}</span>
                </div>
            </div>
        </div>
        
        <div style="background: rgba(99, 96, 255, 0.1); border-left: 3px solid var(--color-accent); padding: 1rem; border-radius: 6px; margin: 1.5rem 0;">
            <p style="color: var(--color-text-body); font-size: 0.95rem; line-height: 1.7; margin: 0;">
                This satellite may have been decommissioned, re-entered the atmosphere, or is no longer transmitting data. 
                The orbit information shown is from when it was last tracked.
            </p>
        </div>

        <div class="logout-actions">
            <button onclick="closeInactiveSatelliteModal()" class="btn btn-primary" style="width: 100%;">
                Understood
            </button>
        </div>
    `;

    modalContainer.innerHTML = '';
    modalContainer.appendChild(modalContent);
    modalContainer.style.display = 'flex';
}

// make function globally accessible
window.closeInactiveSatelliteModal = function () {
    const modal = document.getElementById('inactiveSatelliteModal');
    if (modal) {
        modal.style.display = 'none';
    }
};

// close modal on outside click
document.addEventListener('click', (e) => {
    const modal = document.getElementById('inactiveSatelliteModal');
    if (modal && e.target === modal) {
        window.closeInactiveSatelliteModal();
    }
});

// calculate satellite passes
async function calculatePasses() {
    const btn = document.getElementById('calculatePassesBtn');
    if (!btn) return;

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'calculating...';

    try {
        const response = await fetch('api/calculate_passes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await response.json();

        if (data.success) {
            alert(`Successfully calculated ${data.passes_calculated} passes for ${data.satellites_processed} satellites!`);
            // optionally redirect to passes page
            // window.location.href = 'passes.php';
        } else {
            alert(data.message || 'Failed to calculate passes');
        }
    } catch (error) {
        console.error('Error calculating passes:', error);
        alert('Error calculating passes. Please try again.');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// make function globally accessible
window.calculatePasses = calculatePasses;

// search functionality
const searchInput = document.getElementById('searchSatellites');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.sat-card-compact').forEach(card => {
            const name = card.dataset.name || '';
            card.style.display = name.includes(query) ? 'block' : 'none';
        });
    });
}

// add to watchlist
window.addToWatchlist = async function (satelliteId) {
    try {
        const response = await fetch('api/add_watchlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ satellite_id: satelliteId })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        // Check if already in watchlist - this is not really an error, just informational
        if (!data.success && data.message && data.message.includes('already in watchlist')) {
            // Don't show error, just inform the user
            console.log('Satellite already in watchlist');
            return;
        }
        
        if (data.success) {
            // avoid a full reload — update the watchlist UI and add to globe
            const sat = data.satellite;
            if (sat) {
                // Check if already in local array to avoid duplicates
                const alreadyExists = watchlistSatellites.some(s => s.id == sat.id || s.norad_id == sat.norad_id);
                if (!alreadyExists) {
                watchlistSatellites.push({ id: sat.id, name: sat.name, norad_id: sat.norad_id, satellite_type: sat.satellite_type, tle_line1: sat.tle_line1, tle_line2: sat.tle_line2 });
                }

                // Check if DOM element already exists
                const container = document.getElementById('watchlistContainer');
                const existing = container.querySelector(`[data-sat-id="${sat.id}"]`);
                if (existing) {
                    console.log('Satellite already in watchlist UI');
                    return;
                }

                // create DOM node
                const div = document.createElement('div');
                div.className = 'watchlist-item sat-card-compact';
                div.dataset.satId = sat.id;
                div.dataset.norad = sat.norad_id;
                div.dataset.name = sat.name;
                div.onclick = () => focusSatellite(sat.norad_id);

                div.innerHTML = `
                    <h4>${sat.name}</h4>
                    <div class="meta">
                        <span class="sat-type">${sat.satellite_type || ''}</span>
                        <div class="meta-actions">
                            <button class="btn-show" onclick="event.stopPropagation(); toggleSatelliteVisibility(${sat.norad_id}, this)">Show</button>
                            <button class="btn-remove" onclick="event.stopPropagation(); removeFromWatchlist(${sat.id})">×</button>
                        </div>
                    </div>
                `;

                // prepend new satellite to top of list
                container.insertBefore(div, container.firstChild);

                // add to globe (only if not already there)
                if (!satelliteEntities.has(sat.norad_id)) {
                addSatelliteToGlobe({ norad_id: sat.norad_id, name: sat.name, tle_line1: sat.tle_line1 || '', tle_line2: sat.tle_line2 || '', satellite_type: sat.satellite_type || '' });
                }

                // refresh UI state
                refreshWatchlistButtons();
            }

            // Clear the search input
            const searchInput = document.getElementById('satSearch');
            if (searchInput) searchInput.value = '';
        } else {
            // Only show error if it's not the "already in watchlist" message
            if (data.message && !data.message.includes('already')) {
            alert(data.message || 'failed to add satellite');
            }
        }
    } catch (error) {
        console.error('addToWatchlist error:', error);
        alert('error adding satellite: ' + error.message);
    }
}

// remove from watchlist
window.removeFromWatchlist = async function (satelliteId) {
    if (!confirm('remove this satellite from your watchlist?')) return;

    try {
        const response = await fetch('api/remove_watchlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ satellite_id: satelliteId })
        });

        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'failed to remove satellite');
        }
    } catch (error) {
        alert('error removing satellite');
    }
}

// share modal functions
window.openShareModal = function () {
    document.getElementById('shareModal').classList.add('show');
}

window.closeShareModal = function () {
    document.getElementById('shareModal').classList.remove('show');
}

window.copyShareCode = function () {
    navigator.clipboard.writeText(shareCode).then(() => {
        alert('share code copied to clipboard!');
    });
}

window.openImportModal = function () {
    document.getElementById('importModal').classList.add('show');
}

window.closeImportModal = function () {
    document.getElementById('importModal').classList.remove('show');
}

window.importWatchlist = async function () {
    const code = document.getElementById('importCode').value.trim();
    if (!code) {
        alert('please enter a share code');
        return;
    }

    try {
        const response = await fetch('api/import_watchlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ share_code: code })
        });

        const data = await response.json();
        if (data.success) {
            alert(`imported ${data.count} satellites!`);
            location.reload();
        } else {
            alert(data.message || 'failed to import watchlist');
        }
    } catch (error) {
        alert('error importing watchlist');
    }
}

// helper: populate datalist for satellite search
function populateSatelliteDatalist() {
    const list = document.getElementById('satList');
    const input = document.getElementById('satSearch');
    const dropdownList = document.getElementById('satDropdownList');
    if (!list || !input || typeof allSatellites === 'undefined') return;
    window.__satMap = {};
    window.__satIndex = [];

    // clear existing options
    list.innerHTML = '';
    if (dropdownList) dropdownList.innerHTML = '';

    allSatellites.forEach(s => {
        // only include active satellites in the add list
        if (typeof s.is_active !== 'undefined' && !s.is_active) return;
        const val = `${s.name} (${s.norad_id})`;
        const opt = document.createElement('option');
        opt.value = val;
        list.appendChild(opt);
        window.__satMap[val] = s.id;
        window.__satIndex.push({ label: val, id: s.id });

        // add to dropdown list
        if (dropdownList) {
            const dropdownItem = document.createElement('div');
            dropdownItem.style.cssText = 'padding: 0.5rem; cursor: pointer; color: var(--text-primary); transition: background 0.2s;';
            dropdownItem.textContent = val;
            dropdownItem.dataset.value = val;
            dropdownItem.dataset.id = s.id;
            dropdownItem.addEventListener('mouseenter', () => {
                dropdownItem.style.background = 'rgba(255,255,255,0.05)';
            });
            dropdownItem.addEventListener('mouseleave', () => {
                dropdownItem.style.background = 'transparent';
            });
            dropdownItem.addEventListener('click', () => {
                input.value = val;
                document.getElementById('satDropdown').style.display = 'none';
            });
            dropdownList.appendChild(dropdownItem);
        }
    });

    // setup dropdown toggle
    const dropdownToggle = document.getElementById('satDropdownToggle');
    const dropdown = document.getElementById('satDropdown');
    if (dropdownToggle && dropdown) {
        dropdownToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
            dropdownToggle.textContent = isVisible ? '▼' : '▲';
        });

        // close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== dropdownToggle && e.target !== input) {
                dropdown.style.display = 'none';
                dropdownToggle.textContent = '▼';
            }
        });

        // filter dropdown on input
        input.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const items = dropdownList.querySelectorAll('div');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? 'block' : 'none';
            });
        });
    }
}

function setupWatchlistMenu() {
    const toggle = document.getElementById('watchlistMenuToggle');
    const menu = document.getElementById('watchlistMenu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('show');
    });
    document.addEventListener('click', () => menu.classList.remove('show'));
}

// help modal handlers
function openHelpModal() {
    const m = document.getElementById('helpModal');
    if (!m) return;
    m.classList.add('show');
    m.setAttribute('aria-hidden', 'false');
}
function closeHelpModal() {
    const m = document.getElementById('helpModal');
    if (!m) return;
    m.classList.remove('show');
    m.setAttribute('aria-hidden', 'true');
}

// make help modal functions globally accessible
window.openHelpModal = openHelpModal;
window.closeHelpModal = closeHelpModal;

// wire help toggle - ensure it's set up after DOM loads
document.addEventListener('DOMContentLoaded', () => {
    const helpToggle = document.getElementById('helpToggle');
    if (helpToggle) {
        helpToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            openHelpModal();
        });
    }
});

// close help modal on outside click
document.addEventListener('click', (e) => {
    const m = document.getElementById('helpModal');
    if (!m) return;
    if (!m.classList.contains('show')) return;
    if (!e.target.closest('.modal-content')) {
        closeHelpModal();
    }
});

// close on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeHelpModal();
        closeShareModal();
        closeImportModal();
    }
});

function setupGlobeSizer() {
    const slider = document.getElementById('globeSizeSlider');
    const display = document.getElementById('globeSizeValue');
    if (!slider || !display) return;
    const stored = parseInt(localStorage.getItem('sat_globe_size'), 10);
    const initial = Number.isFinite(stored) ? stored : parseInt(getComputedStyle(document.getElementById('cesiumContainer')).height, 10) || 520;
    slider.value = initial;
    display.textContent = initial;
    setGlobeSize(initial);

    slider.addEventListener('input', (e) => {
        const v = Number(e.target.value);
        display.textContent = v;
        setGlobeSize(v);
    });
}

// initialize on load
document.addEventListener('DOMContentLoaded', () => {
    // initialize the cesium viewer after DOM is ready
    try {
        const container = document.getElementById('cesiumContainer');
        if (!container) {
            console.error('Cesium container not found!');
            return;
        }

        viewer = new Cesium.Viewer('cesiumContainer', {
            shouldAnimate: true,
            timeline: true,
            animation: true,
            baseLayerPicker: false,
            geocoder: false,
            homeButton: false,
            sceneModePicker: false,
            navigationHelpButton: false,
            fullscreenButton: false,
            imageryProvider: new Cesium.IonImageryProvider({ assetId: 3954 }), // dark earth
        });

        // make sure the globe renders even in requestRenderMode cases
        viewer.scene.globe.enableLighting = false; // reduce GPU cost
        viewer.scene.skyAtmosphere.show = true;
        viewer.scene.requestRenderMode = true; // only render when needed
        viewer.clock.shouldAnimate = true; // enable animation for real-time satellite movement
        viewer.clock.multiplier = 1; // real-time speed

        // camera settings for smooth movement
        viewer.scene.screenSpaceCameraController.minimumZoomDistance = 2500000; // 2500km
        viewer.scene.screenSpaceCameraController.maximumZoomDistance = 40000000; // 40,000km

        // set initial camera position to user's location
        if (userLocation && userLocation.lng && userLocation.lat) {
            viewer.camera.flyTo({
                destination: Cesium.Cartesian3.fromDegrees(
                    userLocation.lng,
                    userLocation.lat,
                    16000000 // 16000km altitude
                ),
                duration: 2
            });
        }

        // hide loading overlay once ready
        viewer.scene.globe.tileLoadProgressEvent.addEventListener(queueLength => {
            if (queueLength === 0) {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) loadingOverlay.style.display = 'none';
            }
        });

        // ensure imagery is present; added OpenStreetMap fallback below Ion layer
        try {
            const osm = new Cesium.UrlTemplateImageryProvider({
                url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                credit: '© OpenStreetMap contributors',
                subdomains: ['a', 'b', 'c']
            });
            // add as base layer if imagery failed previously (insert at bottom)
            viewer.imageryLayers.addImageryProvider(osm);
        } catch (e) {
            console.warn('OSM fallback failed', e);
        }

        // force an initial render
        setTimeout(() => {
            if (viewer && viewer.scene && typeof viewer.scene.requestRender === 'function') {
                viewer.scene.requestRender();
            }
        }, 250);
    } catch (e) {
        console.error('Cesium initialization error:', e);
    }

    populateSatelliteDatalist();
    setupWatchlistMenu();
    setupGlobeSizer();

    // wire help toggle
    const helpToggle = document.getElementById('helpToggle');
    if (helpToggle) {
        helpToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            openHelpModal();
        });
    }

    // initialize button states on page load
    const orbitsBtn = document.querySelector('button[onclick="toggleOrbits()"]');
    const labelsBtn = document.querySelector('button[onclick="toggleSatelliteLabels()"]');
    if (orbitsBtn && showOrbits) {
        orbitsBtn.classList.add('active');
        orbitsBtn.style.background = 'var(--accent)';
        orbitsBtn.style.color = 'white';
    }
    if (labelsBtn && showLabels) {
        labelsBtn.classList.add('active');
        labelsBtn.style.background = 'var(--accent)';
        labelsBtn.style.color = 'white';
    }

    // toggle sidebar (reusable for buttons in different places)
    function toggleSidebar() {
        const el = document.querySelector('.sidebar-left');
        if (!el) return;
        const isCollapsed = el.classList.contains('collapsed');
        if (isCollapsed) {
            el.classList.remove('collapsed');
            document.documentElement.style.setProperty('--sidebar-width', '320px');
        } else {
            el.classList.add('collapsed');
            document.documentElement.style.setProperty('--sidebar-width', '64px');
        }
    }

    // wire both possible buttons (old header and new top-bar)
    document.getElementById('collapseSidebarBtn')?.addEventListener('click', (e) => { e.preventDefault(); toggleSidebar(); });
    document.getElementById('collapseSidebarBtnTop')?.addEventListener('click', (e) => { e.preventDefault(); toggleSidebar(); });

    // sidebar resize drag using the resizer handle
    (function setupSidebarResizer() {
        const resizer = document.querySelector('.sidebar-resizer');
        const sidebar = document.querySelector('.sidebar-left');
        if (!resizer || !sidebar) return;

        let dragging = false;
        resizer.addEventListener('mousedown', (e) => {
            dragging = true;
            document.body.style.userSelect = 'none';
        });
        document.addEventListener('mousemove', (e) => {
            if (!dragging) return;
            const x = e.clientX;
            const w = Math.max(64, Math.min(480, x));
            sidebar.style.width = w + 'px';
            document.documentElement.style.setProperty('--sidebar-width', w + 'px');
        });
        document.addEventListener('mouseup', () => {
            if (!dragging) return;
            dragging = false;
            document.body.style.userSelect = '';
        });

        // touch support
        resizer.addEventListener('touchstart', () => { dragging = true; });
        document.addEventListener('touchmove', (e) => {
            if (!dragging) return;
            const touch = e.touches[0];
            const x = touch.clientX;
            const w = Math.max(64, Math.min(480, x));
            sidebar.style.width = w + 'px';
            document.documentElement.style.setProperty('--sidebar-width', w + 'px');
        });
        document.addEventListener('touchend', () => { dragging = false; });
    })();

    // Change location - prompt for a place (city or country) and let backend resolve to lat/lng
    document.getElementById('changeLocationBtn')?.addEventListener('click', async () => {
        const place = prompt('Enter a city or country name (we will resolve it for you)');
        if (!place) return;
        try {
            const resp = await fetch('api/update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ place })
            });
            const data = await resp.json();
            if (data.success) {
                document.getElementById('userLocationDisplay').textContent = data.display || data.country || (data.lat + ', ' + data.lng);
                // fly to new location and ensure render
                viewer.camera.flyTo({ destination: Cesium.Cartesian3.fromDegrees(data.lng, data.lat, 16000000), duration: 1.4 });
                if (viewer && viewer.scene && typeof viewer.scene.requestRender === 'function') viewer.scene.requestRender();
            } else {
                alert(data.message || 'Failed to resolve location');
            }
        } catch (err) {
            console.error(err);
            alert('Error contacting server');
        }
    });

    // wire add button after datalist is populated
    document.getElementById('addSatelliteBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        const val = (document.getElementById('satSearch')?.value || '').trim();
        let id = window.__satMap?.[val];
        if (!id && val) {
            const found = window.__satIndex?.find(x => x.label.toLowerCase() === val.toLowerCase() || x.label.toLowerCase().includes(val.toLowerCase()));
            if (found) id = found.id;
        }
        if (!id) {
            alert('Please select a satellite from the dropdown');
            return;
        }
        addToWatchlist(id);
    });

    // load satellites after viewer is initialized
    if (viewer) {
    loadSatellites().then(() => {
        // refresh show/hide button state after satellites are loaded
        setTimeout(refreshWatchlistButtons, 300);
    });
    }

    // smooth height transition for globe on resize
    const cesium = document.getElementById('cesiumContainer');
    if (cesium) cesium.style.transition = 'height 240ms ease';

    // ensure Cesium paints correctly after layout changes (fix for hidden/zero-size init)
    setTimeout(() => {
        try {
            if (viewer && viewer.scene && typeof viewer.scene.requestRender === 'function') {
                viewer.scene.requestRender();
            }
            // re-fly camera to current user location to ensure proper view and a render
            if (viewer && userLocation && userLocation.lng && userLocation.lat) {
            viewer.camera.flyTo({ destination: Cesium.Cartesian3.fromDegrees(userLocation.lng, userLocation.lat, 16000000), duration: 1.2 });
            }
        } catch (e) {
            console.warn('Cesium re-render attempt failed', e);
        }
    }, 500);
});
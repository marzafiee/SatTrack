<?php
/**
 * sattrack - satellite database seeding script
 * fetches satellite data from celestrak and seeds the database
 */

require_once '../includes/db_config.php';

// satellite categories to fetch from celestrak
$categories = [
    'stations' => 'Space Stations',
    'starlink' => 'Starlink',
    'weather' => 'Weather',
    'noaa' => 'NOAA',
    'goes' => 'GOES',
    'resource' => 'Earth Resources',
    'sarsat' => 'Search & Rescue',
    'geo' => 'Geostationary',
    'amateur' => 'Amateur Radio',
    'x-comm' => 'Experimental Communications',
    'globalstar' => 'Globalstar',
    'iridium' => 'Iridium',
    'iridium-NEXT' => 'Iridium NEXT',
    'orbcomm' => 'Orbcomm',
    'ses' => 'SES',
    'intelsat' => 'Intelsat'
];

/**
 * maps satellite names to appropriate types
 */
function mapSatelliteType($name) {
    $name = strtoupper($name);
    
    // iss and related
    if (strpos($name, 'ISS') !== false || 
        strpos($name, 'ZARYA') !== false) {
        return 'ISS';
    }
    
    // chinese space station
    if (strpos($name, 'TIANHE') !== false || 
        strpos($name, 'MENGTIAN') !== false || 
        strpos($name, 'WENTIAN') !== false ||
        strpos($name, 'CSS') !== false) {
        return 'ISS'; // treating all space stations as iss type
    }
    
    // starlink
    if (strpos($name, 'STARLINK') !== false) {
        return 'Starlink';
    }
    
    // weather satellites
    if (strpos($name, 'NOAA') !== false || 
        strpos($name, 'GOES') !== false || 
        strpos($name, 'METOP') !== false ||
        strpos($name, 'WEATHER') !== false ||
        strpos($name, 'METEOR') !== false) {
        return 'Weather';
    }
    
    // communication satellites
    if (strpos($name, 'IRIDIUM') !== false || 
        strpos($name, 'GLOBALSTAR') !== false || 
        strpos($name, 'INTELSAT') !== false ||
        strpos($name, 'SES') !== false ||
        strpos($name, 'ORBCOMM') !== false ||
        strpos($name, 'VIASAT') !== false) {
        return 'Communication';
    }
    
    // crew/cargo vehicles
    if (strpos($name, 'DRAGON') !== false || 
        strpos($name, 'CYGNUS') !== false || 
        strpos($name, 'PROGRESS') !== false ||
        strpos($name, 'SOYUZ') !== false) {
        return 'ISS'; // spacecraft visiting iss
    }
    
    // debris
    if (strpos($name, 'DEB') !== false || 
        strpos($name, 'DEBRIS') !== false ||
        strpos($name, 'R/B') !== false) {
        return 'Debris';
    }
    
    // military
    if (strpos($name, 'USA-') !== false || 
        strpos($name, 'NROL') !== false ||
        strpos($name, 'CLASSIFIED') !== false) {
        return 'Military';
    }
    
    // default to scientific
    return 'Scientific';
}

/* fetches tle data from celestrak */
function fetchTLEData($category) {
    $url = "https://celestrak.org/NORAD/elements/gp.php?GROUP=$category&FORMAT=tle";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'SatTrack/1.0'
        ]
    ]);
    
    $data = @file_get_contents($url, false, $context);
    
    if ($data === false) {
        return false;
    }
    
    return $data;
}

/* parses tle data into structured format */
function parseTLE($tleData) {
    $lines = explode("\n", trim($tleData));
    $satellites = [];
    
    for ($i = 0; $i < count($lines); $i += 3) {
        if (!isset($lines[$i + 2])) break;
        
        $name = trim($lines[$i]);
        $line1 = trim($lines[$i + 1]);
        $line2 = trim($lines[$i + 2]);
        
        // validate tle format
        if (substr($line1, 0, 1) !== '1' || substr($line2, 0, 1) !== '2') {
            continue;
        }
        
        // extract norad id from line 1
        $noradId = intval(substr($line1, 2, 5));
        
        // extract epoch from line 1 (for last updated time)
        $epochYear = intval(substr($line1, 18, 2));
        $epochDay = floatval(substr($line1, 20, 12));
        
        // convert 2-digit year to 4-digit
        $year = ($epochYear < 57) ? 2000 + $epochYear : 1900 + $epochYear;
        
        // convert day of year to actual date
        $epoch = new DateTime("$year-01-01");
        $epoch->modify('+' . floor($epochDay - 1) . ' days');
        
        $satellites[] = [
            'name' => $name,
            'norad_id' => $noradId,
            'tle_line1' => $line1,
            'tle_line2' => $line2,
            'epoch' => $epoch->format('Y-m-d H:i:s')
        ];
    }
    
    return $satellites;
}

// start seeding
echo "SATELLITE DATABASE SEEDING\n";
echo "fetching satellite data from celestrak... this will take a few minutes\n\n";

$totalSatellites = 0;
$successCount = 0;
$errorCount = 0;

foreach ($categories as $categoryKey => $categoryName) {
    echo "fetching $categoryName satellites...\n";
    
    $tleData = fetchTLEData($categoryKey);
    
    if ($tleData === false) {
        echo "  failed to fetch data\n\n";
        continue;
    }
    
    $satellites = parseTLE($tleData);
    echo "  found " . count($satellites) . " satellites\n";
    
    foreach ($satellites as $sat) {
        $totalSatellites++;
        
        try {
            // map satellite type
            $satType = mapSatelliteType($sat['name']);
            
            // escape data
            $noradId = $conn->real_escape_string($sat['norad_id']);
            $name = $conn->real_escape_string($sat['name']);
            $type = $conn->real_escape_string($satType);
            $line0 = $conn->real_escape_string($sat['name']);
            $line1 = $conn->real_escape_string($sat['tle_line1']);
            $line2 = $conn->real_escape_string($sat['tle_line2']);
            $epoch = $conn->real_escape_string($sat['epoch']);
            
            // insert or update satellite
            $sql = "INSERT INTO satellites (norad_id, name, satellite_type, is_active, last_updated)
                    VALUES ('$noradId', '$name', '$type', 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        name = '$name',
                        satellite_type = '$type',
                        is_active = 1,
                        last_updated = NOW()";
            
            if ($conn->query($sql) === TRUE) {
                $satelliteId = $conn->insert_id;
                
                // if update (not insert), get existing id
                if ($satelliteId == 0) {
                    $result = $conn->query("SELECT id FROM satellites WHERE norad_id = '$noradId'");
                    $row = $result->fetch_assoc();
                    $satelliteId = $row['id'];
                }
                
                // insert or update tle data
                $sql = "INSERT INTO tle_data (satellite_id, tle_line0, tle_line1, tle_line2, epoch, updated_at)
                        VALUES ('$satelliteId', '$line0', '$line1', '$line2', '$epoch', NOW())
                        ON DUPLICATE KEY UPDATE
                            tle_line0 = '$line0',
                            tle_line1 = '$line1',
                            tle_line2 = '$line2',
                            epoch = '$epoch',
                            updated_at = NOW()";
                
                if ($conn->query($sql) === TRUE) {
                    $successCount++;
                } else {
                    $errorCount++;
                    echo "  error updating tle for {$sat['name']}: " . $conn->error . "\n";
                }
            } else {
                $errorCount++;
                echo "  error processing {$sat['name']} (norad {$sat['norad_id']}): " . $conn->error . "\n";
            }
            
        } catch(Exception $e) {
            $errorCount++;
            echo "  exception processing {$sat['name']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "  processed " . count($satellites) . " satellites\n\n";
    
    // small delay to be nice to celestrak servers
    usleep(500000); // 0.5 seconds
}

// summary
echo "SEEDING SUMMARY\n";
echo "total satellites processed: $totalSatellites\n";
echo "successfully inserted/updated: $successCount\n";
echo "errors: $errorCount\n\n";

// display some stats
$result = $conn->query("SELECT satellite_type, COUNT(*) as count FROM satellites GROUP BY satellite_type ORDER BY count DESC");

if ($result) {
    echo "satellites by type:\n";
    echo "-------------------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo sprintf("%-20s: %d\n", $row['satellite_type'], $row['count']);
    }
    echo "\n";
}

$result = $conn->query("SELECT COUNT(*) as total FROM satellites");
if ($result) {
    $row = $result->fetch_assoc();
    echo "total satellites in database: " . $row['total'] . "\n";
}

echo "\nseeding complete!\n";

$conn->close();
?>
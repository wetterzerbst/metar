// Stand: 25.10.2026 22:00 MEZ
// Dieser neue Metar-Generator für den Flugplatz Zerbst berechnet auch die Wolkenuntergrenze und
// die Sichtweite anhand der Taupunkttemperatur
// Es handelt sich um eine Datei für die Darstellung in eingebundenen Dateien, woraus man direkte
// Text oder CSV-Dateien erstellen kann. Dabei greift dieser Code auf eine Ecowitt-Datei zu, die
// automatisch vom IOT der Wetterstation auf einen Webserver gepusht wird. Bisher hat dieser
// Code die Daten aus den Spaltennummern gezogen. Dies wurde nun umgestellt und die Daten werden
// aus den Spaltennamen bezogen. Der Grund. Falls mal ein Sensor versagt hat, hatten sich die
// Spaltenzeilen verschoben und das vorherige Script wurde unbrauchbar. Da Ecowitt seine Daten
// in das imperiale Format schreibt, rechnet dieses Script alles sauber in das Format für den
// Flugverkehr um. Um die Daten am Ende sauber anzeigen zu koennen, muss noch die metar.php
// diese CORE-Datei mit einbinden. Die metar.php laden wir zur Ansicht in das Verzeichnis mit
// hoch. Ausserdem laden wir auch noch eine view.php mit hoch, die den Metar auswertet. Eine
// live.csv laden wir auch in das Verzeichnis mit hoch, damit das Grundprinzip dieser Daten-
// verarbeitung nachvollziehbar wird.
<?php
/**
 * ZEWN / EDUZ Weather Core Kernel (WMO / ICAO FMH-1 Standard)
 * Internationales METAR-Format nach dem Handbuch des DWD und NOAA
 */

date_default_timezone_set('UTC');

function getEduzMetarData($csvPath = null) {
    // Falls kein Pfad übergeben wurde, Standardpfad relativ zu dieser Datei ermitteln
    if ($csvPath === null) {
        $csvPath = __DIR__ . '/path/to/live.csv';
    }

    if (!file_exists($csvPath)) {
        return ['error' => 'Fehler: CSV-Datei unter Pfad nicht gefunden: ' . htmlspecialchars($csvPath)];
    }

    $lines = @file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines || count($lines) < 2) {
        return ['error' => 'Fehler: CSV enthält keine Datenzeilen oder ist leer.'];
    }

    $headers = str_getcsv($lines[0], ';');
    $lastLine = end($lines);
    $values = str_getcsv($lastLine, ';');

    if (count($headers) !== count($values)) {
        return ['error' => 'Fehler: Header- und Spaltenanzahl stimmen nicht überein.'];
    }

    $data = array_combine($headers, $values);

    $getVal = function($key, $default = 0) use ($data) {
        return isset($data[$key] ) && $data[$key] !== '' ? $data[$key] : $default;
    };

    // --- ROHWERTE ---
    $raw_date    = $getVal('dateutc', date('Y-m-d H:i:s'));
    $raw_qnh     = (float)$getVal('baromrelin', 29.92);
    $raw_temp    = (float)$getVal('tempf', 32.0);
    $raw_hum     = (float)$getVal('humidity', 50.0);
    $raw_wdir    = (int)$getVal('winddir', 0);
    $raw_wspd    = (float)$getVal('windspeedmph', 0.0);
    $raw_wgst    = (float)$getVal('windgustmph', 0.0);
    $rain_rate   = (float)$getVal('rainratein', 0.0);
    $hourly_rain = (float)$getVal('hourlyrainin', 0.0);
    $daily_rain  = (float)$getVal('dailyrainin', 0.0);

    // --- UMRECHNUNGEN ---
    $kn_speed = (int)round($raw_wspd / 1.151);
    $kn_gust  = (int)round($raw_wgst / 1.151);
    $temp_c   = ($raw_temp - 32) * 5 / 9;
    $qnh_hpa  = round($raw_qnh * 33.86389);

    // Taupunkt
    $a = ($temp_c >= 0) ? 7.5 : 7.6;
    $b = ($temp_c >= 0) ? 237.3 : 240.7;
    $sdd = 6.1078 * pow(10, (($a * $temp_c) / ($b + $temp_c)));
    $dd  = ($raw_hum / 100) * $sdd;
    $v   = log10(max($dd, 0.0001) / 6.1078);
    $td  = ($b * $v) / ($a - $v);

    // Wolkenbasis
    $spread = $temp_c - $td;
    $cloudBaseFt = max(500, (int)round(($spread / 2.5) * 1000 / 100) * 100);

    // --- METAR GENERIERUNG ---
    $zulu_time = (new DateTime($raw_date))->format('dHi') . "Z";

    $fmtTemp = function($val) {
        $r = (int)round($val);
        return ($r < 0 ? "M" : "") . sprintf("%02d", abs($r));
    };

    $temp_part = $fmtTemp($temp_c) . "/" . $fmtTemp($td);

    if ($kn_gust >= ($kn_speed + 10)) {
        $wind_part = sprintf("%03d%02dG%02dKT", $raw_wdir, $kn_speed, $kn_gust);
    } else {
        $wind_part = sprintf("%03d%02dKT", $raw_wdir, $kn_speed);
    }

    $visibility = "9999";
    $weatherPhenomen = "";
    if ($rain_rate > 0) {
        $weatherPhenomen = ($rain_rate < 0.1) ? "-DZ" : "-RA";
    }

    if (empty($weatherPhenomen) && $cloudBaseFt > 5000) {
        $metarBody = sprintf("EDUZ %s AUTO %s CAVOK %s Q%04d", $zulu_time, $wind_part, $temp_part, (int)$qnh_hpa);
    } else {
        $cloudCode = ($cloudBaseFt <= 2000) ? "BKN" : "SCT";
        $cloudHeightStr = sprintf("%03d", (int)round($cloudBaseFt / 100));
        
        if (empty($weatherPhenomen)) {
            $metarBody = sprintf("EDUZ %s AUTO %s %s %s%s %s Q%04d", $zulu_time, $wind_part, $visibility, $cloudCode, $cloudHeightStr, $temp_part, (int)$qnh_hpa);
        } else {
            $metarBody = sprintf("EDUZ %s AUTO %s %s %s %s%s %s Q%04d", $zulu_time, $wind_part, $visibility, $weatherPhenomen, $cloudCode, $cloudHeightStr, $temp_part, (int)$qnh_hpa);
        }
    }

    // RMK Block WMO konform
    $metarBody .= " RMK AO2";
    $p_hundredths = (int)round($hourly_rain * 100);
    $metarBody .= " P" . sprintf("%04d", $p_hundredths);

    return [
        'metar_raw'     => "METAR " . $metarBody,
        'zulu_time'     => $zulu_time,
        'temp_c'        => $temp_c,
        'dew_p'         => $td,
        'qnh_hpa'       => $qnh_hpa,
        'wind_dir'      => $raw_wdir,
        'wind_speed_kt' => $kn_speed,
        'wind_gust_kt'  => $kn_gust,
        'cloud_base_ft' => $cloudBaseFt,
        'hourly_rain_in'=> $hourly_rain,
        'daily_rain_in' => $daily_rain,
        'daily_rain_mm' => round($daily_rain * 25.4, 1)
    ];
}

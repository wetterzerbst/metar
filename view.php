<?php
/**
 * ZEWN METAR Reader - Mobil Optimiert V8 (WMO-konform inkl. P-Code Parsing)
 */

$sourceUrl = "https://zewn.web.wetter-zerbst.org/eduz/metar.csv/csv.php"; // Zum testen
// Normalerweise nimmt man in diesem Fall die view.php die in diesem Github vorhanden ist.
$metarRaw = @file_get_contents($sourceUrl);

if (!$metarRaw) {
    $metar = "METAR EDUZ 000000Z AUTO 00000KT 9999 CAVOK 00/00 Q0000 RMK AO2 P0000"; 
    $error = "Wetterdaten derzeit nicht erreichbar.";
} else {
    $metar = trim($metarRaw);
}

// METAR-String zerlegen
$parts = explode(' ', $metar);
$icao      = $parts[1] ?? "???";
$zulu_time = $parts[2] ?? "000000Z";
$wind_raw  = $parts[4] ?? "00000KT";

// Variablen für flexible Suche
$temp_raw = "00/00";
$qnh_raw  = "Q1013";
$visibility_text = "9999 m (Sicht gut)";
$cloud_text = "keine Wolken unter 5.000 ft (CAVOK)";
$precip_inches = 0.0;
$precip_code = "P0000";

// Sichtweite ermitteln (meist Position 5, wenn 4-stellige Zahl)
if (isset($parts[5]) && ctype_digit($parts[5])) {
    $vis_num = (int)$parts[5];
    $visibility_text = ($vis_num >= 9999) ? "10 km oder mehr" : $vis_num . " Meter";
}

// Wolken / CAVOK oder Wetterphänomen suchen
for ($i = 5; $i < count($parts); $i++) {
    $p = $parts[$i];
    if ($p === 'CAVOK') {
        $cloud_text = "keine Wolken unter 5.000 ft (CAVOK)";
    } elseif (preg_match('/^(FEW|SCT|BKN|OVC)(\d{3})$/', $p, $cloud_match)) {
        $layer = $cloud_match[1];
        $altitude_ft = (int)$cloud_match[2] * 100;
        $altitude_m  = (int)round($altitude_ft * 0.3048); // Umrechnung Fuß in Meter
        
        $layer_de = [
            'FEW' => 'Leicht bewölkt (1-2 Achtel)',
            'SCT' => 'Aufgelockert (3-4 Achtel)',
            'BKN' => 'Überwiegend bedeckt (5-7 Achtel)',
            'OVC' => 'Vollständig bedeckt (8 Achtel)'
        ][$layer] ?? $layer;
        
        // Formatiert für Piloten: Fuß an erster Stelle, Meter in Klammern
        $cloud_text = sprintf("%s in %s ft Höhe (ca. %s m)", $layer_de, number_format($altitude_ft, 0, ',', '.'), number_format($altitude_m, 0, ',', '.'));
    }
}

// Flexible Suche nach Temperatur, QNH und WMO-konformem Niederschlag (Pxxxx)
foreach ($parts as $key => $p) {
    if (strpos($p, '/') !== false && preg_match('/(M?\d{2})\/(M?\d{2})/', $p)) {
        $temp_raw = $p;
    }
    if (strpos($p, 'Q') === 0 && strlen($p) === 5) {
        $qnh_raw = $p;
    }
    // Suchen nach dem WMO-Standard P-Code im RMK (z.B. P0038)
    if (preg_match('/^P(\d{4})$/', $p, $precip_match)) {
        $precip_code = $p;
        $hundredths = (int)$precip_match[1];
        $precip_inches = $hundredths / 100.0;
    }
}

$day  = substr($zulu_time, 0, 2);
$hour = substr($zulu_time, 2, 2);
$min  = substr($zulu_time, 4, 2);
$metar_time_formatted = "$hour:$min UTC";

// Wind parsen
preg_match('/(\d{3})(\d{2})(G(\d{2}))?KT/', $wind_raw, $wind_matches);
$wind_dir   = $wind_matches[1] ?? "000";
$wind_speed = (int)($wind_matches[2] ?? 0);
$wind_gust  = isset($wind_matches[4]) ? (int)$wind_matches[4] : $wind_speed;

// Temperatur & Taupunkt parsen
$temp_split = explode('/', $temp_raw);
$temp_c = (float)str_replace('M', '-', $temp_split[0] ?? "0");
$dew_p  = (float)str_replace('M', '-', $temp_split[1] ?? "0");
$qnh    = substr($qnh_raw, 1);

if ($wind_speed == 0 && $wind_gust == 0) {
    $wind_text = "Es herrscht Windstille";
} else {
    $wind_text = "Wind aus " . $wind_dir . " Grad mit " . $wind_speed . " Knoten";
    if ($wind_gust > $wind_speed) {
        $wind_text .= " (Böen bis " . $wind_gust . " Knoten)";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>METAR EDUZ</title>
    <link rel="icon" href="windsack.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-color: #1a1a1b;
            --accent-color: #0055aa;
        }

        [data-bs-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f1f5f9;
            --accent-color: #38bdf8;
        }

        body { 
            background-color: var(--bg-color); 
            color: var(--text-color);
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding: 10px;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }

        .metar-card { 
            background-color: var(--card-bg);
            border: none;
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }

        .header-box {
            background: linear-gradient(135deg, var(--accent-color), #003366);
            padding: 20px 15px;
            position: relative;
        }

        .btn-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .raw-code { 
            font-family: 'SFMono-Regular', Consolas, monospace; 
            background: rgba(0,0,0,0.04); 
            padding: 10px; 
            border-radius: 10px; 
            font-size: 0.75rem;
            line-height: 1.3;
            color: var(--text-color);
            opacity: 0.8;
            word-break: break-all;
        }

        [data-bs-theme="dark"] .raw-code { background: rgba(255,255,255,0.06); }

        .data-grid-item {
            padding: 12px 5px;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 15px;
            height: 100%;
        }
        
        [data-bs-theme="dark"] .data-grid-item { border-color: rgba(255,255,255,0.05); }

        .info-box {
            border-left: 4px solid var(--accent-color);
            background: rgba(0,85,170,0.04);
            border-radius: 0 12px 12px 0;
            padding: 12px;
        }
    </style>
</head>
<body data-bs-theme="light">

<div class="container-fluid d-flex justify-content-center align-items-start p-0">
    <div id="refresh-container" class="w-100 d-flex justify-content-center">
        <div class="card metar-card">
            <div class="header-box text-white text-center">
                <button class="btn-toggle" id="themeToggle" title="Modus wechseln">
                    <span id="themeIcon">🌙</span>
                </button>
                <h2 class="h5 mb-1 fw-bold">Flugplatz Zerbst</h2>
                <span class="badge bg-white text-primary rounded-pill px-3">ICAO: <?php echo $icao; ?></span>
            </div>
            
            <div class="card-body p-3 p-sm-4">
                <div class="text-center mb-4">
                    <div class="raw-code mb-2"><?php echo htmlspecialchars($metar); ?></div>
                </div>

                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="data-grid-item">
                            <label class="text-muted small d-block mb-1" style="font-size: 0.7rem;">Wind</label>
                            <div class="fw-bold"><?php echo $wind_dir; ?>° / <?php echo $wind_speed; ?> kt</div>
                            <?php if($wind_gust > $wind_speed): ?>
                                <div class="text-danger fw-bold" style="font-size: 0.65rem;">Böen: <?php echo $wind_gust; ?> kt</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-grid-item">
                            <label class="text-muted small d-block mb-1" style="font-size: 0.7rem;">QNH</label>
                            <div class="fw-bold"><?php echo $qnh; ?> hPa</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-grid-item">
                            <label class="text-muted small d-block mb-1" style="font-size: 0.7rem;">Temperatur</label>
                            <div class="fw-bold text-primary"><?php echo round($temp_c); ?> °C</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-grid-item">
                            <label class="text-muted small d-block mb-1" style="font-size: 0.7rem;">Taupunkt</label>
                            <div class="fw-bold"><?php echo round($dew_p); ?> °C</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 info-box">
                    <h6 class="fw-bold mb-1" style="font-size: 0.85rem;">Wetterzusammenfassung:</h6>
                    <p class="mb-0 small text-secondary" style="line-height: 1.4;">
                        <?php echo $wind_text; ?>. 
                        Sichtweite: <?php echo $visibility_text; ?>. 
                        Bewölkung: <?php echo $cloud_text; ?>. 
                        Temperatur <?php echo round($temp_c); ?>°C (Taupunkt <?php echo round($dew_p); ?>°C). 
                        Luftdruck QNH <?php echo $qnh; ?> hPa.
                        Niederschlag (in der letzten Stunde): <?php echo number_format($precip_inches, 2, '.', ''); ?> in (ca. <?php echo round($precip_inches * 25.4, 1); ?> mm).
                    </p>
                </div>

                <!-- Erklärung für den RMK-Bereich (WMO / FMH-1 konform) -->
                <div class="mt-3 p-3 rounded bg-secondary bg-opacity-10" style="font-size: 0.75rem;">
                    <span class="fw-bold d-block mb-1">Hinweis zum RMK-Block (Remarks):</span>
                    <ul class="mb-0 ps-3 text-secondary" style="line-height: 1.3;">
                        <li><strong>AO2:</strong> Vollautomatische Wetterstation mit Niederschlagserkennung.</li>
                        <li><strong>P<?php echo substr($precip_code, 1); ?>:</strong> Offizieller WMO/FMH-1 Stundenniederschlag (in Hundertstel Inch).</li>
                    </ul>
                </div>

                <div class="text-center mt-4 pt-2 border-top">
                    <div class="text-muted" style="font-size: 0.65rem;">Messzeit:</div>
                    <div class="h5 fw-bold mb-0"><?php echo $metar_time_formatted; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const body = document.body;

    const setTheme = (theme) => {
        body.setAttribute('data-bs-theme', theme);
        themeIcon.innerText = theme === 'light' ? '🌙' : '☀️';
        localStorage.setItem('zewn_theme', theme);
    };

    setTheme(localStorage.getItem('zewn_theme') || 'light');

    themeToggle.addEventListener('click', () => {
        setTheme(body.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light');
    });

    setInterval(() => {
        fetch(window.location.href)
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                const content = newDoc.getElementById('refresh-container').innerHTML;
                document.getElementById('refresh-container').innerHTML = content;
                
                const currentTheme = body.getAttribute('data-bs-theme');
                const newToggle = document.getElementById('themeToggle');
                const newIcon = document.getElementById('themeIcon');
                newIcon.innerText = currentTheme === 'light' ? '🌙' : '☀️';
                
                newToggle.addEventListener('click', () => {
                    const nextTheme = body.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
                    setTheme(nextTheme);
                });
            });
    }, 10000);
</script>

</body>
</html>

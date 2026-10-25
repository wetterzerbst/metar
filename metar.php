// Einbindungsdatei für den metar_core.php
<?php
require_once __DIR__ . '/eduz_core.php';
$data = getEduzMetarData();

if (isset($data['error'])) {
    die($data['error']);
}

header("Content-type: text/plain; charset=utf-8");
echo $data['metar_raw'];

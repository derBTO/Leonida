<?php
/**
 * Leonida – Prüfskript für das Hetzner-Webhosting.
 *
 * Prüft, ob PHP, Erweiterungen, Dateisystem und PostgreSQL alles bieten, was Leonida braucht.
 * Ändert nichts am Server und legt nur kurz eine Testdatei und einen Test-Symlink an.
 *
 * Aufruf per SSH (empfohlen):
 *     LEONIDA_DB_PASSWORD='…' php server-check.php --db-host=… --db-name=… --db-user=…
 *     (ohne --db-* wird die Datenbank übersprungen)
 *
 * Aufruf im Browser (zeigt die PHP-Version, die der Webserver wirklich nutzt):
 *     Datei in den Document Root legen, TOKEN unten ändern, dann
 *     https://leonida.derbto.de/server-check.php?token=<TOKEN> aufrufen.
 *     Danach die Datei wieder löschen.
 *
 * Exit-Code: 0 = alles in Ordnung, 1 = Warnungen, 2 = mindestens ein Problem.
 */

declare(strict_types=1);

const TOKEN = 'bitte-aendern';
const MIN_PHP = '8.3.0';
const REQUIRED_EXTENSIONS = [
    'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'pdo_pgsql',
    'session', 'tokenizer', 'xml', 'zip', 'intl', 'sodium',
];

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    if (TOKEN === 'bitte-aendern' || !hash_equals(TOKEN, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Zugriff verweigert. TOKEN in der Datei setzen und ?token=… anhängen.');
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
}

$results = [];
function report(string $status, string $check, string $detail = ''): void
{
    global $results;
    $results[] = [$status, $check, $detail];
}

// ---------------------------------------------------------------- PHP
report(
    version_compare(PHP_VERSION, MIN_PHP, '>=') ? 'OK' : 'FEHLER',
    'PHP-Version',
    PHP_VERSION . ' (' . PHP_SAPI . ', benötigt ≥ ' . MIN_PHP . ')'
);

foreach (REQUIRED_EXTENSIONS as $ext) {
    report(extension_loaded($ext) ? 'OK' : 'FEHLER', "Erweiterung $ext");
}

$imagick = extension_loaded('imagick');
$gdWebp = extension_loaded('gd') && function_exists('imagewebp');
if ($imagick) {
    $formats = \Imagick::queryFormats();
    $webp = in_array('WEBP', $formats, true);
    report($webp ? 'OK' : 'WARNUNG', 'Imagick', $webp ? 'mit WebP' : 'ohne WebP-Unterstützung');
} else {
    report('INFO', 'Imagick', 'nicht geladen');
}
report($gdWebp ? 'OK' : ($imagick ? 'INFO' : 'FEHLER'), 'GD mit WebP', $gdWebp ? 'vorhanden' : 'nicht vorhanden');

report(
    defined('PASSWORD_ARGON2ID') ? 'OK' : 'WARNUNG',
    'Argon2id für Passwörter',
    defined('PASSWORD_ARGON2ID') ? 'verfügbar' : 'nicht verfügbar, Leonida nutzt dann bcrypt'
);

function toBytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;
    return match ($unit) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}

$limits = [
    'memory_limit' => [256 * 1024 ** 2, '256M'],
    'upload_max_filesize' => [20 * 1024 ** 2, '20M'],
    'post_max_size' => [25 * 1024 ** 2, '25M'],
];
foreach ($limits as $key => [$min, $label]) {
    $value = (string) ini_get($key);
    report(toBytes($value) >= $min ? 'OK' : 'WARNUNG', $key, "$value (empfohlen ≥ $label)");
}
report('INFO', 'max_execution_time', (string) ini_get('max_execution_time') . ' s');

$disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
foreach (['symlink', 'proc_open', 'exec', 'putenv', 'escapeshellarg'] as $fn) {
    $isDisabled = in_array($fn, $disabled, true) || !function_exists($fn);
    $status = $isDisabled ? ($fn === 'symlink' ? 'FEHLER' : 'WARNUNG') : 'OK';
    report($status, "Funktion $fn()", $isDisabled ? 'deaktiviert' : 'verfügbar');
}

// ---------------------------------------------------------------- Dateisystem
$base = __DIR__;
$probe = $base . '/.leonida-check-' . bin2hex(random_bytes(4));
if (@mkdir($probe)) {
    $ok = @file_put_contents("$probe/test.txt", 'ok') === 2;
    report($ok ? 'OK' : 'FEHLER', 'Schreibrechte', $base);
    $link = "$probe/link";
    $linked = function_exists('symlink') && @symlink("$probe/test.txt", $link) && @file_get_contents($link) === 'ok';
    report($linked ? 'OK' : 'FEHLER', 'Symlinks', $linked ? 'funktionieren' : 'nicht möglich (für Releases und Kacheln nötig)');
    @unlink($link);
    @unlink("$probe/test.txt");
    @rmdir($probe);
} else {
    report('FEHLER', 'Schreibrechte', "Kann in $base keinen Ordner anlegen");
}
$free = @disk_free_space($base);
report('INFO', 'Freier Speicher (laut PHP)', $free === false ? 'unbekannt' : sprintf('%.1f GB', $free / 1e9));
report('INFO', 'Dateianzahl-Limit', 'nicht per PHP prüfbar – Karte braucht ca. 56.000 Dateien, siehe Hetzner Console');

// ---------------------------------------------------------------- Werkzeuge (nur CLI)
if ($isCli && function_exists('exec')) {
    foreach (['git', 'composer', 'rsync', 'pg_dump', 'php'] as $tool) {
        $out = [];
        @exec('command -v ' . escapeshellarg($tool) . ' 2>/dev/null', $out, $code);
        report($code === 0 ? 'OK' : ($tool === 'pg_dump' || $tool === 'rsync' ? 'WARNUNG' : 'INFO'),
            "Programm $tool", $code === 0 ? $out[0] : 'nicht gefunden');
    }
}

// ---------------------------------------------------------------- Datenbank (nur CLI)
$options = $isCli ? getopt('', ['db-host:', 'db-port:', 'db-name:', 'db-user:']) : [];
if (!empty($options['db-name'])) {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $options['db-host'] ?? 'localhost',
        $options['db-port'] ?? '5432',
        $options['db-name']
    );
    try {
        $pdo = new PDO($dsn, $options['db-user'] ?? '', (string) getenv('LEONIDA_DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = (string) $pdo->query('SHOW server_version')->fetchColumn();
        report(version_compare($version, '13', '>=') ? 'OK' : 'WARNUNG', 'PostgreSQL', $version);
        $rows = $pdo->query(
            "SELECT name, installed_version FROM pg_available_extensions WHERE name IN ('pg_trgm','citext','unaccent')"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (['pg_trgm', 'citext', 'unaccent'] as $ext) {
            if (!array_key_exists($ext, $rows)) {
                report($ext === 'pg_trgm' ? 'WARNUNG' : 'INFO', "Extension $ext", 'nicht verfügbar');
            } elseif ($rows[$ext] !== null) {
                report('OK', "Extension $ext", "installiert ({$rows[$ext]})");
            } else {
                report('INFO', "Extension $ext", 'verfügbar, noch nicht installiert (Leonida legt sie bei der Migration an)');
            }
        }
        $canCreate = (bool) $pdo->query(
            "SELECT has_database_privilege(current_user, current_database(), 'CREATE')"
        )->fetchColumn();
        report($canCreate ? 'OK' : 'WARNUNG', 'Recht CREATE in der Datenbank', $canCreate ? 'ja' : 'nein');
    } catch (Throwable $e) {
        report('FEHLER', 'PostgreSQL-Verbindung', $e->getMessage());
    }
} else {
    report('INFO', 'PostgreSQL', $isCli ? 'übersprungen (keine --db-name angegeben)' : 'nur per SSH geprüft');
}

// ---------------------------------------------------------------- Ausgabe
if (!function_exists('mb_str_pad')) {
    function mb_str_pad(string $text, int $length): string
    {
        return $text . str_repeat(' ', max(0, $length - mb_strlen($text)));
    }
}
$worst = 0;
$rank = ['OK' => 0, 'INFO' => 0, 'WARNUNG' => 1, 'FEHLER' => 2];
echo "Leonida – Serverprüfung (" . date('Y-m-d H:i') . ")\n";
echo str_repeat('-', 72) . "\n";
foreach ($results as [$status, $check, $detail]) {
    $worst = max($worst, $rank[$status]);
    echo str_pad($status, 9) . mb_str_pad($check, 33) . $detail . "\n";
}
echo str_repeat('-', 72) . "\n";
echo ['Alles in Ordnung.', 'Mit Warnungen – bitte ansehen.', 'Es gibt Probleme – Leonida läuft so nicht.'][$worst] . "\n";
if (!$isCli) {
    echo "\nBitte diese Datei jetzt wieder vom Server löschen.\n";
}
exit($worst);

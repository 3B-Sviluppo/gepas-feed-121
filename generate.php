<?php
$compartoId = 121;
$pageSize   = 50;
$prossimi30 = 'false';
$selfUrl    = 'https://3B-Sviluppo.github.io/gepas-feed-121/scioperi.xml';

$apiUrl = "https://gepas-api.perlapa.gov.it/api/Public/Scioperi/Pubblicati"
        . "?pageNumber=1&pageSize={$pageSize}"
        . "&ScioperoDeiProssimi30Giorni={$prossimi30}"
        . "&CompartoId={$compartoId}"
        . "&OrderBy=DataInizioSciopero&Ascending=true";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Errore API: HTTP $httpCode\n";
    exit(1);
}

$data = json_decode($response, true);
if (empty($data)) {
    echo "Errore: JSON vuoto o non valido\n";
    exit(1);
}

function msToDate(string $ms, string $format = DATE_RSS): string {
    return date($format, (int)((float)$ms / 1000));
}

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo '  <channel>' . "\n";
echo '    <title>Scioperi GEPAS – Istruzione e Ricerca</title>' . "\n";
echo '    <link>https://crusc-gepas.perlapa.gov.it/home</link>' . "\n";
echo '    <description>Feed RSS non ufficiale – Cruscotto Scioperi GEPAS</description>' . "\n";
echo '    <language>it-it</language>' . "\n";
echo '    <pubDate>' . date(DATE_RSS) . '</pubDate>' . "\n";
echo '    <lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
echo '    <atom:link href="' . htmlspecialchars($selfUrl) . '" rel="self" type="application/rss+xml"/>' . "\n\n";

foreach ($data as $sciopero) {
    $titolo   = htmlspecialchars($sciopero['denominazioneSciopero'] ?? 'Sciopero');
    $link     = htmlspecialchars('https://crusc-gepas.perlapa.gov.it/detail/' . ($sciopero['id'] ?? ''));
    $dataMs   = $sciopero['dateSciopero'][0]['data'] ?? null;
    $pubDate  = $dataMs ? msToDate((string)$dataMs) : date(DATE_RSS);
    $dataLegg = $dataMs ? msToDate((string)$dataMs, 'd/m/Y') : 'N/D';
    $scadMs   = $sciopero['dataScadenzaAdesione'] ?? null;
    $scadStr  = $scadMs ? msToDate((string)$scadMs, 'd/m/Y') : 'N/D';

    $sigleInd  = $sciopero['dateSciopero'][0]['sigleSindacaliCheIndicono']    ?? [];
    $siglePart = $sciopero['dateSciopero'][0]['sigleSindacaliChePartecipano'] ?? [];
    $sigleStr  = implode(', ', array_unique(array_merge($sigleInd, $siglePart))) ?: 'N/D';

    $soggetti   = htmlspecialchars($sciopero['dateSciopero'][0]['soggettiCoinvolti'] ?? 'N/D');
    $compartiArr = array_column($sciopero['dateSciopero'][0]['compartiCoinvolti'] ?? [], 'descrizioneComparto');
    $compartiStr = htmlspecialchars(implode(', ', $compartiArr) ?: 'N/D');
    $note        = htmlspecialchars(trim($sciopero['interventiCommissioneGaranzia'] ?? ''));

    $desc  = "📅 Data sciopero: $dataLegg\n";
    $desc .= "⏰ Scadenza adesione: $scadStr\n";
    $desc .= "🏛 Comparti: $compartiStr\n";
    $desc .= "👥 Soggetti: $soggetti\n";
    $desc .= "✊ Sigle: $sigleStr";
    if ($note) $desc .= "\n📋 Note CGS: $note";

    echo "    <item>\n";
    echo "      <title>$titolo</title>\n";
    echo "      <link>$link</link>\n";
    echo "      <description><![CDATA[$desc]]></description>\n";
    echo "      <pubDate>$pubDate</pubDate>\n";
    echo "      <guid isPermaLink=\"true\">$link</guid>\n";
    echo "    </item>\n\n";
}

echo '  </channel>' . "\n";
echo '</rss>';

$xml = ob_get_clean();
file_put_contents(__DIR__ . '/scioperi.xml', $xml);
echo "✅ scioperi.xml generato: " . count($data) . " scioperi — " . date('d/m/Y H:i') . "\n";

<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/../includes/deelnemers_mail.inc.php';
require_once __DIR__ . '/../includes/mail_tracking.php';
require_once __DIR__ . '/../includes/csrf.php';

final class DeelnemersMailTest extends TestCase
{
    public function testHalfjarenGebruikenVolgendJaarEnExclusieveEinddatum(): void
    {
        $halfjaren = mailHalfjaren(new DateTimeImmutable('2026-12-31'));
        self::assertSame(['plaatsing_2027_1', 'plaatsing_2027_2'], array_keys($halfjaren));
        self::assertSame('2027-01-01', $halfjaren['plaatsing_2027_1']['start']);
        self::assertSame('2027-07-01', $halfjaren['plaatsing_2027_1']['einde']);
        self::assertSame('2027-07-01', $halfjaren['plaatsing_2027_2']['start']);
        self::assertSame('2028-01-01', $halfjaren['plaatsing_2027_2']['einde']);
        self::assertArrayHasKey('plaatsing_2028_1', mailHalfjaren(new DateTimeImmutable('2027-01-01')));
    }

    public function testOntvangersZijnUniekEnAlleenJaOfMisschienBinnenHalfjaar(): void
    {
        $pdo = $this->database();
        $halfjaren = mailHalfjaren(new DateTimeImmutable('2026-10-26'));
        $eerste = laadHalfjaarMailDeelnemers($pdo, $halfjaren['plaatsing_2027_1']);
        self::assertSame([1, 2], array_column($eerste, 'id'));
        self::assertSame(['2027-01-01', '2027-06-30'], array_column($eerste[0]['plaatsingen'], 'datum'));
        self::assertSame([2, 3], array_column($eerste[0]['plaatsingen'], 'activiteit_id'));
        self::assertSame('viool', $eerste[0]['instrument']);
        self::assertCount(1, $eerste[1]['plaatsingen']);
        self::assertSame([0], array_column($eerste[1]['plaatsingen'], 'toegelaten'));

        $tweede = laadHalfjaarMailDeelnemers($pdo, $halfjaren['plaatsing_2027_2']);
        self::assertSame([1, 3], array_column($tweede, 'id'));
        self::assertSame(['2027-07-01'], array_column($tweede[0]['plaatsingen'], 'datum'));
        self::assertSame(['2027-12-31'], array_column($tweede[1]['plaatsingen'], 'datum'));
    }

    public function testHalfjaarMailregistreertDeOpgenomenPlaatsingen(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE mail_tracking (token TEXT PRIMARY KEY, mailtype TEXT);
             CREATE TABLE mail_tracking_plaatsingen (
                 token TEXT, activiteit_id INTEGER, toegelaten INTEGER,
                 PRIMARY KEY (token, activiteit_id)
             );
             INSERT INTO mail_tracking VALUES ("token", NULL);'
        );

        markeerHalfjaarMailVerzonden($pdo, 'token', [
            ['activiteit_id' => 12, 'toegelaten' => 1],
            ['activiteit_id' => 13, 'toegelaten' => 0],
        ]);

        self::assertSame(
            'halfjaar_plaatsingen',
            $pdo->query('SELECT mailtype FROM mail_tracking WHERE token = "token"')->fetchColumn()
        );
        self::assertSame(
            [['activiteit_id' => 12, 'toegelaten' => 1], ['activiteit_id' => 13, 'toegelaten' => 0]],
            $pdo->query('SELECT activiteit_id, toegelaten FROM mail_tracking_plaatsingen ORDER BY activiteit_id')->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testPlaatsingGeeftGroenRoodPartijEnVeiligHtml(): void
    {
        $deelnemer = laadHalfjaarMailDeelnemers($this->database(), [
            'start' => '2027-01-01', 'einde' => '2027-07-01',
        ])[0];
        $activiteit = ['datum' => '2027-01-01', 'plaats' => 'Stadsklooster', 'omschrijving' => 'Mozart'];
        $html = vulMailTemplate('<p>Beste {{voornaam}}</p>{{plaatsing}}', $deelnemer, $activiteit);
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('color:#15803d;', $html);
        self::assertStringContainsString('color:#b91c1c;', $html);
        self::assertStringContainsString('Geplaatst als viool 1', $html);
        self::assertStringContainsString('Afgewezen', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('{{plaatsing}}', $html);
        $tekst = mailHtmlNaarTekst($html);
        self::assertStringContainsString("Geplaatst als viool 1 | \n30-06-2027", $tekst);
        self::assertStringContainsString('Afgewezen', $tekst);
        $onderwerp = vulMailTemplate('{{plaatsing}}', $deelnemer, $activiteit, false);
        self::assertStringNotContainsString('<table', $onderwerp);
        self::assertStringContainsString('Geplaatst als viool 1', $onderwerp);
    }

    public function testBestaandeCodesEnGeneriekeMailBlijvenWerken(): void
    {
        $activiteit = ['datum' => '2027-01-24', 'plaats' => 'Marnixzaal', 'omschrijving' => 'Mozart & Haydn'];
        $deelnemer = ['voornaam' => 'Ada & Bob', 'instrument' => 'viool', 'partij' => '2', 'email' => 'ada+test@example.org'];
        self::assertSame(
            'Ada &amp; Bob viool 2 24 januari de Marnixzaal aan het Domplein Mozart &amp; Haydn',
            vulMailTemplate('{{voornaam}} {{instrument_partij}} {{datum}} {{plaats}} {{omschrijving}}', $deelnemer, $activiteit)
        );
        self::assertSame('1 januari', nederlandseDatumZonderJaar('2027-01-01'));
        self::assertSame('31 december', nederlandseDatumZonderJaar('2027-12-31'));
        self::assertSame('Beste ... ', vulMailTemplateGeneriek('Beste {{voornaam}} {{plaatsing}}'));
        self::assertSame('', plaatsingMailOverzicht([]));
        self::assertSame("Een\nTwee", mailHtmlNaarTekst('<p>Een</p><p>Twee</p>'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPaginaToontKnoppenHalfjaarTemplate(): void
    {
        $halfjaren = mailHalfjaren(new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')));
        $sleutel = array_key_first($halfjaren);
        $pdo = $this->database();
        // Houd de paginatest geldig wanneer het kalenderjaar verandert.
        $jaar = substr($halfjaren[$sleutel]['start'], 0, 4);
        $pdo->exec("UPDATE activiteiten SET datum = REPLACE(datum, '2027', '$jaar')");
        $html = $this->renderPagina($pdo, $sleutel);
        foreach ($halfjaren as $halfjaar) {
            self::assertStringContainsString($halfjaar['label'], $html);
        }
        self::assertStringContainsString('2 deelnemers:', $html);
        self::assertStringContainsString('Hieronder vind je je plaatsingen', $html);
        self::assertStringContainsString('name="doelgroep" value="' . $sleutel . '"', $html);
        self::assertStringContainsString('Testmail naar Dirkjan', $html);
        self::assertStringContainsString('{{plaatsing}}', $html);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPaginaToontLeegHalfjaarZonderMailformulier(): void
    {
        $halfjaren = mailHalfjaren(new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')));
        $pdo = $this->database();
        $pdo->exec("UPDATE activiteiten SET datum = '2000-01-01'");
        $leeg = $this->renderPagina($pdo, array_key_first($halfjaren));
        self::assertStringContainsString('Er zijn nog geen activiteiten in dit halfjaar.', $leeg);
        self::assertStringNotContainsString('id="mail-formulier"', $leeg);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPaginaSelecteertAlleenDeelnemersZonderOpgaveVoorVolgendJaar(): void
    {
        $jaar = (int) (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y') + 1;
        $pdo = $this->database();
        $vorigJaar = $jaar - 1;
        $daaropvolgendJaar = $jaar + 1;
        $pdo->exec("UPDATE activiteiten SET datum =
            CASE SUBSTR(datum, 1, 4)
                WHEN '2026' THEN '$vorigJaar' || SUBSTR(datum, 5)
                WHEN '2027' THEN '$jaar' || SUBSTR(datum, 5)
                WHEN '2028' THEN '$daaropvolgendJaar' || SUBSTR(datum, 5)
            END");
        $pdo->exec("INSERT INTO deelnemers VALUES
                (4, 'Dee', 'D', 'dee@example.org'),
                (5, 'Eef', 'E', 'eef@example.org'),
                (6, 'Fie', 'F', 'fie@example.org'),
                (7, 'Gijs', 'G', 'gijs@example.org'),
                (8, 'Henk', 'H', 'henk@example.org');
            INSERT INTO deelnemer_instrumenten VALUES (4, 1), (4, 2);
            INSERT INTO activiteit_deelnemers VALUES
                (5, 1, 'ja', 0, NULL, NULL),
                (5, 6, 'nee', 0, NULL, NULL),
                (6, 2, '', 0, NULL, NULL),
                (6, 3, NULL, 0, NULL, NULL),
                (7, 5, 'misschien', 0, NULL, NULL),
                (8, 2, 'nee', 0, NULL, NULL)");

        $html = $this->renderPagina($pdo, 'beschikbaarheid_onbekend');
        self::assertStringContainsString('3 deelnemers: Beschikbaarheid voor alle activiteiten in ' . $jaar . ' onbekend', $html);
        foreach (['Dee', 'Eef', 'Fie'] as $naam) {
            self::assertStringContainsString($naam, $html);
        }
        foreach (['Ada', 'Bob', 'Cor', 'Gijs', 'Henk'] as $naam) {
            self::assertStringNotContainsString($naam, $html);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOnbekendeBeschikbaarheidIsLeegZonderActiviteitenVolgendJaar(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE activiteiten SET datum = '2000-01-01'");
        $html = $this->renderPagina($pdo, 'beschikbaarheid_onbekend');
        self::assertStringContainsString('0 deelnemers: Beschikbaarheid voor alle activiteiten', $html);
        self::assertStringContainsString('is deze doelgroep leeg', $html);
    }

    private function renderPagina(PDO $pdo, string $doelgroep): string
    {
        $bron = file_get_contents(__DIR__ . '/../onderhoud/deelnemers_mailen.php');
        $bron = preg_replace('/^require_once .*;\r?$/m', '', $bron);
        $bron = str_replace('zorgVoorMailTrackingTabel($pdo);', '', $bron);
        $bron = str_replace("GROUP_CONCAT(DISTINCT i.naam ORDER BY i.naam SEPARATOR ', ')", "GROUP_CONCAT(DISTINCT i.naam)", $bron);
        $oudeGet = $_GET;
        $oudePost = $_POST;
        $oudeSession = $_SESSION ?? [];
        $_GET = ['doelgroep' => $doelgroep];
        $_POST = [];
        $_SESSION = [];
        ob_start();
        try {
            eval('?>' . $bron);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = $oudeGet;
            $_POST = $oudePost;
            $_SESSION = $oudeSession;
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE deelnemers (id INTEGER, voornaam TEXT, achternaam TEXT, email TEXT);
            CREATE TABLE activiteiten (id INTEGER, datum TEXT, plaats TEXT, omschrijving TEXT);
            CREATE TABLE instrumenten (id INTEGER, naam TEXT);
            CREATE TABLE deelnemer_instrumenten (deelnemer_id INTEGER, instrument_id INTEGER);
            CREATE TABLE activiteit_deelnemers (deelnemer_id INTEGER, activiteit_id INTEGER, status TEXT, toegelaten INTEGER, instrument_id INTEGER, partij TEXT);
            INSERT INTO deelnemers VALUES (1, "Ada", "A", "ada@example.org"), (2, "Bob", "B", "bob@example.org"), (3, "Cor", "C", "cor@example.org");
            INSERT INTO instrumenten VALUES (1, "viool"), (2, "cello");
            INSERT INTO activiteiten VALUES
                (1, "2026-12-31", "Stadsklooster", "Buiten periode"),
                (2, "2027-01-01", "Stadsklooster", "<script>"),
                (3, "2027-06-30", "Marnixzaal", "Haydn"),
                (4, "2027-07-01", "Stadsklooster", "Mozart"),
                (5, "2027-12-31", "Stadsklooster", "Mozart"),
                (6, "2028-01-01", "Stadsklooster", "Buiten periode");
            INSERT INTO activiteit_deelnemers VALUES
                (1, 1, "ja", 1, 1, "1"),
                (1, 2, "ja", 1, 1, "1"),
                (1, 3, "misschien", 0, 1, "2"),
                (1, 4, "ja", 1, 1, "2"),
                (1, 6, "ja", 1, 1, "1"),
                (2, 2, "misschien", 0, NULL, NULL),
                (2, 3, "nee", 0, NULL, NULL),
                (3, 2, "nee", 0, NULL, NULL),
                (3, 5, "ja", 0, NULL, NULL)');
        return $pdo;
    }
}

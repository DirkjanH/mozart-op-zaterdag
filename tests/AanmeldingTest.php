<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/aanmelding.inc.php';

final class AanmeldingTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTurnstileConfiguratieKanUitServeromgevingWordenGelezen(): void
    {
        $naam = 'MOZART_TEST_TURNSTILE_CONFIG';
        $oudeWaarde = getenv($naam);
        putenv($naam . '=testwaarde');
        try {
            self::assertSame('testwaarde', aanmeldTurnstileConfiguratie($naam));
        } finally {
            putenv($oudeWaarde === false ? $naam : $naam . '=' . $oudeWaarde);
        }
    }

    public function testTurnstileVereistSuccesEnDeVerwachteHostnaam(): void
    {
        self::assertTrue(isAanmeldTurnstileResultaatGeldig([
            'success' => true,
            'hostname' => 'mozartopzaterdag.nl',
        ], 'mozartopzaterdag.nl'));
        self::assertFalse(isAanmeldTurnstileResultaatGeldig([
            'success' => false,
            'hostname' => 'mozartopzaterdag.nl',
        ], 'mozartopzaterdag.nl'));
        self::assertFalse(isAanmeldTurnstileResultaatGeldig([
            'success' => true,
            'hostname' => 'anderedomein.nl',
        ], 'mozartopzaterdag.nl'));
    }

    public function testTurnstileVerwerptOntbrekendeTokensEnConfiguratie(): void
    {
        self::assertFalse(controleerAanmeldTurnstile('', 'secret', 'mozartopzaterdag.nl'));
        self::assertFalse(controleerAanmeldTurnstile(str_repeat('x', 2049), 'secret', 'mozartopzaterdag.nl'));
        $this->expectException(RuntimeException::class);
        controleerAanmeldTurnstile('token', '', 'mozartopzaterdag.nl');
    }

    public function testFormulierwaardenWordenHersteldEnAlleenBekendeKeuzesWordenOvergenomen(): void
    {
        $hersteld = herstelAanmeldFormulier(
            ['voornaam' => 'Oude naam', 'op_de_hoogte_houden' => 1],
            [
                'voornaam' => 'Nieuwe naam',
                'achternaam' => 'Speler',
                'email' => 'speler@example.org',
                'op_de_hoogte_houden' => '0',
                'instrumenten' => ['2', '999', 'onjuist'],
                'status_10' => 'misschien',
                'status_11' => 'ongeldig',
            ],
            [2],
            [10, 11]
        );

        self::assertSame('Nieuwe naam', $hersteld['gegevens']['voornaam']);
        self::assertSame('Speler', $hersteld['gegevens']['achternaam']);
        self::assertSame(0, $hersteld['gegevens']['op_de_hoogte_houden']);
        self::assertSame([2], $hersteld['instrumenten']);
        self::assertSame([10 => 'misschien'], $hersteld['statussen']);
    }

    public function testNieuweInschrijvingVoegtDeelnemerEnActiviteitenToe(): void
    {
        $pdo = $this->database();

        $resultaat = verwerkAanmelding(
            $pdo,
            [
                'voornaam' => 'Ada',
                'achternaam' => 'Speler',
                'email' => 'ada@example.org',
                'telefoon' => null,
                'postcode' => null,
                'plaats' => null,
                'muzikale_ervaring' => null,
                'opmerkingen' => null,
                'op_de_hoogte_houden' => 1,
            ],
            [2],
            [1 => 'ja']
        );

        self::assertSame(1, $resultaat['deelnemer_id']);
        self::assertSame([], $resultaat['waarschuwingen']);
        self::assertSame('ja', $pdo->query('SELECT status FROM activiteit_deelnemers WHERE deelnemer_id = 1 AND activiteit_id = 1')->fetchColumn());
        self::assertSame(2, (int) $pdo->query('SELECT instrument_id FROM deelnemer_instrumenten WHERE deelnemer_id = 1')->fetchColumn());
    }

    public function testBestaandeEendubbeleDeelnemersWordenSamengevoegdMetNieuweGegevens(): void
    {
        $pdo = $this->database();
        $pdo->exec(
            "INSERT INTO deelnemers VALUES
                (1, 'Oud', 'Naam', 'speler@example.org', 'oud telefoon', 'oud postcode', 'Oud', 'Oud', 'Oud', 0, '2026-01-01'),
                (2, 'Andere', 'Naam', 'SPELER@example.org', 'ander telefoon', 'ander postcode', 'Ander', 'Ander', 'Ander', 0, '2026-01-02');
            INSERT INTO deelnemer_instrumenten VALUES (1, 1), (2, 2);
            INSERT INTO activiteit_deelnemers
                (activiteit_id, deelnemer_id, status, toegelaten, instrument_id, partij, uitnodiging_verstuurd_op)
            VALUES
                (1, 1, 'ja', 1, 1, '1', NULL),
                (2, 2, 'ja', 1, 2, '2', '2026-01-01'),
                (3, 1, 'ja', 0, 1, '1', NULL),
                (4, 1, 'misschien', 0, 1, '1', NULL),
                (4, 2, 'ja', 1, 2, '2', NULL);
            INSERT INTO deelnemer_wijzigingen VALUES (2, '2026-01-02');
            INSERT INTO mail_tracking VALUES ('token', 2, 2, 'speler@example.org', NULL, NULL);"
        );

        $resultaat = verwerkAanmelding(
            $pdo,
            [
                'voornaam' => 'Nieuw',
                'achternaam' => 'Gegeven',
                'email' => 'Speler@example.org',
                'telefoon' => 'nieuw telefoon',
                'postcode' => 'nieuw postcode',
                'plaats' => 'Utrecht',
                'muzikale_ervaring' => 'nieuw',
                'opmerkingen' => 'nieuw',
                'op_de_hoogte_houden' => 1,
            ],
            [3],
            [1 => 'ja', 2 => 'misschien', 3 => 'nee', 4 => 'nee']
        );

        self::assertSame(1, $resultaat['deelnemer_id']);
        self::assertCount(2, $resultaat['waarschuwingen']);
        self::assertSame('misschien', $resultaat['waarschuwingen'][0]['nieuwe_status']);
        self::assertSame('nee', $resultaat['waarschuwingen'][1]['nieuwe_status']);
        self::assertSame(
            ['Nieuw', 'Gegeven', 'Speler@example.org', 'nieuw telefoon', 'nieuw postcode', 'Utrecht', 'nieuw', 'nieuw', 1],
            $pdo->query('SELECT voornaam, achternaam, email, telefoon, postcode, plaats, muzikale_ervaring, opmerkingen, op_de_hoogte_houden FROM deelnemers WHERE id = 1')->fetch(PDO::FETCH_NUM)
        );
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM deelnemers')->fetchColumn());
        self::assertSame([3], array_map('intval', $pdo->query('SELECT instrument_id FROM deelnemer_instrumenten WHERE deelnemer_id = 1')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame('misschien', $pdo->query('SELECT status FROM activiteit_deelnemers WHERE activiteit_id = 2 AND deelnemer_id = 1')->fetchColumn());
        self::assertSame('2026-01-01', $pdo->query('SELECT uitnodiging_verstuurd_op FROM activiteit_deelnemers WHERE activiteit_id = 2 AND deelnemer_id = 1')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT deelnemer_id FROM mail_tracking WHERE token = "token"')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM deelnemer_wijzigingen WHERE deelnemer_id = 1')->fetchColumn());
    }

    public function testWaarschuwtNietVoorNietIngedeeldeJaOfEenBestaandeMisschien(): void
    {
        $pdo = $this->database();
        $pdo->exec(
            "INSERT INTO deelnemers VALUES
                (1, 'Ada', 'Speler', 'ada@example.org', NULL, NULL, NULL, NULL, NULL, 1, '2026-01-01');
            INSERT INTO activiteit_deelnemers
                (activiteit_id, deelnemer_id, status, toegelaten, instrument_id, partij, uitnodiging_verstuurd_op)
            VALUES
                (1, 1, 'ja', 0, NULL, NULL, NULL),
                (2, 1, 'misschien', 1, NULL, NULL, NULL);"
        );

        $resultaat = verwerkAanmelding(
            $pdo,
            [
                'voornaam' => 'Ada',
                'achternaam' => 'Speler',
                'email' => 'ada@example.org',
                'telefoon' => null,
                'postcode' => null,
                'plaats' => null,
                'muzikale_ervaring' => null,
                'opmerkingen' => null,
                'op_de_hoogte_houden' => 1,
            ],
            [],
            [1 => 'nee', 2 => 'nee']
        );

        self::assertSame([], $resultaat['waarschuwingen']);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE deelnemers (
                id INTEGER PRIMARY KEY AUTOINCREMENT, voornaam TEXT, achternaam TEXT, email TEXT,
                telefoon TEXT, postcode TEXT, plaats TEXT, muzikale_ervaring TEXT, opmerkingen TEXT,
                op_de_hoogte_houden INTEGER, laatst_gewijzigd_op TEXT
            );
            CREATE TABLE deelnemer_instrumenten (deelnemer_id INTEGER, instrument_id INTEGER);
            CREATE TABLE activiteit_deelnemers (
                activiteit_id INTEGER, deelnemer_id INTEGER, status TEXT, toegelaten INTEGER,
                instrument_id INTEGER, partij TEXT, uitnodiging_verstuurd_op TEXT,
                PRIMARY KEY (activiteit_id, deelnemer_id)
            );
            CREATE TABLE activiteiten (id INTEGER PRIMARY KEY, datum TEXT, plaats TEXT, omschrijving TEXT);
            CREATE TABLE deelnemer_wijzigingen (deelnemer_id INTEGER PRIMARY KEY, gemarkeerd_op TEXT);
            CREATE TABLE mail_tracking (
                token TEXT PRIMARY KEY, activiteit_id INTEGER, deelnemer_id INTEGER, email TEXT,
                verzonden_op TEXT, geopend_op TEXT
            );
            INSERT INTO activiteiten VALUES
                (1, "2027-01-01", "Utrecht", "Activiteit een"),
                (2, "2027-02-01", "Utrecht", "Activiteit twee"),
                (3, "2027-03-01", "Utrecht", "Activiteit drie"),
                (4, "2027-04-01", "Utrecht", "Activiteit vier");'
        );
        return $pdo;
    }
}

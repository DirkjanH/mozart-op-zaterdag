<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/bezetting.inc.php';

final class GenereerWebpaginasTest extends TestCase
{
    public function testActiviteitenkeuzeToontAlleenVandaagEnLater(): void
    {
        $bron = file_get_contents(__DIR__ . '/../onderhoud/genereer_webpaginas.php');
        self::assertNotFalse($bron);
        self::assertSame(1, preg_match("/'([^']*SELECT id, datum, plaats, omschrijving FROM activiteiten[^']*)'/", $bron, $overeenkomst));
        $pdo = new PDO('sqlite::memory:');
        $pdo->sqliteCreateFunction('CURDATE', static fn (): string => '2026-10-21', 0);
        $pdo->exec('CREATE TABLE activiteiten (id INTEGER, datum TEXT, plaats TEXT, omschrijving TEXT)');
        $stmt = $pdo->prepare('INSERT INTO activiteiten (id, datum) VALUES (?, ?)');
        foreach ([[1, '2026-09-26'], [2, '2026-10-21'], [3, '2026-10-24'], [4, '2026-11-21']] as $activiteit) {
            $stmt->execute($activiteit);
        }

        self::assertSame(
            ['2026-10-21', '2026-10-24', '2026-11-21'],
            array_column($pdo->query($overeenkomst[1])->fetchAll(PDO::FETCH_ASSOC), 'datum')
        );
    }

    public function testOntbrekendeStrijkersWordenAlsRodeVacaturesGetoond(): void
    {
        $html = $this->renderDeelnemers('0000-0000-21111', [
            ['voornaam' => 'Ada', 'achternaam' => 'Speler', 'instrument' => 'viool', 'partij' => '1 concertmeester'],
        ]);

        self::assertSame(5, substr_count($html, '<em class="w3-text-red">Vacature</em>'));
        foreach (['viool 1', 'viool 2', 'altviool', 'cello', 'contrabas'] as $stemgroep) {
            self::assertStringContainsString('<td>' . $stemgroep . '</td>', $html);
        }
        self::assertStringContainsString('Er zijn 1 toegelaten deelnemers.', $html);
        self::assertStringContainsString('<td>Ada</td>', $html);
    }

    public function testVoldoendeStrijkersLeverenGeenVacaturesOp(): void
    {
        $html = $this->renderDeelnemers('0000-0000-10000', [
            ['voornaam' => 'Ada', 'achternaam' => 'Speler', 'instrument' => 'viool', 'partij' => '1'],
            ['voornaam' => 'Bob', 'achternaam' => 'Speler', 'instrument' => 'viool', 'partij' => '1'],
        ]);

        self::assertStringNotContainsString('Vacature', $html);
        self::assertStringContainsString('Er zijn 2 toegelaten deelnemers.', $html);
    }

    public function testBlazersEnPaukenKrijgenVacaturesZonderStrijkersaantallen(): void
    {
        $html = $this->renderDeelnemers('0200-0000-timp-str', [
            ['voornaam' => 'Ada', 'achternaam' => 'Speler', 'instrument' => 'hobo', 'partij' => 'hobo 2'],
        ]);

        self::assertSame(2, substr_count($html, '<em class="w3-text-red">Vacature</em>'));
        self::assertStringContainsString('<td>hobo 1</td>', $html);
        self::assertStringContainsString('<td>pauken</td>', $html);
        self::assertStringNotContainsString('<td>viool 1</td>', $html);
    }

    public function testLegeBezettingZonderDeelnemers(): void
    {
        $html = $this->renderDeelnemers('', []);

        self::assertStringNotContainsString('Vacature', $html);
        self::assertStringContainsString('Er zijn nog geen deelnemers toegelaten.', $html);
    }

    public function testVijfEersteViolenBijZesGewenstGevenEenVacature(): void
    {
        $deelnemers = array_fill(0, 5, [
            'voornaam' => 'Ada', 'achternaam' => 'Speler', 'instrument' => 'viool', 'partij' => '1',
        ]);

        $html = $this->renderDeelnemers('0000-0000-60000', $deelnemers);

        self::assertSame(1, substr_count($html, '<em class="w3-text-red">Vacature</em>'));
        self::assertStringContainsString('<td>viool 1</td>', $html);
        self::assertStringContainsString('Er zijn 5 toegelaten deelnemers.', $html);
        self::assertStringNotContainsString('Vacature', $this->renderDeelnemers('0000-0000-50000', $deelnemers));
    }

    public function testOngeldigeActueleBezettingGeeftEenFout(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('De gewenste bezetting heeft niet het formaat');

        $this->renderDeelnemers('ongeldig', []);
    }

    private function renderDeelnemers(string $notatie, array $deelnemers): string
    {
        $bron = file_get_contents(__DIR__ . '/../onderhoud/genereer_webpaginas.php');
        self::assertNotFalse($bron);
        self::assertSame(1, preg_match("/<<<'PHP'\r?\n(.*?)\r?\nPHP/s", $bron, $overeenkomst));
        $template = preg_replace('/^\h*require_once [^\r\n]+;\r?$/m', '', $overeenkomst[1]);
        self::assertNotNull($template);
        $template = str_replace('__ACTIVITEIT_ID__', '42', $template);
        $pdo = new class ($deelnemers, $notatie) {
            public function __construct(private array $deelnemers, private string $notatie)
            {
            }

            public function prepare(string $query): object
            {
                if ($query === 'SELECT gewenste_bezetting FROM activiteiten WHERE id = ?') {
                    return new class ($this->notatie) {
                        public function __construct(private string $notatie)
                        {
                        }

                        public function execute(array $parameters): void
                        {
                            TestCase::assertSame([42], $parameters);
                        }

                        public function fetchColumn(): string
                        {
                            return $this->notatie;
                        }
                    };
                }
                TestCase::assertStringContainsString('FROM activiteit_deelnemers ad', $query);
                return new class ($this->deelnemers) {
                    public function __construct(private array $deelnemers)
                    {
                    }

                    public function execute(array $parameters): void
                    {
                        TestCase::assertSame([42], $parameters);
                    }

                    public function fetchAll(int $mode): array
                    {
                        return $this->deelnemers;
                    }
                };
            }
        };

        ob_start();
        try {
            eval('?>' . $template);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

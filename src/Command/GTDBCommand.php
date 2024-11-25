<?php

namespace App\Command;

use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:get-gt-db-data',
)]
class GTDBCommand extends Command
{
    private const URL = 'https://ddm999.github.io/gt7info/data.json';

    public function __construct(
        private readonly HttpClientInterface $client,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $io = new SymfonyStyle($input, $output);

        $response = $this->client->request('GET', self::URL);

        [
            'used' => ['cars' => $usedCars],
            'legend' => ['cars' => $legendCars]
        ] = $response->toArray();

        /** @var array< $carClass > $usedCars */
        $usedCars = array_map(
            $this->getCarClass(...),
            $usedCars
        );

        /** @var array< $carClass > $legendCars */
        $legendCars = array_map(
            $this->getCarClass(...),
            $legendCars
        );

        usort($usedCars, $this->sortCars(...));
        usort($legendCars, $this->sortCars(...));

        $legendTable = $io->createTable();
        $legendTable->setHeaders(['Make', 'Model', 'Credits', 'Est Days', 'Max Est Days']);

        foreach ($legendCars as $car) {
            $legendTable->addRow([
                $car->manufacturer,
                $car->model,
                $car->hrCredits(),
                $car->hrEstimateDays(),
                $car->hrMaxEstimateDays(),
            ]);
        }

        $legendTable->render();

        return Command::SUCCESS;
    }

    private function getCarClass(
        array $arr,
    ): object
    {
        $class =  new class extends stdClass {
            public function __construct(
                public string $manufacturer,
                public string $model,
                public int    $credits,
                public int    $estimateDays,
                public int    $maxEstimateDays
            )
            {
            }

            public function hrCredits(): string
            {
                return number_format($this->credits);
            }

            public function hrEstimateDays(): string
            {
                return $this->numberHr($this->estimateDays);
            }

            public function hrMaxEstimateDays(): string
            {
                return $this->numberHr($this->maxEstimateDays);
            }

            private function numberHr(int $number): string
            {
                return match ($number <=> 0) {
                    -1 => (string)$number,
                    0 => "±0",
                    1 => "+{$number}",
                };
            }
        };

        return new $class(
            manufacturer: $arr['manufacturer'],
            model: $arr['name'],
            credits: $arr['credits'],
            estimateDays: $arr['estimatedays'],
            maxEstimateDays: $arr['maxestimatedays'],
        );
    }

    private function sortCars(object $a, object $b): int
    {
        $manComp = strcmp($a->manufacturer, $b->manufacturer);
        return $manComp === 0 ? strcmp($a->model, $b->model) : $manComp;
    }
}


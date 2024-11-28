<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-type CarObject object{
 *      manufacturer: string,
 *      model: string,
 *      credits: int,
 *      estimateDays: int,
 *      maxEstimateDays: int,
 *      state: string,
 *      dealership: string,
 * }
 */
#[AsCommand(
    name: 'app:get-gt-db-data',
)]
class GTDBCommand extends Command
{
    private const string URL = 'https://ddm999.github.io/gt7info/data.json';

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

        $legends = array_map(
            fn(array $arr) => $this->getCarClass($arr, 'legends'),
            $legendCars
        );

        $useds = array_map(
            fn(array $arr) => $this->getCarClass($arr, 'used'),
            $usedCars
        );

        /** @var CarObject[] $allCars */
        $allCars = array_filter(
            array_merge($legends, $useds),
            fn(object $car) => $car->state !== 'soldout'
        );

        usort($allCars, $this->sortCars(...));

        $io->table(
            [
                'Make',
                'Model',
                'Credits',
                'Est Days',
                'Max Est Days',
                'State',
                'Dealership',
            ],
            array_map(
                function (mixed $car) {
                    /** @var CarObject $car */
                    return [
                        $car->manufacturer,
                        $car->model,
                        number_format($car->credits),
                        $car->hrEstimateDays(),
                        $car->hrMaxEstimateDays(),
                        ucfirst($car->state),
                        ucfirst($car->dealership)
                    ];
                },
                $allCars)
        );

        return Command::SUCCESS;
    }


    /**
     * @param array $arr
     * @param string $dealership
     * @return CarObject
     */
    private function getCarClass(
        array  $arr,
        string $dealership
    ): object
    {
        echo json_encode($arr, JSON_PRETTY_PRINT) . PHP_EOL;
        return new class (
            manufacturer: $arr['manufacturer'],
            model: $arr['name'],
            credits: $arr['credits'],
            estimateDays: $arr['estimatedays'],
            maxEstimateDays: $arr['maxestimatedays'],
            state: $arr['state'],
            dealership: $dealership
        ) {
            public function __construct(
                public string $manufacturer,
                public string $model,
                public int    $credits,
                public int    $estimateDays,
                public int    $maxEstimateDays,
                public string $state,
                public string $dealership
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
    }

    private function sortCars(object $a, object $b): int
    {
        $manComp = strcmp($a->manufacturer, $b->manufacturer);
        return $manComp === 0 ? strcmp($a->model, $b->model) : $manComp;
    }

    private function makeTable(SymfonyStyle $io, array $cars, string $dealership): void
    {
        $table = $io->createTable();
        $table->setHeaders(['Make', 'Model', 'Credits', 'Est Days', 'Max Est Days']);
        $carObjs = array_map(
            $this->getCarClass(...),
            $cars
        );

        usort($carObjs, $this->sortCars(...));

        foreach ($carObjs as $car) {
            $table->addRow([
                $car->manufacturer,
                $car->model,
                $car->hrCredits(),
                $car->hrEstimateDays(),
                $car->hrMaxEstimateDays(),
            ]);
        }

        $table->render();
    }
}


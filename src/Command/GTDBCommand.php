<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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

    private const string STOCK_NORMAL = 'normal';
    private const string STOCK_LIMITED = 'limited';
    private const string STOCK_SOLD_OUT = 'soldout';

    private const string REWARD_TYPE_MENUBOOK = 'menubook';
    private const string REWARD_TYPE_LICENSE = 'license';
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
            fn(object $car) => $car->state !== self::STOCK_SOLD_OUT
        );

        usort($allCars, function (object $a, object $b) {
            $comps = [
                'price' => $b->credits - $a->credits,
                'manufacturer' =>strcmp($a->manufacturer, $b->manufacturer),
                'model' => strcmp($a->model, $b->model),
            ];

            foreach ($comps as $comp) {
                if ($comp !== 0) {
                    return $comp;
                }
            }

            return 0;
        });

        $io->table(
            [
                'Make',
                'Model',
                'Credits',
                'Est Days',
                'Max Est Days',
                'State',
                'Dealership',
                'Menu Book',
                'License'
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
                        match ($car->state) {
                            self::STOCK_SOLD_OUT => '<fg=red>' . ucfirst($car->state) . '</>',
                            self::STOCK_LIMITED => '<fg=yellow>' . ucfirst($car->state) . '</>',
                            default => ucfirst($car->state),
                        },
                        ucfirst($car->dealership),
                        $car->hrMenuBook(),
                        $car->hrLicense(),
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
        $rewardCar = $arr['rewardcar'];
        $menuBooks = null;
        $licenses = null;
        if (is_array($rewardCar)){
            if ($rewardCar['type'] === self::REWARD_TYPE_MENUBOOK) {
                $menuBooks = $rewardCar;
            } else if ($rewardCar['type'] === self::REWARD_TYPE_LICENSE) {
                $licenses = $rewardCar;
            }
        }


        return new class (
            manufacturer: $arr['manufacturer'],
            model: $arr['name'],
            credits: $arr['credits'],
            estimateDays: $arr['estimatedays'],
            maxEstimateDays: $arr['maxestimatedays'],
            state: $arr['state'],
            dealership: $dealership,
            menubook: $menuBooks,
            license: $licenses
        ) {
            public function __construct(
                public string $manufacturer,
                public string $model,
                public int    $credits,
                public int    $estimateDays,
                public int    $maxEstimateDays,
                public string $state,
                public string $dealership,
                public ?array $menubook,
                public ?array $license,
            )
            {
            }

            public function hrCredits(): string
            {
                return number_format($this->credits);
            }

            public function hrEstimateDays(): string
            {
                return $this->numberHr($this->maxEstimateDays);
            }

            public function hrMaxEstimateDays(): string
            {
                $diffDays = abs($this->maxEstimateDays);
                $interval = new \DateInterval("P{$diffDays}D");
                $dateTime = new \DateTime();
                if ($this->maxEstimateDays > 0) {
                    $dateTime->add($interval);
                } else {
                    $dateTime->sub($interval);
                }

                $ymd = $dateTime->format('Y-m-d');

                $colour = match ($this->maxEstimateDays <=> 0) {
                    -1 => 'red',
                    0 => 'orange',
                    default => 'green',
                };

                return "<bg={$colour}>{$ymd}</>";
            }

            public function hrMenuBook(): string
            {
                if (is_null($this->menubook)) {
                    return 'No';
                }

                return "Menubook {$this->menubook['name']}";
            }

            public function hrLicense(): string
            {
                if (is_null($this->license)) {
                    return 'No';
                }

                $requirement = ucfirst($this->license['requirement']);

                return "License: {$this->license['name']} {$requirement}";
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
}


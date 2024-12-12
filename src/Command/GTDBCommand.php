<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-type CarArray array{
 *      'manufacturer': string,
 *      'model': string,
 *      'credits': int,
 *      'estimateDays': int,
 *      'maxEstimateDays': int,
 *      'state': string,
 *      'dealership': string,
 *      'menubook': array,
 *      'license': array,
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $response = $this->client->request('GET', self::URL);

        [
            'used' => ['cars' => $usedCars],
            'legend' => ['cars' => $legendCars],
        ] = $response->toArray();

        $legends = array_map(
            fn (array $arr) => $this->getCarClass($arr, 'legends'),
            $legendCars
        );

        $useds = array_map(
            fn (array $arr) => $this->getCarClass($arr, 'used'),
            $usedCars
        );

        $allCars = array_filter(
            array_merge($legends, $useds),
            fn (array $car) => self::STOCK_SOLD_OUT !== $car['state']
        );

        usort($allCars, function (array $a, array $b) {
            $comps = [
                'price' => $b['credits'] - $a['credits'],
                'manufacturer' => strcmp($a['manufacturer'], $b['manufacturer']),
                'model' => strcmp($a['model'], $b['model']),
            ];

            foreach ($comps as $comp) {
                if (0 !== $comp) {
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
                'License',
            ],
            array_map(
                function (array $car) {
                    /** @var CarArray $car */
                    $diffDays = abs($car['maxEstimateDays']);
                    $interval = new \DateInterval("P{$diffDays}D");
                    $dateTime = new \DateTime();
                    if ($car['maxEstimateDays'] > 0) {
                        $dateTime->add($interval);
                    } else {
                        $dateTime->sub($interval);
                    }

                    $ymd = $dateTime->format('Y-m-d');

                    $colour = match ($car['maxEstimateDays'] <=> 0) {
                        -1 => 'red',
                        0 => 'orange',
                        default => 'green',
                    };

                    $endDate = "<bg={$colour}>{$ymd}</>";

                    return [
                        $car['manufacturer'],
                        $car['model'],
                        number_format($car['credits']),
                        $car['estimateDays'],
                        $endDate,
                        match ($car['state']) {
                            self::STOCK_SOLD_OUT => '<fg=red>'.ucfirst($car['state']).'</>',
                            self::STOCK_LIMITED => '<fg=yellow>'.ucfirst($car['state']).'</>',
                            default => ucfirst($car['state']),
                        },
                        ucfirst($car['dealership']),
                        $car['menubook'] ? "Menubook {$car['menubook']['name']}" : '',
                        $car['license'] ? "License: {$car['license']['name']} {$car['license']['requirement']}" : '',
                    ];
                },
                $allCars)
        );

        return Command::SUCCESS;
    }

    /**
     * @return CarArray
     */
    private function getCarClass(
        array $arr,
        string $dealership,
    ): array {
        $rewardCar = $arr['rewardcar'];
        $menuBooks = null;
        $licenses = null;
        if (is_array($rewardCar) && isset($rewardCar['type'])) {
            if (self::REWARD_TYPE_MENUBOOK === $rewardCar['type']) {
                $menuBooks = $rewardCar;
            } elseif (self::REWARD_TYPE_LICENSE === $rewardCar['type']) {
                $licenses = $rewardCar;
            }
        }

        return [
            'manufacturer' => $arr['manufacturer'],
            'model' => $arr['name'],
            'credits' => $arr['credits'],
            'estimateDays' => $arr['estimatedays'],
            'maxEstimateDays' => $arr['maxestimatedays'],
            'state' => $arr['state'],
            'dealership' => $dealership,
            'menubook' => $menuBooks,
            'license' => $licenses,
        ];
    }
}

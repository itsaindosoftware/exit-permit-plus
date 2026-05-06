<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cars = [
            [
                'police_no' => 'B 1122 RKE',
                'trade_merk' => 'CSM',
                'status' => 'ACTIVE',
                'spesification' => 'Daihatsu Grand Max MB 1.5 D PS FH 2022',
                'user_after' => 'HRGA Operational',
            ],
            [
                'police_no' => 'B 2015 UYI',
                'trade_merk' => 'PT. Obitrans Indonesia',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Avanza',
                'user_after' => 'Mr. Rico',
            ],
            [
                'police_no' => 'B 1325 HZN',
                'trade_merk' => 'MPM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Avanza',
                'user_after' => 'Ms. Wida',
            ],
            [
                'police_no' => 'B 1489 HZN',
                'trade_merk' => 'MPM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Avanza',
                'user_after' => 'Mr. Tomi',
            ],
            [
                'police_no' => 'B 1487 HZN',
                'trade_merk' => 'MPM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Avanza',
                'user_after' => 'Ms. Riama',
            ],
            [
                'police_no' => 'B 1142 RYD',
                'trade_merk' => 'CSM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Voxy',
                'user_after' => 'MD',
            ],
            [
                'police_no' => 'B 1229 HZN',
                'trade_merk' => 'MPM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Innova',
                'user_after' => 'Thai Team',
            ],
            [
                'police_no' => 'B 1120 NZX',
                'trade_merk' => 'MPM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Avanza',
                'user_after' => 'Mr. Idham',
            ],
            [
                'police_no' => 'BE 1547 AAO',
                'trade_merk' => 'MPM',
                'status' => 'ACTIVE',
                'spesification' => 'Toyota Avanza',
                'user_after' => 'Mr. Restu',
            ],
        ];

        foreach ($cars as $car) {
            Car::query()->updateOrCreate(
                ['police_no' => $car['police_no']],
                $car,
            );
        }
    }
}

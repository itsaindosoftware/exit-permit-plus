<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = [
            [
                'id_employee' => 'ITSA.044.06.07',
                'name' => 'Aldin Kertayasa',
                'department' => 'HR, GA & LEGAL',
            ],
            [
                'id_employee' => 'GPN0001',
                'name' => 'Agus Wahyudin',
                'department' => 'HR, GA & LEGAL',
            ],
            [
                'id_employee' => 'GPN0002',
                'name' => 'Chaerul Shaleh',
                'department' => 'HR, GA & LEGAL',
            ],
            [
                'id_employee' => 'GPN0003',
                'name' => 'Dedi Suryadi',
                'department' => 'HR, GA & LEGAL',
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::query()->updateOrCreate(
                ['id_employee' => $driver['id_employee']],
                $driver,
            );
        }
    }
}

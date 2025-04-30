<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            CompanySeeder::class,
            ClientSeeder::class,
            TenderSeeder::class,
            ContractSeeder::class,
            InvoiceSeeder::class,
            SdiNotificationSeeder::class,
            InvoiceItemSeeder::class
        ]);
    }
}

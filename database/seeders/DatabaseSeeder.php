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
        $this->call(UsersTableSeeder::class);
        $this->call(TypeSeeder::class);
        $this->call(RegionsTableSeeder::class);
        $this->call(ProvincesTableSeeder::class);
        $this->call(CitiesTableSeeder::class);
        $this->call(DocGroupsTableSeeder::class);
        $this->call(DocTypesTableSeeder::class);
        $this->call(CompaniesTableSeeder::class);
        $this->call(BankAccountsTableSeeder::class);
        $this->call(ClientsTableSeeder::class);
        $this->call(ContainersTableSeeder::class);
        $this->call(TendersTableSeeder::class);
        $this->call(ContractsTableSeeder::class);
        $this->call(InvoicesTableSeeder::class);
        $this->call(SdiNotificationsTableSeeder::class);
        $this->call(CompanyUserTableSeeder::class);
        $this->call(InvoiceItemsTableSeeder::class);
        $this->call(ActivePaymentsTableSeeder::class);
    }
}

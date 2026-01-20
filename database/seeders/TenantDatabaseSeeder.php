<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\Ingredient;
use App\Models\BranchStock;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Table;
use App\Models\Order;
use App\Models\Expense;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class TenantDatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Roles & Permissions
        $roles = ['Owner', 'Admin Pusat', 'Manajer Cabang', 'Kasir', 'Karyawan Dapur'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        // 2. Users
        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Owner Cafe', 'password' => bcrypt('password')]
        );
        $owner->assignRole('Owner');

        $kasir = User::firstOrCreate(
            ['email' => 'kasir@example.com'],
            ['name' => 'Kasir 1', 'password' => bcrypt('password')]
        );
        $kasir->assignRole('Kasir');

        // 3. Branch (Toko Pusat)
        $centralBranch = Branch::firstOrCreate(
            ['name' => 'Toko Pusat'],
            ['address' => 'Jl. Sudirman No. 1', 'phone' => '08123456789', 'is_central' => true]
        );

        // 4. Inventory (Suppliers & Ingredients)
        $supplier = Supplier::firstOrCreate(
            ['email' => 'supplier@kopi.com'],
            ['name' => 'Agen Kopi Jaya', 'phone' => '08987654321', 'address' => 'Medan']
        );

        $coffeeBean = Ingredient::firstOrCreate(
            ['name' => 'Biji Kopi Arabica'],
            ['unit' => 'gram', 'cost_per_unit' => 150] // Rp 150 per gram
        );
        $milk = Ingredient::firstOrCreate(
            ['name' => 'Susu Fresh Milk'],
            ['unit' => 'ml', 'cost_per_unit' => 20] // Rp 20 per ml
        );
        $sugar = Ingredient::firstOrCreate(
            ['name' => 'Gula Aren'],
            ['unit' => 'gram', 'cost_per_unit' => 30]
        );

        // 5. Initial Stock (Branch Stock)
        // Setup stock awal di Toko Pusat
        BranchStock::updateOrCreate(
            ['branch_id' => $centralBranch->id, 'ingredient_id' => $coffeeBean->id],
            ['quantity' => 5000] // 5kg
        );
        BranchStock::updateOrCreate(
            ['branch_id' => $centralBranch->id, 'ingredient_id' => $milk->id],
            ['quantity' => 10000] // 10L
        );
        BranchStock::updateOrCreate(
            ['branch_id' => $centralBranch->id, 'ingredient_id' => $sugar->id],
            ['quantity' => 2000] // 2kg
        );

        // 6. Menu (Categories, Products, Recipes)
        $catCoffee = ProductCategory::firstOrCreate(['name' => 'Coffee']);
        $catNonCoffee = ProductCategory::firstOrCreate(['name' => 'Non-Coffee']);

        $kopisusu = Product::firstOrCreate(
            ['name' => 'Kopi Susu Gula Aren'],
            [
                'category_id' => $catCoffee->id,
                'description' => 'Kopi susu kekinian dengan gula aren asli.',
                'price' => 18000,
                'is_active' => true
            ]
        );

        // Resep Kopi Susu: 18g Kopi, 150ml Susu, 20g Gula
        $kopisusu->recipes()->firstOrCreate(['ingredient_id' => $coffeeBean->id], ['quantity' => 18]);
        $kopisusu->recipes()->firstOrCreate(['ingredient_id' => $milk->id], ['quantity' => 150]);
        $kopisusu->recipes()->firstOrCreate(['ingredient_id' => $sugar->id], ['quantity' => 20]);

        $americano = Product::firstOrCreate(
            ['name' => 'Iced Americano'],
            [
                'category_id' => $catCoffee->id,
                'description' => 'Espresso dengan air dingin.',
                'price' => 15000,
                'is_active' => true
            ]
        );
        $americano->recipes()->firstOrCreate(['ingredient_id' => $coffeeBean->id], ['quantity' => 18]);

        // 7. Tables
        for ($i = 1; $i <= 5; $i++) {
            Table::firstOrCreate(
                ['branch_id' => $centralBranch->id, 'number' => 'T0' . $i],
                ['capacity' => 4, 'status' => 'available']
            );
        }

        // 8. Transactions (Dummy Data for Reports)
        // Simulate a completed order yesterday
        $yesterday = Carbon::yesterday();
        $order = Order::create([
            'branch_id' => $centralBranch->id,
            'customer_name' => 'Budi (Dummy)',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 36000, // 2 Kopi Susu
            'created_at' => $yesterday,
            'updated_at' => $yesterday
        ]);
        
        $order->items()->create([
            'product_id' => $kopisusu->id,
            'quantity' => 2,
            'unit_price' => 18000,
            'subtotal' => 36000
        ]);

        $order->payments()->create([
            'payment_method' => 'cash',
            'amount' => 36000,
            'payment_date' => $yesterday
        ]);

        // 9. Expenses
        Expense::create([
            'branch_id' => $centralBranch->id,
            'user_id' => $owner->id,
            'name' => 'Beli Es Batu',
            'amount' => 50000,
            'date' => $yesterday,
            'category' => 'supplies',
            'note' => 'Beli di warung sebelah'
        ]);
    }
}

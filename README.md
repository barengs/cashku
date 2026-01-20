# Cashku - Multi-Tenant Cloud POS System

Cashku adalah sistem Point of Sale (POS) berbasis Cloud yang mendukung Multi-Tenant (banyak cabang/bisnis dalam satu aplikasi). Dibangun menggunakan Laravel, sistem ini dirancang untuk mengelola operasional kafe atau restoran dari hulu ke hilir.

## 🚀 Fitur Utama

Sistem ini terdiri dari 5 Modul Utama:

### 1. Manajemen Pegawai (Employee)

- **User Profile**: Kelola data diri dan foto profil.
- **Role & Permission**: Hak akses berbeda untuk Owner, Manager, Kasir, dll.
- **Authentication**: JWT Auth untuk keamanan API.

### 2. Manajemen Inventori (Multi-Branch)

- **Suppliers**: Database pemasok bahan baku.
- **Ingredients**: Manajemen bahan baku (satuan, harga pokok).
- **Purchase Order (PO)**: Pembelian stok ke supplier.
- **Stock Transfer**: Kirim stok antar cabang.
- **Stock Waste**: Pencatatan stok terbuang/rusak.
- **Stock Opname**: Penyesuaian stok fisik vs sistem.

### 3. Menu & Harga (Product)

- **Kategori & Menu**: Manajemen produk jual.
- **Resep (Recipe)**: Link produk ke bahan baku untuk perhitungan HPP otomatis.
- **Pricing & Promosi**: Atur harga jual dan diskon berdasarkan periode.

### 4. Transaksi Kasir (POS)

- **Table Management**: Status meja (Available, Occupied).
- **Shift Kasir**: Buka/Tutup shift dengan rekonsiliasi uang tunai.
- **Order Processing**: Dine-in & Takeaway.
- **Auto-Stock Deduction**: Stok bahan baku berkurang otomatis saat pembayaran sukses berdasarkan resep.

### 5. Keuangan & Laporan

- **Expenses**: Catat biaya operasional (Listrik, Sewa, Gaji).
- **Sales Report**: Laporan omzet dan produk terlaris.
- **Profitability Report**: Laba kotor (Revenue - HPP).
- **Cash Flow**: Arus kas masuk vs keluar.

---

## 🛠️ Instalasi & Setup

### Prasyarat

- PHP 8.2+
- Composer
- MySQL / MariaDB (atau SQLite untuk testing)

### Langkah Instalasi

1. **Clone Repository**

    ```bash
    git clone https://github.com/barengs/cashku.git
    cd cashku
    ```

2. **Install Dependencies**

    ```bash
    composer install
    ```

3. **Setup Environment**
   Salin `.env.example` ke `.env` dan sesuaikan konfigurasi database.

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Migrasi & Seeding Database**
   Perintah ini akan membuat database central, membuat tenant default, dan menjalankan migrasi tenant.

    ```bash
    php artisan migrate:fresh --seed
    ```

    _Perintah ini menjalan `DatabaseSeeder` yang memanggil `TenantSeeder` untuk membuat tenant 'barengs' dengan database `tenantbarengs`._

5. **Jalankan Server**
    ```bash
    php artisan serve
    ```

---

## 🏢 Cara Membuat Tenant Baru

Cashku menggunakan arsitektur Multi-Tenant dimana setiap tenant memiliki database terpisah.

### Cara Otomatis (via Code/Tinker)

Anda dapat menggunakan Laravel Tinker untuk membuat tenant baru secara cepat.

1. Buka Tinker:

    ```bash
    php artisan tinker
    ```

2. Jalankan perintah pembuatan tenant:

    ```php
    // Buat Tenant dengan ID 'cabang01'
    $tenant = App\Models\Tenant::create(['id' => 'cabang01']);

    // Tambahkan Domain untuk akses (misal: cabang01.localhost)
    $tenant->domains()->create(['domain' => 'cabang01.localhost']);

    // (Opsional) Buat User Owner untuk tenant tersebut
    $tenant->run(function () {
        $user = App\Models\User::create([
            'name' => 'Owner Cabang 01',
            'email' => 'owner@cabang01.com',
            'password' => bcrypt('password')
        ]);
        // Assign Role jika perlu
        // $user->assignRole('Owner');
    });
    ```

Saat `Tenant::create` dijalankan, sistem secara otomatis akan:

1. Membuat database baru (misal: `tenantcabang01`).
2. Menjalankan semua migrasi (tabel users, products, orders, dll) ke database tersebut.

### Akses Tenant

Setelah dibuat, Anda bisa mengakses API tenant tersebut melalui domain yang didaftarkan:

- URL: `http://cabang01.localhost:8000/api/...`

---

## 📚 Dokumentasi API

Sistem ini dilengkapi dengan **Scramble** untuk dokumentasi otomatis (OpenAPI/Swagger).

Akses dokumentasi di browser:

```
http://{tenant_domain}/docs/api
```

Contoh untuk tenant default:
[http://barengs.localhost:8000/docs/api](http://barengs.localhost:8000/docs/api)

---

## ✅ Testing

Untuk memastikan semua modul berjalan dengan baik, jalankan automated test:

```bash
php artisan test
```

Saat ini terdapat **28 Tests** yang mencakup seluruh alur bisnis dari Employee hingga Financial Report.

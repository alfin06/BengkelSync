# Panduan Pengguna BengkelSync

Panduan singkat untuk staf bengkel: Admin Depan, Keuangan, dan Pemilik. Aplikasi bisa dipakai di HP, tablet, maupun komputer. Di HP, menu utama ada di bawah layar; menu lain ada di **Lainnya**. Di komputer, menu ada di sisi kiri.

> Menu yang tampil berbeda untuk setiap peran. Kalau sebuah menu atau tombol tidak terlihat, berarti peran Anda tidak punya akses ke situ. Minta pemilik bengkel mengubahnya bila perlu.

---

## 1. Masuk ke aplikasi

### Akun demo (hanya untuk mencoba)

Akun ini **hanya ada di aplikasi demo** (setelah `php artisan migrate:fresh --seed`), bukan di bengkel yang sudah berjalan. Semua akun demo memakai kata sandi yang sama: **`password1`**.

| Email | Peran | Cabang |
|---|---|---|
| `owner@demo.test` | Pemilik | Semua cabang |
| `manager@demo.test` | Kepala Cabang | Pusat dan Cabang Timur |
| `frontdesk@demo.test` | Admin Depan | Pusat |
| `finance@demo.test` | Keuangan | Pusat |
| `warehouse@demo.test` | Gudang | Pusat dan Cabang Timur |

Nama bengkel demo adalah **Bengkel Demo**, dengan dua cabang: **Pusat** dan **Cabang Timur**. Isinya contoh SPK di setiap status, invoice (ada yang dibayar sebagian dengan dua metode), pembelian, pengeluaran, dan barang yang stoknya menipis.

### Akun bengkel sungguhan

- Akun staf dibuat oleh **Pemilik** di menu **Pengguna & akses** (lihat bagian 8). Pemilik memberikan email dan kata sandi awal kepada staf.
- Buka alamat aplikasi, isi **Email** dan **Kata sandi**, lalu tekan **Masuk**. Centang **Ingat saya** hanya di perangkat pribadi.
- Setelah masuk pertama kali, ganti kata sandi Anda di **Profil saya** → **Kata sandi**.
- **Lupa kata sandi?** Tekan tautan itu di halaman masuk, isi email, lalu buka tautan yang dikirim ke email Anda. Bisa juga minta Pemilik membuat kata sandi baru.
- Jangan berbagi akun. Setiap perubahan tercatat atas nama orang yang masuk.

### Profil dan cabang

- **Profil saya**: ubah nama, nomor HP, bahasa (Indonesia/English), dan kata sandi.
- **Pilih cabang**: kalau Anda punya akses ke lebih dari satu cabang, pilih cabang yang sedang dikerjakan di bagian atas layar. Semua data (SPK, invoice, stok, kas) mengikuti cabang yang dipilih.
- **Lonceng (Notifikasi)**: pengingat stok menipis, tagihan pelanggan, dan tagihan supplier yang mendekati atau lewat jatuh tempo.
- **Keluar**: selalu keluar setelah memakai komputer bersama.

---

## 2. Alur harian Admin Depan

### a. Kendaraan masuk (membuat SPK)

1. Buka **SPK** → **Buat SPK**.
2. Ketik plat nomor. Kalau kendaraan sudah pernah datang, pilih dari daftar. Kalau belum, pilih **Kendaraan baru** lalu isi data kendaraan dan pemiliknya (atau **Pelanggan baru**).
3. Tulis keluhan pelanggan, kilometer, dan perkiraan jam selesai. Aplikasi memberi peringatan bila kilometer lebih kecil dari kunjungan sebelumnya.
4. Tekan **Simpan & masuk antrian**. Untuk perkiraan biaya yang belum pasti dikerjakan, pakai **Simpan draft**.
5. Bila perlu, tekan **Cetak SPK** untuk lembar kerja mekanik (tanpa harga).

### b. Selama dikerjakan

Di halaman SPK:
- **Mekanik**: pilih mekanik yang mengerjakan.
- **Tambah jasa**: pilih dari Daftar jasa atau ketik sendiri, lalu isi harganya.
- **Tambah part**: pilih barang dan jumlahnya. **Stok langsung berkurang saat part disimpan**, dan kembali bila part dihapus dari SPK. Barang yang stoknya habis tidak bisa dipakai.
- **Tambah ke daftar cek**: daftar pekerjaan yang bisa dicentang satu per satu.
- Ubah status sesuai keadaan: **Masukkan antrian** → **Kerjakan** → **Tunggu part** (bila menunggu barang) → **Selesai**.
- **Batalkan SPK** (wajib isi alasan) hanya bisa sebelum invoice dibuat. Part yang sudah dipakai kembali ke stok.
- **Riwayat servis** menampilkan servis kendaraan ini sebelumnya, termasuk di cabang lain.

### c. Membuat invoice

1. Setelah SPK berstatus **Selesai**, tekan **Buat invoice**.
2. Jasa dan part terisi otomatis dari SPK. Harga dan diskon masih bisa disesuaikan.
3. Tekan **Simpan invoice**. Status SPK berubah menjadi **Ditagih**.
4. Tekan **Cetak** untuk mencetak invoice (ukuran A5, cocok untuk kertas 2 ply).

**Penjualan langsung** (tanpa SPK, misalnya beli oli atau aki): buka **Invoice** → **Penjualan langsung**. Untuk pembeli yang tidak perlu dicatat, pilih **Pelanggan umum**. Stok barang langsung berkurang.

### d. Menerima pembayaran

1. Di halaman invoice, tekan **Bayar**.
2. Pilih **Metode pembayaran** (tunai, transfer, kartu, dll.) dan **Jumlah**. Pelanggan boleh membayar dengan beberapa metode sekaligus: tekan **Metode lain**.
3. Untuk tunai, isi **Uang diterima**. Aplikasi menghitung kembaliannya.
4. Pembayaran kartu kredit bisa dikenai **Biaya kartu** di atas tagihan (diatur di pengaturan metode pembayaran).
5. Metode tertentu (mis. transfer) wajib diisi **No. referensi**.
6. Bila pelanggan belum melunasi, isi **Jatuh tempo sisa**, yaitu kapan sisanya akan dibayar. Sisa ini masuk ke **Piutang pelanggan**.
7. Tekan **Simpan pembayaran**, lalu **Cetak kwitansi** bila diminta.

Saat pelanggan kembali melunasi, buka invoice-nya (atau lewat **Piutang pelanggan**) dan tekan **Bayar** lagi. Metodenya boleh berbeda dari pembayaran pertama. Setelah lunas, SPK otomatis **Ditutup**.

### e. Memperbaiki invoice

- Tekan **Ubah** di invoice, perbaiki baris yang salah, isi **alasan**, lalu **Simpan perubahan**.
- Nomor invoice tidak berubah. Versi lama tersimpan dan bisa dilihat di **Riwayat perubahan**.
- Admin Depan hanya bisa mengubah invoice yang **belum ada pembayarannya**. Invoice yang sudah dibayar diubah oleh Keuangan.
- Total baru tidak boleh lebih kecil dari jumlah yang sudah dibayar.

### f. Pembelian barang dan pengeluaran kecil

- **Pembelian** → **Catat pembelian**: isi supplier dan barang sesuai nota. Stok langsung bertambah. Admin Depan boleh **Lunasi sekarang dari kas** hanya jika lunas, dibayar dari laci kasir atau kas kecil, dan tidak melebihi batas bayar tunai cabang (bawaan Rp 500.000). Selebihnya dicatat sebagai hutang dan dibayar oleh Keuangan.
- **Pengeluaran** → **Catat pengeluaran**: untuk pengeluaran kecil dari kas (mis. bensin, makan, ATK). Foto nota bisa diambil langsung dengan kamera HP.

---

## 3. Tugas Keuangan

Keuangan bisa melakukan semua yang dilakukan Admin Depan, ditambah:

### Piutang pelanggan
- **Piutang pelanggan** menampilkan semua invoice yang belum lunas, diurutkan dari jatuh tempo terdekat. Pengingat juga muncul di lonceng notifikasi.

### Membatalkan pembayaran
- Di halaman invoice, bagian **Pembayaran**: **Batalkan baris** untuk satu metode saja, atau **Batalkan kwitansi** untuk seluruh pembayaran dalam satu kwitansi. Alasan wajib diisi.
- Data tidak pernah dihapus. Pembayaran yang dibatalkan tetap terlihat dengan tanda **BATAL**, uangnya dikeluarkan lagi dari akun kas, dan jumlahnya kembali menjadi sisa tagihan.

### Membatalkan invoice
- **Batalkan invoice** (alasan wajib) hanya bisa jika tidak ada pembayaran yang masih berlaku; batalkan pembayarannya dulu.
  - Invoice dari SPK: SPK kembali ke status **Selesai**, bisa diperbaiki, lalu ditagih lagi.
  - Penjualan langsung: semua part dikembalikan ke stok.

### Hutang supplier
- **Hutang supplier** menampilkan pembelian yang belum lunas. Tekan **Bayar**, pilih akun **Dibayar dari** dan jumlahnya. Boleh dicicil.
- Pembayaran atau pembelian yang salah bisa dibatalkan dengan alasan.

### Kas
- **Kas** menampilkan saldo setiap akun (Laci Kasir, Kas Kecil, rekening bank) dan **Riwayat** transaksinya.
- **Transfer**: memindahkan uang antar akun, mis. mengisi kas kecil dari laci kasir atau menyetor ke bank.
- **Hitung kas**: isi jumlah uang yang benar-benar ada. Selisihnya dicatat dengan alasan. Dipakai juga untuk mengisi **saldo awal** saat mulai memakai aplikasi.
- Laci kasir dan kas kecil tidak bisa minus. Saldo bank boleh minus.

### Pengeluaran
- **Catat pengeluaran** dari akun mana saja, dengan kategori dan foto nota.
- Pengeluaran yang salah: tekan **Batalkan** (alasan wajib). Uangnya kembali ke akun kas.
- Kategori pengeluaran diatur di **Kategori pengeluaran** (Pemilik dan Keuangan).
- Kategori **Gaji & bonus** hanya bisa dilihat dan dicatat oleh Pemilik. Saran: bayar gaji dari rekening bank, karena pengeluaran dari laci kasir tetap terlihat sebagai uang keluar oleh orang yang bisa melihat kas (walau keterangannya disembunyikan).

### Beranda
- Keuangan melihat penjualan, uang masuk, pengeluaran, grafik 12 bulan, jasa dan part terlaris, serta pekerjaan per mekanik.
- **Penjualan** adalah total invoice; **Uang masuk** adalah uang yang benar-benar diterima. Biaya kartu tidak dihitung sebagai pendapatan.

---

## 4. Stok dan suku cadang

- **Suku cadang** → **Tambah barang**: nama, satuan, harga beli dan harga jual, serta batas stok minimum. Satuan liter, meter, dan kg boleh desimal (mis. 4,2 L).
- Stok bertambah lewat **Pembelian** dan berkurang lewat SPK atau penjualan langsung. Jangan mengubah stok dengan cara lain.
- **Koreksi stok** (stok opname): isi jumlah yang benar-benar ada di rak, beserta alasannya.
- **Riwayat stok** menampilkan setiap barang masuk dan keluar beserta sumbernya.
- Jika stok di bawah batas minimum, notifikasi muncul di lonceng dan di Beranda.

---

## 5. Pelanggan, kendaraan, jasa, dan mekanik

- **Pelanggan**: nomor HP boleh ditulis `08…`. Aplikasi memberi peringatan bila nomor yang sama sudah dipakai pelanggan lain.
- Plat nomor otomatis dirapikan (mis. `B 1234 XYZ`). Kendaraan yang pindah tangan: pilih **Pemilik baru**; pemilik lama tetap tercatat di **Riwayat pemilik**.
- Pelanggan yang masih punya kendaraan tidak bisa dihapus.
- **Daftar jasa**: daftar pekerjaan dan harga standar untuk mempercepat pengisian SPK. Harga tetap bisa diubah di setiap SPK.
- **Mekanik**: mekanik tidak perlu akun. NIK dan foto KTP disimpan secara aman dan hanya terlihat oleh peran yang berhak.
- Jasa dan mekanik yang sudah pernah dipakai tidak bisa dihapus, tetapi bisa dinonaktifkan.

---

## 6. Pemilik

Pemilik bisa melakukan semuanya, termasuk:

- **Beranda**: juga melihat **laba kotor** (hanya Pemilik).
- **Toko & cabang**: nama bengkel dan **Kunci invoice setelah** sekian hari. Invoice yang lebih lama dari periode ini tidak bisa diubah atau dibatalkan, dan pembayarannya tidak bisa dibatalkan, kecuali oleh Pemilik. Perubahan oleh Pemilik ditandai **Diubah setelah periode dikunci** di riwayat. Pembayaran baru untuk invoice lama tetap bisa diterima.
- **Cabang** (di halaman **Toko & cabang**):
  - **Tambah cabang**: isi nama, **Kode** (2–6 huruf/angka, dipakai di nomor dokumen, mis. `KLM/INV/2026/00001`), alamat, telepon, NPWP, **Zona waktu** (WIB/WITA/WIT), dan **Logo**. Cabang baru otomatis mendapat Laci Kasir, Kas Kecil, dan metode bayar Tunai; stok semua barang mulai dari nol. Beri akses staf lewat **Pengguna & akses**.
  - **Ubah** (ikon pensil): Pemilik untuk semua cabang, Kepala Cabang untuk cabangnya sendiri. Logo dicetak di invoice, kwitansi, dan SPK. **Kode tidak bisa diubah** setelah cabang mengeluarkan dokumen pertama.
  - **Hapus** (ikon tempat sampah, hanya Pemilik): ditolak bila masih ada SPK yang belum ditutup/dibatalkan, invoice belum lunas, hutang supplier, atau saldo di akun kas cabang itu. Cabang yang belum pernah dipakai dihapus permanen. Cabang yang sudah punya data **tidak dihapus datanya**: cabang hanya disembunyikan dan muncul di **Cabang yang dihapus**, lalu bisa dikembalikan dengan **Pulihkan**.
  - Cabang terakhir tidak bisa dihapus.
- **Pajak & invoice** (juga Kepala Cabang): nyalakan **Tagih pajak di invoice**, nama dan tarif pajak, harga **Sudah termasuk pajak** atau **Belum termasuk pajak**, serta **Catatan kaki invoice** (mis. nomor rekening atau garansi). Berlaku hanya untuk invoice baru.
- **Akun kas & pembayaran**: akun kas, metode pembayaran (beserta biaya kartu dan kewajiban no. referensi), serta batas bayar tunai untuk Admin Depan.

---

## 7. Peran bawaan

| Peran | Singkatnya |
|---|---|
| **Pemilik** | Semua hal. |
| **Kepala Cabang** | Semua hal di cabangnya, kecuali peran & hak akses, data toko, gaji, laba, dan invoice yang sudah dikunci. |
| **Admin Depan** | Pelanggan, kendaraan, SPK, invoice (termasuk diskon dan memperbaiki invoice yang belum dibayar), terima pembayaran, lihat stok, catat pembelian, pengeluaran kecil dari kas. |
| **Keuangan** | Semua tugas Admin Depan, ditambah mekanik, memperbaiki invoice yang sudah dibayar, membatalkan invoice dan pembayaran, bayar supplier, transfer dan hitung kas, pengeluaran, dan laporan keuangan di Beranda. |
| **Gudang** | Suku cadang, stok, supplier, dan pembelian. |

---

## 8. Mengelola pengguna dan hak akses (Pemilik)

Menu **Pengguna & akses**:

- **Tambah pengguna**: isi nama, **Email (untuk masuk)**, **Kata sandi** (minimal 8 karakter, berisi huruf dan angka), **Peran**, dan **Cabang**. Berikan email dan kata sandinya kepada staf secara langsung, jangan lewat grup.
- **Kata sandi**: membuat kata sandi baru untuk staf yang lupa. Staf tersebut otomatis keluar dari semua perangkat.
- **Boleh masuk (aktif)**: matikan untuk staf yang berhenti bekerja. Akunnya langsung tidak bisa dipakai, tetapi riwayat kerjanya tetap tersimpan.
- **Peran & hak akses**: ubah apa saja yang boleh dilakukan setiap peran, atau buat **Peran baru** (mis. Kasir, Kepala Mekanik). Peran bawaan tidak bisa dihapus, tetapi hak aksesnya bisa diubah.
- Selalu ada minimal satu Pemilik yang aktif. Pemilik tidak bisa mengubah akunnya sendiri dari menu ini; pakai **Profil saya**.

---

## 9. Hal penting yang perlu diingat

- **Tidak ada yang dihapus diam-diam.** Invoice, pembayaran, pembelian, dan pengeluaran yang salah dibatalkan dengan alasan, dan catatannya tetap ada.
- **Setiap perubahan tercatat**: siapa, kapan, dan apa yang diubah.
- **Stok dan kas hanya berubah lewat transaksi** (SPK, invoice, pembelian, pembayaran, pengeluaran, koreksi stok, hitung kas). Kalau angkanya tidak cocok, pakai **Koreksi stok** atau **Hitung kas** dengan alasan yang jelas.
- **Mencetak**: tombol cetak membuka jendela cetak browser. Pilih ukuran kertas **A5**. Untuk menyimpan sebagai PDF, pilih printer **Save as PDF / Simpan sebagai PDF**.
- **Pilihan (dropdown) bisa diketik**: ketuk kolomnya lalu ketik sebagian nama (mis. nama supplier atau akun kas) untuk menyaring daftar, lalu pilih atau tekan Enter. Di HP, daftar pendek (6 pilihan atau kurang) langsung terbuka tanpa keyboard.
- Jika muncul pesan merah, baca isinya: biasanya menjelaskan aturan yang mencegah perubahan (mis. stok tidak cukup, atau invoice sudah dikunci).

---

## 10. Persiapan sebelum mulai dipakai (Pemilik)

1. Isi **Toko & cabang**: nama, alamat, telepon, NPWP, dan logo.
2. Atur **Pajak & invoice**: pakai pajak atau tidak, dan catatan kaki invoice.
3. Periksa **Akun kas & pembayaran**: akun kas, rekening bank, metode pembayaran, dan batas bayar tunai.
4. Isi saldo awal setiap akun lewat **Kas** → **Hitung kas** (alasan: "saldo awal").
5. Masukkan **Daftar jasa**, **Mekanik**, **Supplier**, dan **Suku cadang** beserta stok awalnya (lewat **Koreksi stok**, alasan: "stok awal").
6. Buat akun untuk Admin Depan dan Keuangan di **Pengguna & akses**.
7. Coba cetak satu invoice dan satu kwitansi di printer bengkel sebelum hari pertama.

Saya ingin membuat sistem Marketlist MBG menggunakan PHP Native, MySQL, Bootstrap 5, font awesome, dan jQuery. Ini adalah PART 1 dari 4 part.

KONTEKS SISTEM:
- Sistem untuk mengelola permintaan barang dari 6 kantor cabang ke 1 koperasi dan 1 gudang pusat
- Database sudah dibuat dengan nama: marketlist_mbg
- Terdapat 3 jenis barang: Bahan Baku (BB), Bahan Jadi (BJ), dan Perlengkapan Kantor (PK)
- User role: admin, koperasi, gudang, kantor

PART 1 - SETUP & AUTHENTICATION:

sudah buat struktur folder dan file berikut:

1. STRUKTUR FOLDER:
/marketlist_mbg/
├── /assets/
│   ├── /css/
│   ├── /js/
│   ├── /img/
│   └── /uploads/
├── /config/
│   └── database.php
├── /includes/
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   └── sidebar.php
├── /modules/
│   ├── /auth/
│   ├── /dashboard/
│   ├── /master/
│   ├── /request/
│   ├── /pembelanjaan/
│   ├── /penerimaan/
│   ├── /distribusi/
│   ├── /piutang/
│   ├── /gudang/
│   └── /laporan/
├── /helpers/
│   ├── functions.php
│   └── session.php
├── index.php
└── logout.php
noted :sudah

2. FITUR YANG HARUS DIBUAT:

A. config/database.php
   - Koneksi database dengan mysqli
   - Error handling
   - Set charset utf8mb4

B. helpers/session.php
   - Fungsi start session
   - Fungsi cek login
   - Fungsi cek role
   - Fungsi get user data dari session

C. helpers/functions.php
   - Fungsi generate nomor otomatis (REQ/2024/12/001, dst)
   - Fungsi format tanggal indonesia
   - Fungsi format rupiah
   - Fungsi sanitize input
   - Fungsi upload file
   - Fungsi generate QR code (gunakan library phpqrcode)
   - Fungsi alert/notification

D. modules/auth/login.php
   - Form login dengan username & password
   - Validasi login
   - Set session user
   - Redirect ke dashboard sesuai role
   - Remember me (optional)

E. modules/dashboard/index.php
   - Dashboard untuk masing-masing role:
     * Admin: statistik lengkap semua modul
     * Koperasi: request pending, pembelanjaan hari ini
     * Gudang: stok menipis, barang akan expired
     * Kantor: request saya, status distribusi
   - Widget dengan card bootstrap
   - Chart menggunakan Chart.js (grafik pembelanjaan bulanan, distribusi per kantor)

F. includes/navbar.php & sidebar.php
   - Navbar dengan user profile & logout
   - Sidebar menu sesuai role user
   - Active menu indicator

G. index.php
   - Landing page atau redirect ke login/dashboard

REQUIREMENTS:
- Gunakan Bootstrap 5.3
- jQuery 3.7
- Font Awesome 6 untuk icon
- Chart.js untuk grafik
- Responsive design
- Clean code dengan komentar
- Gunakan prepared statement untuk query
- Password di-hash dengan MD5 (sesuai database)

Buatkan kode lengkap untuk PART 1 ini dengan penjelasan singkat untuk setiap file.
noted :sudah

Lanjutan sistem Marketlist MBG - PART 2 dari 4 part.

PART 2 - MASTER DATA:

Buatkan CRUD lengkap untuk semua master data dengan fitur:
- DataTables untuk list data (server-side processing)
- Form add/edit dengan validasi
- Soft delete dengan konfirmasi
- Export to Excel
- Print
- Search & Filter

1. modules/master/user/
   - List user dengan filter role
   - Form user (username, password, nama, email, no_hp, role, kantor_id jika role=kantor, foto)
   - Upload foto profile
   - Change password
   - Activate/deactivate user

2. modules/master/kantor/
   - List kantor
   - Form kantor (kode otomatis KTR001, nama, alamat, no_telp, PIC name, PIC phone)
   - Status aktif/nonaktif

3. modules/master/jenis_barang/
   - List jenis barang (BB, BJ, PK)
   - Form jenis barang
   - Tidak bisa delete jika sudah ada kategori terkait

4. modules/master/kategori/
   - List kategori dengan parent hierarchy (tree view)
   - Filter by jenis barang
   - Form kategori (kode otomatis BB-001, nama, jenis barang, parent kategori, deskripsi)
   - Drag & drop untuk ubah parent (optional)

5. modules/master/satuan/
   - List satuan sederhana
   - Form satuan (nama, keterangan)

6. modules/master/produk/
   - List produk dengan filter jenis barang & kategori
   - Form produk lengkap:
     * Kode otomatis berdasarkan jenis (BB-001, BJ-001, PK-001)
     * Nama produk
     * Jenis barang (dropdown)
     * Kategori (dropdown filtered by jenis barang)
     * Satuan
     * Tipe item (stok/distribusi/khusus)
     * Status produk (persiapan/running/nonaktif)
     * Harga estimasi
     * Stok minimum
     * Masa kadaluarsa (jika bahan baku)
     * Spesifikasi
     * Deskripsi
     * Upload gambar
     * Generate barcode otomatis
   - Bulk import dari Excel
   - View detail produk dengan history stok

7. modules/master/supplier/
   - List supplier
   - Form supplier (kode otomatis SUP-001, nama, alamat, kontak, PIC, status)
   - Rating supplier (optional)

REQUIREMENTS:
- Semua form harus ada validasi client-side (jQuery) dan server-side (PHP)
- DataTables dengan ajax untuk performa
- Sweet Alert 2 untuk konfirmasi delete
- Export Excel menggunakan PhpSpreadsheet
- Upload image dengan preview dan resize
- Generate barcode menggunakan library php-barcode
- Breadcrumb navigation
- Loading indicator saat proses

Buatkan kode lengkap untuk PART 2 dengan struktur file yang rapi.


Lanjutan sistem Marketlist MBG - PART 3 dari 4 part.

PART 3 - TRANSAKSI UTAMA:

Buatkan modul transaksi dengan alur lengkap sesuai bisnis proses.

1. modules/request/ (Permintaan Barang dari Kantor)
   
   A. list.php
      - List request dengan filter: status, tanggal, kantor
      - Card view dengan color status (pending=warning, diproses=info, selesai=success, ditolak=danger)
      - Action button sesuai role dan status
   
   B. add.php (Role: Kantor)
      - Form request header (no otomatis, tanggal, keperluan, tanggal butuh)
      - Form detail dengan multiple item:
        * Select produk (dengan autocomplete)
        * Input qty
        * Keterangan
        * Tombol add item, remove item
      - Tombol simpan draft atau submit request
   
   C. detail.php
      - View detail request header & items
      - Timeline status request
      - Tombol approve/reject (Role: Koperasi)
      - Form input qty approved (bisa berbeda dengan qty request)
   
   D. approve.php (Role: Koperasi)
      - Review request dari kantor
      - Cek stok di gudang otomatis (highlight jika stok tidak cukup)
      - Input qty approved per item
      - Keterangan approval

2. modules/pembelanjaan/ (Belanja ke Pasar)
   
   A. list.php
      - List pembelanjaan dengan filter: periode type, tanggal, supplier
      - Summary total belanja per periode
      - Chart pembelanjaan (harian/mingguan/bulanan)
   
   B. add.php (Role: Koperasi)
      - Form header:
        * No otomatis BLJ/2024/12/001
        * Tanggal
        * Periode type (harian/mingguan/bulanan)
        * Periode value (auto generate)
        * Supplier (optional)
        * Request terkait (optional)
        * Upload bukti belanja
        * Keterangan
      - Form detail pembelanjaan (multiple items):
        * Produk
        * Qty
        * Harga satuan
        * Subtotal (auto calculate)
        * Total belanja (auto sum)
   
   C. detail.php
      - View detail pembelanjaan
      - Tombol proses penerimaan barang

3. modules/penerimaan/ (Penerimaan Barang)
   
   A. list.php
      - List penerimaan dengan status (diterima_koperasi, masuk_gudang)
   
   B. add.php (Role: Koperasi)
      - Form header:
        * No otomatis TRM/2024/12/001
        * Tanggal terima
        * Pembelanjaan terkait (load data pembelanjaan)
        * Supplier
        * No surat jalan
        * Kondisi barang
        * Keterangan
      - Form detail:
        * List barang dari pembelanjaan
        * Qty terima (bisa berbeda dengan qty beli)
        * Qty ke gudang (untuk stok)
        * Qty distribusi langsung (jika ada request)
        * Kondisi per item
   
   C. detail.php
      - View penerimaan
      - Tombol kirim ke gudang (update stok gudang otomatis)

4. modules/distribusi/ (Distribusi ke Kantor)
   
   A. list.php
      - List distribusi dengan filter kantor, status, tanggal
      - Scan QR untuk penerimaan cepat
   
   B. add.php (Role: Koperasi)
      - Form header:
        * No surat jalan otomatis DST/2024/12/001
        * Generate QR Code otomatis
        * Tanggal kirim
        * Request terkait (load data request)
        * Kantor tujuan
        * Pengirim
        * Keterangan
      - Form detail:
        * List item dari request
        * Qty request (dari request awal)
        * Qty kirim (input)
        * Cek stok gudang realtime
        * Keterangan
   
   C. detail.php
      - View distribusi dengan QR Code besar
      - Print surat jalan dengan QR Code
      - Status tracking
   
   D. scan.php (Role: Kantor - Akses via mobile friendly)
      - Scan QR Code (gunakan HTML5 camera + jsQR library)
      - Tampilkan detail distribusi yang di-scan
      - Form penerimaan:
        * Nama penerima
        * Per item input qty terima (bandingkan dengan qty kirim)
        * Kondisi terima (lengkap/kurang/rusak/lebih)
        * Alasan jika ada selisih (wajib jika kurang/rusak)
        * Ambil GPS location otomatis
        * Upload foto barang
      - Simpan data penerimaan
      - Update status distribusi
      - Catat di log_scan_qr

REQUIREMENTS:
- Ajax untuk load data dinamis
- Autocomplete untuk select produk
- Real-time stock checking
- QR Code generation (phpqrcode)
- QR Code scanner (jsQR + HTML5 camera)
- Geolocation API untuk GPS
- Print layout untuk surat jalan
- Timeline UI untuk tracking status
- Validation untuk mencegah over-distribution
- Transaction/rollback untuk konsistensi data

Buatkan kode lengkap PART 3 dengan alur yang jelas dan error handling yang baik.



Lanjutan sistem Marketlist MBG - PART 4 dari 4 part (TERAKHIR).

PART 4 - GUDANG, PIUTANG & LAPORAN:

1. modules/gudang/stok/
   
   A. list.php (Role: Gudang)
      - DataTable stok gudang dengan filter:
        * Jenis barang
        * Kategori
        * Status (normal/menipis/akan expired/expired/rusak)
        * Kondisi
      - Alert stok menipis (highlight merah)
      - Alert akan expired dalam 30 hari (highlight kuning)
      - Card summary: total item, total stok, stok menipis, akan expired
   
   B. detail.php
      - Detail stok produk
      - History kartu stok (masuk/keluar)
      - Batch list (jika ada)
      - Chart trend stok 6 bulan terakhir
   
   C. adjustment.php (Role: Gudang/Admin)
      - Form adjustment stok manual
      - Alasan adjustment (wajib)
      - Auto catat ke kartu_stok

2. modules/gudang/opname/
   
   A. list.php
      - List stok opname dengan status
      - Schedule opname (bulanan/triwulan)
   
   B. add.php (Role: Gudang)
      - Form header:
        * No otomatis OPN/2024/12/001
        * Tanggal opname
        * Periode
        * Lokasi (gudang/koperasi)
        * PIC
      - Load semua stok dari sistem
      - Form input qty fisik per item
      - Auto calculate selisih
      - Highlight jika ada selisih
   
   C. detail.php
      - View hasil opname
      - List item dengan selisih
      - Approval opname (Role: Admin)
      - Auto adjustment stok setelah approved

3. modules/piutang/
   
   A. list.php (Role: Koperasi/Admin)
      - List piutang per kantor
      - Filter: status (belum lunas/lunas), kantor, tanggal
      - Card aging piutang: 0-30 hari, 31-60 hari, >60 hari
      - Alert piutang jatuh tempo
   
   B. add.php (Role: Koperasi)
      - Form piutang baru:
        * No otomatis HTG/2024/12/001
        * Tanggal
        * Kantor
        * Distribusi terkait (optional)
        * Total piutang
        * Jatuh tempo
        * Keterangan
   
   C. detail.php
      - Detail piutang
      - History pembayaran
      - Sisa piutang
      - Form input pembayaran:
        * Tanggal bayar
        * Jumlah bayar
        * Metode (tunai/transfer/giro)
        * No referensi
        * Upload bukti bayar
        * Keterangan
      - Auto update status jika lunas
   
   D. reminder.php
      - Send email/WA reminder piutang jatuh tempo (optional)

4. modules/laporan/
   
   A. request.php
      - Filter: periode, kantor, status
      - Summary: total request, approved, rejected
      - Detail per kantor
      - Export Excel, PDF, Print
   
   B. pembelanjaan.php
      - Filter: periode type, tanggal mulai-selesai, supplier
      - Chart pembelanjaan per periode
      - Summary: total belanja, rata-rata per hari/minggu/bulan
      - Detail per transaksi
      - Comparison periode sebelumnya
      - Export Excel, PDF
   
   C. distribusi.php
      - Filter: periode, kantor
      - Summary distribusi per kantor
      - Chart distribusi 6 bulan terakhir
      - Status penerimaan (sesuai/kurang/lebih)
      - Export Excel, PDF
   
   D. stok_gudang.php
      - Laporan stok per jenis barang
      - Stok minimum
      - Stok menipis alert
      - Barang expired/akan expired
      - Batch tracking
      - Export Excel
   
   E. kartu_stok.php
      - Laporan mutasi stok per produk
      - Filter: periode, produk
      - Detail masuk/keluar/opname/adjustment
      - Saldo awal - saldo akhir
      - Export Excel, PDF
   
   F. piutang.php
      - Laporan piutang per kantor
      - Aging analysis
      - History pembayaran
      - Piutang jatuh tempo
      - Export Excel, PDF
   
   G. comparison_delivery.php
      - Laporan perbandingan: Request vs Kirim vs Terima
      - Filter: periode, kantor
      - Tampilkan qty diminta, dikirim, diterima, selisih
      - Alasan selisih
      - Chart akurasi pengiriman per kantor
      - Export Excel, PDF
   
   H. dashboard_executive.php (Role: Admin)
      - KPI Dashboard:
        * Total request bulan ini
        * Total pembelanjaan
        * Stok menipis
        * Piutang outstanding
        * Akurasi pengiriman (%)
      - Chart trend 6 bulan
      - Top 10 produk terbanyak direquest
      - Top supplier
      - Performance per kantor

5. Additional Features:
   
   A. modules/notification/
      - Real-time notification (ajax polling setiap 30 detik)
      - Notif untuk:
        * Request baru (ke koperasi)
        * Stok menipis (ke gudang)
        * Barang akan expired (ke gudang)
        * Piutang jatuh tempo (ke admin/koperasi)
        * Distribusi diterima (ke koperasi)
   
   B. modules/settings/
      - Pengaturan sistem
      - Format nomor dokumen
      - Logo perusahaan
      - Email settings (SMTP)
      - Backup database

REQUIREMENTS:
- Export Excel: PhpSpreadsheet dengan styling
- Export PDF: TCPDF atau mPDF
- Chart: Chart.js dengan multiple datasets
- Print: CSS @media print yang rapi
- Real-time notification dengan badge counter
- Responsive table untuk mobile
- Loading state untuk proses berat
- Cron job untuk reminder otomatis (optional)
- Activity log untuk audit trail

Buatkan kode lengkap PART 4 ini untuk melengkapi seluruh sistem Marketlist MBG.

CATATAN PENTING:
- Pastikan semua modul terintegrasi dengan baik
- Konsisten dengan database yang sudah dibuat
- Error handling dan validation di semua form
- Security: prepared statement, XSS prevention, CSRF token
- Clean code dengan komentar yang jelas
- User-friendly interface dengan UX yang baik
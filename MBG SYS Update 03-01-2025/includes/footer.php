</div>
    <!-- End Content Wrapper -->
    
    <footer class="mt-5 py-4">
        <div class="container-fluid">
            <div class="text-center text-muted small opacity-50">
                &copy; <?= date('Y') ?> MBG System &bull; Version 2.0 Premium
            </div>
        </div>
    </footer>
</div>
<!-- End Content -->

</div>
<!-- End Wrapper -->

<!-- jQuery 3.7 -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script>
    $(document).ready(function() {
        // Toggle Sidebar
        $('#sidebarCollapse, #sidebarOverlay').on('click', function() {
            $('#sidebar, #content, #sidebarOverlay').toggleClass('active');
        });

        // Close sidebar on mobile when window is resized to desktop
        $(window).on('resize', function() {
            if ($(window).width() > 991.98) {
                $('#sidebar, #content, #sidebarOverlay').removeClass('active');
            }
        });
        
        // Auto hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Confirmation dialogs
        $('.btn-delete').on('click', function(e) {
            if (!confirm('Yakin ingin menghapus data ini?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Format number input
        $('.format-number').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            $(this).val(value);
        });
        
        // Format rupiah input
        $('.format-rupiah').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            $(this).val(formatRupiah(value));
        });
    });
    
    // Format rupiah helper
    function formatRupiah(angka) {
        if (typeof angka === 'undefined' || angka === null || angka === '') return '';
        
        // If it's a string with a dot (decimal from DB), take only the integer part
        let val = angka.toString();
        if (val.includes('.') && !val.includes(',')) {
            val = val.split('.')[0];
        }
        
        let number_string = val.replace(/[^,\d]/g, ''),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        
        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        
        return rupiah;
    }
    
    // Print function
    function printContent(element) {
        let printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
        printWindow.document.write('</head><body>');
        printWindow.document.write(document.getElementById(element).innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
</script>

<!-- Toastify JS for Toast Notifications -->
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<?php if (isset($extra_js)): ?>
    <?= $extra_js ?>
<?php endif; ?>

</body>
</html>
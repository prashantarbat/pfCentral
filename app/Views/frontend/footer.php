

<!-- Main content -->
<section class="content">
<div class="container-fluid">

</div>
<!-- /.container-fluid -->
</section>
<!-- /.content -->
</div>
<!-- /.content-wrapper -->
<style>
.main-footer {
    min-height: 28px !important;
    padding: 4px 12px !important;
    font-size: 12px !important;
    line-height: 1.2 !important;
}
.main-footer p {
    margin: 0 !important;
}
.main-footer .float-right {
    line-height: 1.2 !important;
}
</style>
<footer class="main-footer">
    <div class="float-right d-none d-sm-block">
        <b>Version</b> <?= $app_version ?? '1.0.0' ?>
    </div>
    <p>&copy; <?= date("Y") ?> <?= $app_name ?? 'My Application' ?>
        <a href="<?= base_url('privacy') ?>">Privacy Policy</a> | 
        <a href="<?= base_url('terms') ?>">Terms of Service</a>
    </p>
</footer>

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
</aside>
<?= view('partials/command_palette') ?>

</div>
<!-- ./wrapper -->

<!-- Bootstrap 4 -->
<script src="<?= base_url('assets/template/') ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?= base_url('assets/template/') ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>

<script src="<?= base_url('assets/template/') ?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- AdminLTE App -->
<script src="<?= base_url('assets/template/') ?>dist/js/adminlte.min.js"></script>
<!-- jQuery Knob -->
<script src="<?= base_url('assets/template/') ?>plugins/jquery-knob/jquery.knob.min.js"></script>

<!-- Toastr -->
<script src="<?= base_url('assets/template/') ?>plugins/toastr/toastr.min.js"></script>

<!-- Page specific script -->
<script>
  // Global SweetAlert parameter helper available across all pages
  window.getSwalParams = window.getSwalParams || function() {
      const isDark = document.body.classList.contains('dark-mode');
      return {
          background: isDark ? '#12161c' : '#ffffff',
          color: isDark ? '#f8fafc' : '#0f172a'
      };
  };

  $(function () {
    // Initialize example1 DataTable safely if present and not already initialized
    if ($("#example1").length && !$.fn.DataTable.isDataTable('#example1')) {
      $("#example1").DataTable({
        paging: true,
        lengthChange: true,
        autoWidth: true,
        scrollX: true,
        lengthMenu: [[10, 25, 50, 100, 250, 500, 1000, -1], [10, 25, 50, 100, 250, 500, 1000, "All"]],
        buttons: [
          "copy", 
          "csv", 
          "excel", 
          {
            extend: "pdfHtml5",
            orientation: "landscape", // Set the orientation to landscape
            pageSize: "A4", // Set the page size to A4
            title: '#appname' // Optional: Set the title of the PDF
          },
          "print"
        ]
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    }

    // Initialize example2 DataTable safely if present and not already initialized
    if ($("#example2").length && !$.fn.DataTable.isDataTable('#example2')) {
      $('#example2').DataTable({
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        responsive: true,
        lengthMenu: [[10, 25, 50, 100, 250, 500, 1000, -1], [10, 25, 50, 100, 250, 500, 1000, "All"]],
      });
    }
  });
</script>
</body>
</html>

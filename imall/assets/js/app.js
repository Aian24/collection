$(document).ready(function () {
  var table = $('#example').DataTable({
      ordering: false,
      destroy: true,
      buttons: ['csv', 'excel', 'pdf', 'print'],
      responsive: true, // Enable responsive extension
      lengthMenu: [
          [10, 25, 50, 100, -1],
          [10, 25, 50, 100, "All"]
      ],
  });

  // Move the table buttons container
  table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');
});

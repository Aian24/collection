// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = 'Nunito', '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#858796';

// Function to fetch data from PHP file and update pie chart
function fetchPieChartData() {
  fetch('fetch_pie_data.php') // Update path to your PHP script
    .then(response => response.json())
    .then(data => {
      console.log('Fetched data:', data); // Debugging line

      // Update pie chart data
      myPieChart.data.datasets[0].data = [
        data.paid_rent,
        data.paid_bal
      ];

      // Update pie chart
      myPieChart.update();
    })
    .catch(error => console.error('Error fetching data:', error));
}

// Call fetchPieChartData function to initially load data
fetchPieChartData();

// Pie Chart Example
var ctx = document.getElementById("myPieChart");
var myPieChart = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ["Paid Rent", "Paid Balance"], // Updated labels
    datasets: [{
      data: [], // Will be updated dynamically
      backgroundColor: ['#1cc88a', '#36b9cc', '#f6c23e'], // Adjusted colors to match chart
      hoverBackgroundColor: ['#17a673', '#2c9faf', '#f0b500'], // Adjusted hover colors
      hoverBorderColor: "rgba(234, 236, 244, 1)",
    }],
  },
  options: {
    maintainAspectRatio: false,
    tooltips: {
      backgroundColor: "rgb(255,255,255)",
      bodyFontColor: "#858796",
      borderColor: '#dddfeb',
      borderWidth: 1,
      xPadding: 15,
      yPadding: 15,
      displayColors: false,
      caretPadding: 10,
    },
    legend: {
      display: true // Show legend to identify data slices
    },
    cutoutPercentage: 80,
  },
});

document.addEventListener('DOMContentLoaded', function () {
    // Get the select element
    var branchSelect = document.getElementById('branch');

    // Add an event listener to the select element
    branchSelect.addEventListener('change', function () {
        // Get the selected option value
        var selectedBranch = branchSelect.value;

        // Send an AJAX request to branch.php with the selected branch
        if (selectedBranch) {
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    // Redirect to branch.php with the selected branch as a parameter
                    window.location.href = 'branch.php?branch=' + selectedBranch;
                }
            };
            xhr.open('GET', 'branch.php?branch=' + selectedBranch, true);
            xhr.send();
        }
    });
});


// Function to show the loader
function showLoader() {
    jQuery(".page-loader").fadeIn("fast");
}

// Function to hide the loader
function hideLoader() {
    jQuery(".page-loader").fadeOut("slow");
}



// Function to display the loader based on internet speed
function checkInternetSpeed() {
    // Check if the Network Information API is supported
    if (navigator.connection && navigator.connection.effectiveType) {
        var connectionSpeed = navigator.connection.effectiveType;

        // Replace the effectiveType values and thresholds based on your requirements
        if (connectionSpeed === "4g" || connectionSpeed === "3g") {
            // If the effectiveType is 4g or 3g, hide the loader
            hideLoader();
        } else {
            // If the effectiveType is slower, keep the loader visible or handle it accordingly
            console.log("Slow internet speed detected");
        }
    } else {
        // Network Information API is not supported, hide the loader by default
        hideLoader();
    }
}

// Attach an event listener for the DOMContentLoaded event
document.addEventListener("DOMContentLoaded", function () {
    // You may choose to show the loader here as well, depending on your needs
    // showLoader();

    // Call the functions to show the loader based on internet speed and browser loading
    checkInternetSpeed();
    checkBrowserLoading();
});

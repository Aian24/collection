<?php
session_start();
@include 'navadmin.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner</title>
    <link rel="stylesheet" href="style.css">
  
</head>



<body>
    <div class="input-container">
    <input class="input"  type="text" id="barcodeInput" placeholder="Scan barcode..." oninput="autoRetrieveTransactionDetails()">
    <span class="icon"> 
        <svg width="19px" height="19px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path opacity="1" d="M14 5H20" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path opacity="1" d="M14 8H17" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M21 11.5C21 16.75 16.75 21 11.5 21C6.25 21 2 16.75 2 11.5C2 6.25 6.25 2 11.5 2" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path> <path opacity="1" d="M22 22L20 20" stroke="#000" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
      </span>
    </div>
    <div id="transactionDetails" style="display: block; margin: 0 auto;"></div>


    <script>
        // Function to handle automatic retrieval of transaction details
        function autoRetrieveTransactionDetails() {
            // Get the barcode value from the input field
            var barcodeValue = document.getElementById("barcodeInput").value;

            // Check if the barcode scanner is connected and a valid barcode is scanned
            if (barcodeValue.length > 0) {
                // Use AJAX to send the barcode value to the PHP script for processing
                var xhr = new XMLHttpRequest();
                xhr.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        // Display the transaction details in the designated div
                        document.getElementById("transactionDetails").innerHTML = this.responseText;
                    }
                };
                xhr.open("GET", "retrieve_detailsadmin.php?barcode=" + barcodeValue, true);
                xhr.send();
            }
        }

        // Add an event listener to detect when the barcode scanner is connected
        window.addEventListener('DOMContentLoaded', function() {
            window.addEventListener('input', function(e) {
                // Check if the input event is triggered by the barcodeInput field
                if (e.target.id === 'barcodeInput') {
                    // Barcode scanner is connected, trigger autoRetrieveTransactionDetails
                    autoRetrieveTransactionDetails();
                }
            });
        });
    </script>
</body>
</html>
<style>
    .input-container {
        width: 220px;
        position: relative;
        margin: 0 auto; 
        top:20px;
    }

    .icon {
        position: absolute;
        right: 10px;
        top: calc(50% + 5px);
        transform: translateY(calc(-50% - 5px));
    }

    .input {
        width: 100%;
        height: 40px;
        padding: 10px;
        margin-top: 20px;
        transition: .2s linear;
        border: 2.5px solid black;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 2px;
        box-sizing: border-box;
        display: block;
        margin: 0 auto; 
    }


.input:focus {
  outline: none;
  border: 0.5px solid black;
  box-shadow: -5px -5px 0px black;
}

.input-container:hover > .icon {
  animation: anim 1s linear infinite;
}

@keyframes anim {
  0%,
  100% {
    transform: translateY(calc(-50% - 5px)) scale(1);
  }

  50% {
    transform: translateY(calc(-50% - 5px)) scale(1.1);
  }
}
</style>
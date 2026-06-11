<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success</title>
    <style>
        .modal {
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .animate-check {
            animation: check 3s forwards;
        }

        .icon-container {
            display: flex;
            justify-content: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            animation: iconAnimation 1.3s ease-in-out forwards;
        }

        @keyframes iconAnimation {
            0% {
                transform: scale(0);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Insert your modified CSS here */

        .success-checkmark {
            width: 80px;
            height: 115px;
            margin: 0 auto;
        }

        .success-checkmark .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid red;
            /* Changed color to red */
        }

        .success-checkmark .check-icon::before,
        .success-checkmark .check-icon::after {
            content: '';
            height: 100px;
            position: absolute;
            background: #FFFFFF;
            transform: rotate(-45deg);
            border-radius: 0;
        }

        .success-checkmark .check-icon::before {
            top: 3px;
            left: -2px;
            width: 30px;
            transform-origin: 100% 50%;
            border-radius: 0;
            /* Changed border-radius */
        }

        .success-checkmark .check-icon::after {
            background: transparent;
            top: 0;
            left: 30px;
            width: 60px;
            transform-origin: 0 50%;
            border-radius: 0;
            /* Changed border-radius */
            animation: rotate-circle 4.25s ease-in;
        }

        .success-checkmark .check-icon .icon-line {
            height: 5px;
            background-color: red;
            /* Changed color to red */
            display: block;
            border-radius: 2px;
            position: absolute;
            z-index: 10;
        }

        .success-checkmark .check-icon .icon-line.line-tip {
            top: 40px;
            /* Adjusted position */
            left: 20px;
            /* Adjusted position */
            width: 40px;
            /* Adjusted width */
            transform: rotate(-45deg);
            /* Adjusted rotation */
            animation: icon-line-tip 0.75s;
        }

        .success-checkmark .check-icon .icon-line.line-long {
            top: 40px;
            /* Adjusted position */
            left: 20px;
            /* Adjusted position */
            width: 40px;
            /* Adjusted width */
            transform: rotate(45deg);
            /* Adjusted rotation */
            animation: icon-line-long 0.75s;
        }

        .success-checkmark .check-icon .icon-circle {
            display: none;
            /* Removed the circle */
        }

        .success-checkmark .check-icon .icon-fix {
            display: none;
            /* Removed the fix */
        }

        @keyframes rotate-circle {
            0% {
                transform: rotate(-45deg);
            }

            50% {
                transform: rotate(-405deg);
            }

            100% {
                transform: rotate(-405deg);
            }
        }


        @keyframes icon-line-tip {
            0% {
                width: 0;
                left: 1px;
                top: 19px;
            }

            54% {
                width: 0;
                left: 1px;
                top: 19px;
            }

            70% {
                width: 50px;
                left: -8px;
                top: 37px;
            }

            84% {
                width: 17px;
                left: 21px;
                top: 48px;
            }

            100% {
                width: 25px;
                left: 14px;
                top: 45px;
            }
        }

        @keyframes icon-line-long {
            0% {
                width: 0;
                right: 46px;
                top: 54px;
            }

            65% {
                width: 0;
                right: 46px;
                top: 54px;
            }

            84% {
                width: 55px;
                right: 0px;
                top: 35px;
            }

            100% {
                width: 47px;
                right: 8px;
                top: 38px;
            }
        }
    </style>
</head>

<body>
    <div class="modal" id="modal">
        <div class="modal-content animate-check">
            <div class="icon-container">
                <div class="success-checkmark">
                    <div class="check-icon">
                        <span class="icon-line line-tip"></span>
                        <span class="icon-line line-long"></span>
                    </div>
                </div>
            </div>
            <p>Deleted Success.</p>
        </div>
    </div>

    <script>
        setTimeout(function () {
            document.getElementById('modal').style.display = 'none';
        }, 2000);
    </script>

</body>

</html>
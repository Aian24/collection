
        // Function to open transactions modal
        function openModal() {
            const vtModal = document.getElementById('viewTransactionsModal');
            if (vtModal) {
                vtModal.classList.remove('hidden');
                fetchAndLoadTransactions();
            }
        }
        
        // Function to close modals
        function closeModal() {
            const vtModal = document.getElementById('viewTransactionsModal');
            if (vtModal) vtModal.classList.add('hidden');
            
            const dtModal = document.getElementById('duplicatedTransactionsModal');
            if (dtModal) dtModal.classList.add('hidden');
        }

        // Duplicate card builder
        function fmtDate(d) {
            const dt = new Date(d);
            if (isNaN(dt)) return d;
            return dt.toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})
                + ' ' + dt.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
        }
        function nFmt(v) {
            return parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
        }
        function getChargesTotal(chStr) {
            if (!chStr || chStr === 'null') return 0;
            let total = 0;
            const parts = chStr.split(',');
            for (let p of parts) {
                const match = p.match(/:\s*([\d,.]+)/);
                if (match) total += parseFloat(match[1].replace(/,/g, '')) || 0;
            }
            return total;
        }
        function buildDupCardUser(t) {
            const cTotal = getChargesTotal(t.charges);
            const total = parseFloat(t.paidrent||0) + parseFloat(t.paidbal||0) + cTotal;
            const charges = t.charges && t.charges !== 'null' ? t.charges : '';
            return `<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #fee2e2;border-left:4px solid #dc2626;margin-bottom:0;overflow:hidden">
                <div style="background:linear-gradient(135deg,#fff5f5,#fee2e2);padding:10px 14px 8px;border-bottom:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#dc2626">#${t.transaction_number}</div>
                        <div style="font-size:11px;color:#64748b">${fmtDate(t.collected_date)}</div>
                    </div>
                    <div style="font-size:14px;font-weight:800;color:#16a34a">&#x20B1;${nFmt(total)}</div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;padding:10px 14px 12px">
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Space</div><div style="font-size:13px;font-weight:600;color:#374151">${t.spacecode||'—'}</div></div>
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Code</div><div style="font-size:13px;font-weight:600;color:#374151">${t.tenantcode||'—'}</div></div>
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Paid Rent</div><div style="font-size:13px;font-weight:700;color:#16a34a">&#x20B1;${nFmt(t.paidrent)}</div></div>
                    ${charges ? `<div style="padding:4px;grid-column:1/-1"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Charges</div><div style="font-size:12px;color:#64748b">${charges}</div></div>` : ''}
                    <div style="padding:4px;grid-column:1/-1"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Tenant</div><div style="font-size:14px;font-weight:600;color:#1e293b">${t.tenantname||'—'}</div></div>
                </div>
            </div>`;
        }

        // Wire the Dups nav button
        document.addEventListener('DOMContentLoaded', function() {
            const dupBtn = document.getElementById('notificationButton');
            if (dupBtn) {
                dupBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modal = document.getElementById('duplicatedTransactionsModal');
                    const cont  = document.getElementById('dupCardsContainerUser');
                    modal.classList.remove('hidden');
                    cont.innerHTML = '<div style="text-align:center;padding:24px;color:#94a3b8"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
                    fetch('fetch_duplicated_transactions.php')
                        .then(r => r.json())
                        .then(data => {
                            cont.innerHTML = data.length
                                ? data.map(buildDupCardUser).join('')
                                : '<div style="text-align:center;padding:32px;color:#22c55e"><i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:8px"></i>No duplicates found!</div>';
                        })
                        .catch(() => { cont.innerHTML = '<div style="text-align:center;padding:24px;color:#ef4444">Error loading data</div>'; });
                });
            }
        });

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.remove('hidden');
        }
        function hideLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.add('hidden');
        }
        
        // (Legacy transaction table loader removed - table replaced with modern card modal)

        // Function to update the combined datetime field
        function updateDateTime() {
            const datePart = document.getElementById('collected_date_part').value;
            const timePart = document.getElementById('collected_time_part').value;
            
            if (datePart && timePart) {
                const combinedValue = `${datePart}T${timePart}`;
                document.getElementById('collected_date').value = combinedValue;
                
                // Re-fetch tenant details if spacecode is already filled
                var spacecode = document.getElementById("spacecode-input").value;
                if (spacecode && spacecode !== "") {
                    fetchTenantDetails(spacecode);
                }
            }
        }
        
        // Function to update time with seconds in real-time
        function updateTimeWithSeconds() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeValue = `${hours}:${minutes}:${seconds}`;
            document.getElementById('collected_time_part').value = timeValue;
            
            // Update the combined field
            updateDateTime();
        }
        
        // Set interval to update time every second
        let timeUpdateInterval;
        
        // Function to format numbers with commas
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // Function to calculate new running balance and new rent balance
        function calculateNewBalance() {
            // Get the values from input fields and default to 0 if empty
            var dailyRent = parseFloat(document.getElementById("rent").value.replace(/,/g, '')) || 0;
            var paidRent = parseFloat(document.getElementById("paidrent").value.replace(/,/g, '')) || 0;
            var paidBalance = parseFloat(document.getElementById("paidbal").value.replace(/,/g, '')) || 0;
            var rentBalance = parseFloat(document.getElementById("rentbal").value.replace(/,/g, '')) || 0;
            var runningBalance = parseFloat(document.getElementById("runningbal").value.replace(/,/g, '')) || 0;

            // Get the charges values from predefined inputs
            var chargecusa = parseFloat(document.getElementById("chargecusa").value.replace(/,/g, '')) || 0;
            var chargeac = parseFloat(document.getElementById("chargeac").value.replace(/,/g, '')) || 0;
            var chargeelec = parseFloat(document.getElementById("chargeelec").value.replace(/,/g, '')) || 0;
            var chargewater = parseFloat(document.getElementById("chargewater").value.replace(/,/g, '')) || 0;

            // Calculate total charges from predefined inputs
            var totalCharges = chargecusa + chargeac + chargeelec + chargewater;

            // Get the value from the predefined 'Others' inputs
            var otherAmount = parseFloat(document.getElementById("otheramount").value.replace(/,/g, '')) || 0;

            // Check if there is a valid other type selected and amount is greater than 0
            var otherType = document.getElementById("chargeothers").value;
            if (otherType && otherAmount > 0) {
                totalCharges += otherAmount;
            }

            // Get dynamically added charges
            var additionalCharges = document.querySelectorAll('#additionalChargesContainer input[type="text"]');
            additionalCharges.forEach(function (input) {
                var amount = parseFloat(input.value.replace(/,/g, '')) || 0;
                totalCharges += amount;
            });

            // Calculate total amount paid
            var totalAmountPaid = paidRent + paidBalance;
            
            // Calculate new balances according to business rules:
            // 1. Handle overpayment in rent balance first
            // 2. Apply payments to respective balances independently
            // 3. Preserve negative balances as overpayments
            
            // Calculate new rent balance
            // When rentBalance is negative (overpayment), applying a payment should reduce the overpayment
            // The overpayment amount is (paidRent - dailyRent) when paidRent > dailyRent
            // New rent balance = Current rent balance - (Paid Rent - Daily Rent)
            var overpayment = paidRent - dailyRent;
            var newRentBalance = rentBalance - overpayment;
            
            // Calculate new arrear balance
            // New arrear balance = Current arrear balance - Amount paid toward arrear
            var newRunningBalance = runningBalance - paidBalance;

            // Calculate total payment (amounts paid + charges)
            var total = totalAmountPaid + totalCharges;

            // Format the values with commas for display (keep exact decimal precision)
            var formattedNewRentBalance = parseFloat(newRentBalance.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            var formattedNewRunningBalance = parseFloat(newRunningBalance.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            var formattedTotal = parseFloat(total.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Update both the display elements and hidden input fields
            document.getElementById("newrentbalance").value = formattedNewRentBalance;
            document.getElementById("newbalance").value = formattedNewRunningBalance;
            document.getElementById("total").value = formattedTotal;
            
            // Update the display spans
            document.getElementById("newrentbalance-display").textContent = formattedNewRentBalance;
            document.getElementById("newbalance-display").textContent = formattedNewRunningBalance;
            document.getElementById("total-display").textContent = formattedTotal;
        }

        // Function to format numbers with commas and trigger calculation
        function formatNumberAndCalculateNewBalance(input) {
            // Remove non-numeric characters and commas
            let cleanValue = input.value.replace(/[^\d.]/g, '');

            // Format the number with commas
            let formattedValue = cleanValue.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Set the formatted value back to the input
            input.value = formattedValue;

            // Now you can use this value for further calculations
            calculateNewBalance();
        }

        // For add button other charges
        document.getElementById('addChargeButton').addEventListener('click', function () {
            var container = document.getElementById('additionalChargesContainer');

            // Create a new entry with dropdown and amount input
            var newEntry = document.createElement('div');
            newEntry.classList.add('grid', 'grid-cols-2', 'gap-4', 'items-end', 'mb-2');
            newEntry.innerHTML = `
                <div class="relative custom-select">
                    <input type="text" class="pos-input w-full cursor-pointer bg-white border-blue-200 focus:border-blue-500" placeholder="Select a type" readonly>
                    <input type="hidden" name="chargeothers[]">
                    <div class="absolute z-50 w-full bg-white border border-blue-200 rounded-lg shadow-xl mt-1 hidden dropdown-menu flex-col overflow-hidden">
                        <div class="p-2 border-b border-blue-100 bg-blue-50">
                            <input type="text" class="w-full bg-white border border-blue-200 rounded px-2 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400" placeholder="Search charges...">
                        </div>
                        <div class="max-h-48 overflow-y-auto dropdown-options"></div>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative flex-1">
            <input oninput="formatNumberAndCalculateNewBalance(this)" 
                            class="pos-input w-full"
                            type="text" name="otheramount[]" placeholder="0.00" inputmode="decimal">
                    </div>
                    <button type="button" class="remove-charge ml-2 bg-red-50 hover:bg-red-100 text-red-500 rounded-full p-2.5 transition-colors">
                        <i class="fas fa-trash-alt"></i>
                    </button>
        </div>
    `;

            // Add event listener for remove button
            newEntry.querySelector('.remove-charge').addEventListener('click', function() {
                container.removeChild(newEntry);
                calculateNewBalance(); // Recalculate after removing
            });

            container.appendChild(newEntry);
            
            // Initialize custom select logic for the new entry
            initCustomSelect(newEntry.querySelector('.custom-select'));
        });

        // For Modal Payment Summary
        // Function to format the numbers as currency
        function formatCurrency(value) {
            return '&#x20B1;' + numberWithCommas(parseFloat(value).toFixed(2));
        }

        // Function to gather the values and show the modal
        function showConfirmationModal(event) {
            event.preventDefault(); // Prevent the form from submitting immediately

            // Retrieve the form values
            const rent = parseFloat(document.getElementById('rent').value.replace(',', '')) || 0;
            const rentbal = parseFloat(document.getElementById('rentbal').value.replace(',', '')) || 0;
            const runningbal = parseFloat(document.getElementById('runningbal').value.replace(',', '')) || 0;
            const paidrent = parseFloat(document.getElementById('paidrent').value.replace(',', '')) || 0;
            const paidbal = parseFloat(document.getElementById('paidbal').value.replace(',', '')) || 0;
            const total = parseFloat(document.getElementById('total').value.replace(',', '')) || 0;
            const newbalance = parseFloat(document.getElementById('newbalance').value.replace(',', '')) || 0;
            const newrentbalance = parseFloat(document.getElementById('newrentbalance').value.replace(',', '')) || 0;

            // Get all charges (Cusa, Aircon, Electricity, Water, Other charges from dropdown)
            const charges = [];
            
            const chargeNames = [
                { label: 'Cusa', id: 'chargecusa' },
                { label: 'Aircon', id: 'chargeac' },
                { label: 'Electricity', id: 'chargeelec' },
                { label: 'Water', id: 'chargewater' }
            ];
            
            chargeNames.forEach(charge => {
                const chargeAmount = parseFloat(document.getElementById(charge.id).value.replace(',', '')) || 0;
                if (chargeAmount > 0) {
                    charges.push({ name: charge.label, amount: chargeAmount });
                }
            });

            // Handle dynamic "Other Charges" from dropdowns (both static and dynamically added)
            const otherSelects = document.querySelectorAll('select[name="chargeothers[]"]');
            const otherAmounts = document.querySelectorAll('input[name="otheramount[]"]');
            
            for (let i = 0; i < otherSelects.length; i++) {
                const selectedChargeType = otherSelects[i].value;
                const otherAmount = parseFloat(otherAmounts[i].value.replace(/,/g, '')) || 0;
                if (selectedChargeType && otherAmount > 0) {
                    charges.push({ name: selectedChargeType, amount: otherAmount });
                }
            }

            // Generate the charges summary HTML
            let chargesHtml = '';
            charges.forEach(charge => {
                chargesHtml += `<p><strong>${charge.name}:</strong> ${formatCurrency(charge.amount)}</p>`;
            });

            // Update the Amount Paid (Arrear Balance) to include the charges as well
            const totalPaidArrearBalance = paidbal + charges.reduce((acc, charge) => acc + charge.amount, 0);

            // Display the payment summary in the modal
            const summaryHtml = `
                <p><strong>Amount Paid (Daily Rent):</strong> ${formatCurrency(paidrent)}</p>
                <p><strong>Amount Paid (Arrear Balance):</strong> ${formatCurrency(paidbal)}</p>
                ${chargesHtml}  <!-- Display charges here -->
                <p><strong>Total Paid (including Charges):</strong> ${formatCurrency(total)}</p>
            `;
            document.getElementById('confirmationSummary').innerHTML = summaryHtml;

            // Show the confirmation modal
            document.getElementById('confirmationModal').classList.remove('hidden');
        }

        // Event listener for the confirm button
        const confBtn = document.getElementById('confirmButton');
        if (confBtn) {
            confBtn.addEventListener('click', function() {
                // Close the modal
                document.getElementById('confirmationModal').classList.add('hidden');

                // Submit the form programmatically
                document.getElementById('collectionForm').submit();
            });
        }

        // Event listener for the cancel button
        const canBtn = document.getElementById('cancelButton');
        if (canBtn) {
            canBtn.addEventListener('click', function() {
                window.location.href = "user.php";
            });
        }

        // Event listener for the form submit button
        const colForm = document.getElementById('collectionForm');
        if (colForm) {
            colForm.addEventListener('submit', showConfirmationModal);
        }

        // Show welcome modal on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if the welcome modal should be displayed
            var welcomeModalClosed = localStorage.getItem("welcomeModalClosed");
            if (!welcomeModalClosed) {
                document.getElementById("welcomeModal").classList.remove("hidden");
                setTimeout(function() {
                    document.getElementById("welcomeModal").classList.add("hidden");
                    localStorage.setItem("welcomeModalClosed", "true");
                }, 2000);
            }
            
            // Initialize date/time inputs
            const datePart = document.getElementById('collected_date_part');
            const timePart = document.getElementById('collected_time_part');
            
            // Initialize with default values if not already set
            if (!datePart.value) {
                datePart.value = new Date().toISOString().split('T')[0];
            }
            
            // Start the real-time clock update
            updateTimeWithSeconds(); // Update immediately
            timeUpdateInterval = setInterval(updateTimeWithSeconds, 1000); // Update every second
            
            // Set the hidden combined field
            updateDateTime();
            
            // Initial manual calc
            calculateNewBalance();
            
            // Handle pre-fill from monitoring page
            const prefill = localStorage.getItem('prefill_spacecode');
            if (prefill) {
                document.getElementById('spacecode-input').value = prefill;
                fetchTenantDetails(prefill);
                localStorage.removeItem('prefill_spacecode');
            }
        });

        // â”€â”€ RIGHT-SIDE DRAWER â”€â”€
        document.getElementById('burger-menu-btn').addEventListener('click', function() {
            const sideNav = document.getElementById('side-nav');
            sideNav.classList.remove('hidden');
            setTimeout(function() {
                const sideMenu = sideNav.querySelector('.side-menu');
                sideMenu.classList.add('translate-x-0');
                sideMenu.classList.remove('translate-x-full');
            }, 20);
        });

        document.getElementById('close-btn').addEventListener('click', closeMenu);
        document.getElementById('side-nav-backdrop').addEventListener('click', closeMenu);

        function closeMenu() {
            const sideNav = document.getElementById('side-nav');
            const sideMenu = sideNav.querySelector('.side-menu');
            sideMenu.classList.add('translate-x-full');
            sideMenu.classList.remove('translate-x-0');
            setTimeout(function() { sideNav.classList.add('hidden'); }, 300);
        }

        // â”€â”€ BADGE FOR DUPLICATES â”€â”€
        function checkDuplicateBadge() {
            fetch('fetch_duplicated_transactions.php')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notificationBadge');
                    if (data.length > 0) {
                        badge.textContent = data.length;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }).catch(() => {});
        }
        // Monitoring stats updates take care of the badge now
        // checkDuplicateBadge();

        // Inside the event listener for the notification button
        document.getElementById('notificationButton').addEventListener('click', function (event) {
            event.preventDefault(); // Prevent the default form submission
            
            // Show loading indicator
            document.getElementById('duplicatedTransactionsTableBody').innerHTML = 
                '<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading data...</td></tr>';
            
            // Show the modal first
            document.getElementById('duplicatedTransactionsModal').classList.remove('hidden');
            
            fetch('fetch_duplicated_transactions.php')
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('duplicatedTransactionsTableBody');
                    tableBody.innerHTML = ''; // Clear existing rows

                    if (data.length > 0) {
                        data.forEach(transaction => {
                            const charges = parseCharges(transaction.charges); // Parse charges from string

                            // Create a row with hover effect
                            const row = document.createElement('tr');
                            row.className = 'hover:bg-red-50 transition-colors';
                            
                            // Format the date
                            const date = new Date(transaction.collected_date);
                            const formattedDate = date.toLocaleString('en-US', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            // Create the table cells
                            row.innerHTML = `
                                <td class="px-4 py-3 whitespace-nowrap">${formattedDate}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${transaction.transaction_number}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${transaction.spacecode}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${transaction.tenantcode}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(transaction.paidrent).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(transaction.paidbal).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.aircon || 0).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.cusa || 0).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.electricity || 0).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.water || 0).toFixed(2))}</td>
                            `;
                            
                            tableBody.appendChild(row);
                        });

                        // Initialize DataTable with better styling
                        try {
                            if ($.fn.DataTable.isDataTable('#duplicatedTransactionsTable')) {
                                $('#duplicatedTransactionsTable').DataTable().destroy();
                            }
                            
                            $('#duplicatedTransactionsTable').DataTable({
                                responsive: true,
                                paging: true,
                                searching: true,
                                dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
                                language: {
                                    search: "<i class='fas fa-search mr-2'></i>",
                                    lengthMenu: "<i class='fas fa-list mr-2'></i> _MENU_ rows"
                                }
                            });
                        } catch (e) {
                            console.error("Error initializing DataTable:", e);
                        }
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-500">No duplicated transactions found</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching duplicated transactions:', error);
                    document.getElementById('duplicatedTransactionsTableBody').innerHTML = 
                        '<tr><td colspan="10" class="text-center py-4 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> Error loading duplicated transactions</td></tr>';
                });
        });

        // Function to parse charges from the string
        function parseCharges(chargesString) {
            if (!chargesString) return {};
            
            const charges = {
                aircon: 0,
                cusa: 0,
                electricity: 0,
                water: 0,
                table_tennis: 0,
                pay_toilet: 0,
                pay_parking: 0,
                ice_water: 0,
                ulam_vendor: 0,
                gas: 0,
                famylihan: 0,
                garbage_haul: 0,
                photocopy: 0,
                tenant_id: 0,
                function_room: 0,
                tables_chairs: 0,
                overnight_works: 0,
                vendo_sale: 0,
                zumba: 0,
                secdep: 0,
                meterdep: 0,
                utilitydep: 0,
                miscellaneous: 0
            };

            try {
                const chargeArray = chargesString.split(', ');
                chargeArray.forEach(charge => {
                    const match = charge.match(/([^:]+):\s*(\d+\.?\d*)?/);
                    if (match) {
                        // Convert the charge type to a normalized key
                        let key = match[1].trim().toLowerCase()
                            .replace(/\s+/g, '_')       // Replace spaces with underscore
                            .replace(/[&']/g, '')       // Remove special chars
                            .replace(/ice_&_water/i, 'ice_water');  // Special case
                        
                        charges[key] = parseFloat(match[2]) || 0;
                    }
                });
            } catch (e) {
                console.error("Error parsing charges:", e);
            }

            return charges;
        }

        // Suggest Space Code and Auto Complete
        function suggestSpaceCode(value) {
            var branch = document.getElementById("branch").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "suggest_spacecode.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var suggestions = JSON.parse(xhr.responseText);
                    var suggestionsContainer = document.getElementById("spacecode-suggestions");
                    var errorSpan = document.getElementById("spacecode-error");
                    suggestionsContainer.innerHTML = "";
                    
                    if (suggestions.length > 0) {
                        suggestions.forEach(function(suggestion) {
                            var option = document.createElement("div");
                            option.classList.add("cursor-pointer", "py-2", "px-4", "hover:bg-blue-50");
                            option.textContent = suggestion;
                            option.onclick = function() {
                                document.getElementById("spacecode-input").value = this.textContent;
                                suggestionsContainer.classList.add("hidden");
                                fetchTenantDetails(this.textContent);
                            };
                            suggestionsContainer.appendChild(option);
                        });
                        
                        suggestionsContainer.classList.remove("hidden");
                        errorSpan.textContent = "";
                    } else {
                        suggestionsContainer.classList.add("hidden");
                        errorSpan.textContent = "No suggestions found";
                    }

                    // Check if typed value matches any suggestion
                    if (suggestions.includes(value)) {
                        fetchTenantDetails(value);
                    } else {
                        clearTenantDetails();
                    }
                }
            };
            xhr.send("search=" + encodeURIComponent(value) + "&branch=" + encodeURIComponent(branch));
        }

        // Close suggestions when clicking outside
        document.addEventListener("click", function(event) {
            var suggestionsContainer = document.getElementById("spacecode-suggestions");
            if (event.target !== suggestionsContainer && !suggestionsContainer.contains(event.target)) {
                suggestionsContainer.classList.add("hidden");
            }
        });

        // Function to fetch tenant details
        function fetchTenantDetails(spacecode) {
            var branch = document.getElementById("branch").value;
            var selectedDate = document.getElementById("collected_date_part").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "get_tenant_details.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        document.getElementById("tenantcode").value = response.tenantcode;
                        document.getElementById("tenantname").value = response.tenantname;
                        document.getElementById("rent").value = response.dailyRent;
                        document.getElementById("rentbal").value = response.rentbal;
                        document.getElementById("runningbal").value = response.runningbal;

                        // Set readonly attribute based on editable flag
                        var isAmbulant = response.editable;
                        document.getElementById("tenantcode").readOnly = !isAmbulant;
                        document.getElementById("tenantname").readOnly = !isAmbulant;
                        document.getElementById("rent").readOnly = !isAmbulant;
                        document.getElementById("rentbal").readOnly = !isAmbulant;
                        document.getElementById("runningbal").readOnly = !isAmbulant;
                        
                        // Update payment status reminder
                        var statusContainer = document.getElementById("payment-status-container");
                        var statusText = document.getElementById("payment-status-text");
                        var statusIcon = document.getElementById("payment-status-icon");
                        
                        var todayStr = new Date().toISOString().split('T')[0];
                        var dateLabel = selectedDate === todayStr ? "TODAY" : selectedDate;
                        
                        statusContainer.classList.remove("hidden");
                        if (response.paidToday) {
                            statusContainer.className = "status-bubble status-paid";
                            statusText.textContent = "ALREADY PAID ON " + dateLabel;
                            statusIcon.className = "fas fa-check-circle text-[10px]";
                            document.getElementById("submitButton").disabled = false;
                        } else {
                            statusContainer.className = "status-bubble status-unpaid";
                            statusText.textContent = "NOT YET PAID ON " + dateLabel;
                            statusIcon.className = "fas fa-exclamation-circle text-[10px]";
                        }
                        
                        // Calculate the balance immediately
                        calculateNewBalance();
                    } else {
                        clearTenantDetails();
                    }
                }
            };
            xhr.send("spacecode=" + encodeURIComponent(spacecode) + "&branch=" + encodeURIComponent(branch) + "&date=" + encodeURIComponent(selectedDate));
        }

        // Function to clear tenant details
        function clearTenantDetails() {
            document.getElementById("tenantcode").value = "";
            document.getElementById("tenantname").value = "";
            document.getElementById("rent").value = "";
            document.getElementById("rentbal").value = "";
            document.getElementById("runningbal").value = "";
            
            document.getElementById("tenantcode").readOnly = false;
            document.getElementById("tenantname").readOnly = false;
            document.getElementById("rent").readOnly = false;
            document.getElementById("rentbal").readOnly = false;
            document.getElementById("runningbal").readOnly = false;
            
            // Reset the balance displays
            document.getElementById("total-display").textContent = "0.00";
            document.getElementById("newbalance-display").textContent = "0.00";
            document.getElementById("newrentbalance-display").textContent = "0.00";
            
            // Hide and reset status container
            var statusContainer = document.getElementById("payment-status-container");
            if (statusContainer) statusContainer.classList.add("hidden");
        }

        // Listen for input on space code field
        document.getElementById("spacecode-input").addEventListener("input", function(event) {
            var value = event.target.value.trim();
            if (value === "") {
                clearTenantDetails();
            } else {
                suggestSpaceCode(value);
            }
        });

        // Custom Select Logic for Charges
        const CHARGE_OPTIONS = [
            "Table Tennis", "Pay Toilet", "Pay Parking", "Ice & Water", "Ulam Vendor",
            "Gas", "Famylihan", "Garbage Haul", "Photocopy", "Tenant ID",
            "Function Room", "Tables & Chairs", "Overnight Works", "Vendo Sale",
            "Zumba", "Sec Dep", "Meter Dep", "Utility Dep", "Miscellaneous", "Forfeited Items"
        ];

        function initCustomSelect(wrapper) {
            if (!wrapper) return;
            const input = wrapper.querySelector('input[type="text"][readonly]');
            const hidden = wrapper.querySelector('input[type="hidden"]');
            const menu = wrapper.querySelector('.dropdown-menu');
            const search = wrapper.querySelector('.dropdown-menu input');
            const optionsContainer = wrapper.querySelector('.dropdown-options');

            function renderOptions(filter = '') {
                optionsContainer.innerHTML = '';
                const filtered = CHARGE_OPTIONS.filter(opt => opt.toLowerCase().includes(filter.toLowerCase()));
                filtered.forEach(opt => {
                    const div = document.createElement('div');
                    div.className = 'px-3 py-2 cursor-pointer hover:bg-blue-50 text-gray-700 text-sm border-b border-gray-50 last:border-0 transition-colors';
                    div.textContent = opt;
                    div.onclick = () => {
                        input.value = opt;
                        hidden.value = opt;
                        menu.classList.add('hidden');
                    };
                    optionsContainer.appendChild(div);
                });
                if (filtered.length === 0) {
                    optionsContainer.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">No results found</div>';
                }
            }

            input.onclick = (e) => {
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu').forEach(m => {
                    if (m !== menu) m.classList.add('hidden');
                });
                menu.classList.toggle('hidden');
                if (!menu.classList.contains('hidden')) {
                    search.value = '';
                    renderOptions();
                    search.focus();
                }
            };

            search.oninput = (e) => renderOptions(e.target.value);
            search.onclick = (e) => e.stopPropagation();
            menu.onclick = (e) => e.stopPropagation();
            
            renderOptions();
        }

        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        });
        
        // Initialize existing
        document.addEventListener('DOMContentLoaded', () => {
            initCustomSelect(document.getElementById('initialChargeSelect'));
        });
    
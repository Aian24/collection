/**
 * LC Lopez Collection - Android App Print Integration
 * 
 * This JavaScript code enables printing in the Android WebView app
 * Add this to your print pages (print.php, printsummary.php, etc.)
 */

// Detect if running inside the Android app
function isAndroidApp() {
    return typeof Android !== 'undefined';
}

// Get user agent info
function isSunmiDevice() {
    const userAgent = navigator.userAgent.toLowerCase();
    return userAgent.includes('sunmi') || userAgent.includes('lclopezapp');
}

// Enhanced window.print() for Android app
if (isAndroidApp()) {
    console.log('Running in LC Lopez Collection Android App');
    
    // Override the default window.print() function
    const originalPrint = window.print;
    window.print = function() {
        try {
            // Try Android app printing first
            if (typeof Android !== 'undefined' && typeof Android.print === 'function') {
                console.log('Triggering Android app print');
                Android.print();
                return;
            }
        } catch (e) {
            console.error('Android print failed:', e);
        }
        
        // Fallback to standard print
        originalPrint.call(window);
    };
    
    // Handle Sunmi printer extension URLs
    const originalOpen = window.open;
    window.open = function(url, target, features) {
        // Check if it's a Sunmi printer extension URL
        if (url && (url.includes('bluetoothprint.scheme') || url.includes('sunmiprinter'))) {
            console.log('Sunmi printer extension detected, using app print');
            if (typeof Android !== 'undefined' && typeof Android.print === 'function') {
                Android.print();
                return null;
            }
        }
        
        // Check for custom schemes that might be printer related
        if (url && (url.startsWith('http://my.') || url.startsWith('bluetoothprint://'))) {
            console.log('Custom printer scheme detected, using app print');
            if (typeof Android !== 'undefined' && typeof Android.print === 'function') {
                Android.print();
                return null;
            }
        }
        
        // For other URLs, use original function
        return originalOpen.call(window, url, target, features);
    };
    
    // Override location.href for Sunmi printer schemes
    const originalLocationHrefSetter = Object.getOwnPropertyDescriptor(Location.prototype, 'href').set;
    Object.defineProperty(location, 'href', {
        set: function(url) {
            if (url && (url.includes('bluetoothprint.scheme') || url.includes('sunmiprinter'))) {
                console.log('Sunmi printer scheme detected via location.href, using app print');
                if (typeof Android !== 'undefined' && typeof Android.print === 'function') {
                    Android.print();
                    return;
                }
            }
            originalLocationHrefSetter.call(this, url);
        },
        get: Object.getOwnPropertyDescriptor(Location.prototype, 'href').get
    });
    
    // Add a method for Sunmi-specific printing
    window.printSunmi = function(content) {
        try {
            if (typeof Android !== 'undefined' && typeof Android.printSunmi === 'function') {
                Android.printSunmi(content || document.body.innerHTML);
            } else {
                window.print();
            }
        } catch (e) {
            console.error('Sunmi print failed:', e);
            window.print();
        }
    };
    
    // Show toast notification (useful for debugging)
    window.showToast = function(message) {
        try {
            if (typeof Android !== 'undefined' && typeof Android.showToast === 'function') {
                Android.showToast(message);
            }
        } catch (e) {
            console.error('Toast failed:', e);
        }
    };
    
    // Add indicator that app print is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('App print integration ready');
        
        // Optional: Add a visual indicator
        if (isSunmiDevice()) {
            console.log('Sunmi device detected');
        }
    });
}

// Auto-print when page loads (for receipt printing)
window.addEventListener('load', function() {
    if (isAndroidApp()) {
        console.log('Auto-print enabled for Android app');
        // Delay to ensure page is fully rendered
        setTimeout(function() {
            console.log('Looking for print button to auto-click...');
            
            // Try to find and click the print button automatically
            const printButton = document.querySelector('.print-button a');
            if (printButton) {
                console.log('Print button found! Auto-clicking...');
                printButton.click();
            } else {
                // Fallback to window.print if button not found
                console.log('Print button not found, using window.print()');
                window.print();
            }
        }, 1000); // 1 second delay to ensure page is fully loaded
    }
});


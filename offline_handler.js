/**
 * Offline Transaction Handler
 * Handles offline transaction storage and syncing using IndexedDB
 */

const OfflineHandler = (function() {
    'use strict';

    // IndexedDB configuration
    const DB_NAME = 'CollectionAppDB';
    const DB_VERSION = 1;
    const STORE_NAME = 'offlineTransactions';
    let db = null;

    // Online/Offline state
    let isOnline = navigator.onLine;

    /**
     * Initialize IndexedDB
     */
    function initDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => {
                console.error('IndexedDB error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                db = request.result;
                console.log('IndexedDB initialized successfully');
                resolve(db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create object store if it doesn't exist
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const objectStore = db.createObjectStore(STORE_NAME, { 
                        keyPath: 'localId', 
                        autoIncrement: true 
                    });
                    
                    // Create indexes for easier querying
                    objectStore.createIndex('timestamp', 'timestamp', { unique: false });
                    objectStore.createIndex('branch', 'branch', { unique: false });
                    objectStore.createIndex('synced', 'synced', { unique: false });
                    
                    console.log('Object store created successfully');
                }
            };
        });
    }

    /**
     * Save transaction to IndexedDB
     */
    function saveOfflineTransaction(transactionData) {
        return new Promise((resolve, reject) => {
            if (!db) {
                reject(new Error('Database not initialized'));
                return;
            }

            // Add metadata
            transactionData.timestamp = new Date().toISOString();
            transactionData.synced = false;

            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const objectStore = transaction.objectStore(STORE_NAME);
            const request = objectStore.add(transactionData);

            request.onsuccess = () => {
                console.log('Transaction saved offline:', request.result);
                resolve(request.result);
                updateOfflineIndicator();
            };

            request.onerror = () => {
                console.error('Error saving transaction:', request.error);
                reject(request.error);
            };
        });
    }

    /**
     * Get all unsynced transactions
     */
    function getUnsyncedTransactions() {
        return new Promise((resolve, reject) => {
            if (!db) {
                reject(new Error('Database not initialized'));
                return;
            }

            const transaction = db.transaction([STORE_NAME], 'readonly');
            const objectStore = transaction.objectStore(STORE_NAME);
            const index = objectStore.index('synced');
            const request = index.getAll(false); // Get all unsynced (false) transactions

            request.onsuccess = () => {
                resolve(request.result);
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Get count of unsynced transactions
     */
    function getUnsyncedCount() {
        return new Promise((resolve, reject) => {
            if (!db) {
                reject(new Error('Database not initialized'));
                return;
            }

            const transaction = db.transaction([STORE_NAME], 'readonly');
            const objectStore = transaction.objectStore(STORE_NAME);
            const index = objectStore.index('synced');
            const request = index.count(false);

            request.onsuccess = () => {
                resolve(request.result);
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Mark transactions as synced
     */
    function markAsSynced(localIds) {
        return new Promise((resolve, reject) => {
            if (!db) {
                reject(new Error('Database not initialized'));
                return;
            }

            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const objectStore = transaction.objectStore(STORE_NAME);
            let updated = 0;

            localIds.forEach(localId => {
                const request = objectStore.get(localId);
                
                request.onsuccess = () => {
                    const data = request.result;
                    if (data) {
                        data.synced = true;
                        data.syncedAt = new Date().toISOString();
                        const updateRequest = objectStore.put(data);
                        updateRequest.onsuccess = () => updated++;
                    }
                };
            });

            transaction.oncomplete = () => {
                console.log(`Marked ${updated} transactions as synced`);
                resolve(updated);
                updateOfflineIndicator();
            };

            transaction.onerror = () => {
                reject(transaction.error);
            };
        });
    }

    /**
     * Delete synced transactions (cleanup)
     */
    function deleteSyncedTransactions() {
        return new Promise((resolve, reject) => {
            if (!db) {
                reject(new Error('Database not initialized'));
                return;
            }

            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const objectStore = transaction.objectStore(STORE_NAME);
            const index = objectStore.index('synced');
            const request = index.openCursor(true); // Get synced transactions

            let deleted = 0;

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    cursor.delete();
                    deleted++;
                    cursor.continue();
                }
            };

            transaction.oncomplete = () => {
                console.log(`Deleted ${deleted} synced transactions`);
                resolve(deleted);
            };

            transaction.onerror = () => {
                reject(transaction.error);
            };
        });
    }

    /**
     * Sync offline transactions to server
     */
    async function syncTransactions() {
        try {
            // Check if online
            if (!navigator.onLine) {
                throw new Error('You are currently offline. Please check your internet connection.');
            }

            // Show syncing indicator
            showSyncingIndicator(true);

            // Get unsynced transactions
            const unsyncedTransactions = await getUnsyncedTransactions();

            if (unsyncedTransactions.length === 0) {
                showSyncMessage('No offline transactions to sync.', 'info');
                showSyncingIndicator(false);
                return { status: 'info', message: 'No offline transactions to sync.' };
            }

            // Prepare transactions for server
            const transactionsToSync = unsyncedTransactions.map(t => {
                // Remove IndexedDB metadata
                const { localId, timestamp, synced, syncedAt, ...transaction } = t;
                transaction.localId = localId; // Keep localId for reference
                return transaction;
            });

            // Send to server
            const formData = new FormData();
            formData.append('action', 'sync_offline_transactions');
            formData.append('transactions', JSON.stringify(transactionsToSync));

            const response = await fetch('offline_sync.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }

            const result = await response.json();

            if (result.status === 'success' && result.synced_count > 0) {
                // Mark synced transactions
                if (result.synced_ids && result.synced_ids.length > 0) {
                    await markAsSynced(result.synced_ids);
                }

                // Clean up synced transactions after a delay
                setTimeout(() => {
                    deleteSyncedTransactions();
                }, 5000);

                showSyncMessage(
                    `✓ Successfully synced ${result.synced_count} transaction(s)!` +
                    (result.failed_count > 0 ? ` (${result.failed_count} failed)` : ''),
                    'success'
                );

                return result;
            } else {
                throw new Error(result.message || 'Failed to sync transactions');
            }

        } catch (error) {
            console.error('Sync error:', error);
            showSyncMessage(`Sync failed: ${error.message}`, 'error');
            return { status: 'error', message: error.message };
        } finally {
            showSyncingIndicator(false);
        }
    }

    /**
     * Update offline/online indicator in UI
     */
    function updateOfflineIndicator() {
        getUnsyncedCount().then(count => {
            const indicator = document.getElementById('offline-indicator');
            const syncButton = document.getElementById('sync-button');
            const badge = document.getElementById('offline-count-badge');

            if (indicator) {
                if (!isOnline) {
                    indicator.classList.remove('hidden');
                    indicator.querySelector('.status-text').textContent = 'Offline Mode';
                    indicator.classList.add('bg-yellow-500');
                    indicator.classList.remove('bg-green-500');
                } else if (count > 0) {
                    indicator.classList.remove('hidden');
                    indicator.querySelector('.status-text').textContent = `${count} Pending`;
                    indicator.classList.add('bg-blue-500');
                    indicator.classList.remove('bg-yellow-500', 'bg-green-500');
                } else {
                    indicator.classList.remove('hidden');
                    indicator.querySelector('.status-text').textContent = 'Online';
                    indicator.classList.add('bg-green-500');
                    indicator.classList.remove('bg-yellow-500', 'bg-blue-500');
                    
                    // Hide after 3 seconds if online and no pending
                    setTimeout(() => {
                        if (isOnline && count === 0) {
                            indicator.classList.add('hidden');
                        }
                    }, 3000);
                }
            }

            if (syncButton) {
                if (count > 0 && isOnline) {
                    syncButton.classList.remove('hidden');
                } else {
                    syncButton.classList.add('hidden');
                }
            }

            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }).catch(err => {
            console.error('Error updating offline indicator:', err);
        });
    }

    /**
     * Show syncing indicator
     */
    function showSyncingIndicator(show) {
        const button = document.getElementById('sync-button');
        if (button) {
            const icon = button.querySelector('i');
            const text = button.querySelector('.sync-text');
            
            if (show) {
                button.disabled = true;
                button.classList.add('opacity-50', 'cursor-not-allowed');
                if (icon) {
                    icon.className = 'fas fa-spinner fa-spin mr-2';
                }
                if (text) {
                    text.textContent = 'Syncing...';
                }
            } else {
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                if (icon) {
                    icon.className = 'fas fa-sync-alt mr-2';
                }
                if (text) {
                    text.textContent = 'Sync Now';
                }
            }
        }
    }

    /**
     * Show sync message modal or toast
     */
    function showSyncMessage(message, type = 'success') {
        // Try to use existing modal system if available
        if (typeof showModal === 'function') {
            const title = type === 'success' ? 'Sync Complete' : 
                         type === 'error' ? 'Sync Failed' : 'Sync Info';
            showModal(title, message, type);
        } else {
            // Fallback to alert
            alert(message);
        }
    }

    /**
     * Setup online/offline event listeners
     */
    function setupNetworkListeners() {
        window.addEventListener('online', () => {
            console.log('Connection restored - Back online');
            isOnline = true;
            updateOfflineIndicator();
            
            // Show notification
            showSyncMessage('Connection restored! You can now sync your offline transactions.', 'success');
        });

        window.addEventListener('offline', () => {
            console.log('Connection lost - Going offline');
            isOnline = false;
            updateOfflineIndicator();
            
            // Show notification
            showSyncMessage('You are now offline. Transactions will be saved locally.', 'info');
        });
    }

    /**
     * Initialize the offline handler
     */
    async function init() {
        try {
            await initDB();
            setupNetworkListeners();
            updateOfflineIndicator();
            
            // Setup sync button
            const syncButton = document.getElementById('sync-button');
            if (syncButton) {
                syncButton.addEventListener('click', syncTransactions);
            }

            console.log('Offline handler initialized successfully');
        } catch (error) {
            console.error('Failed to initialize offline handler:', error);
        }
    }

    // Public API
    return {
        init,
        saveOfflineTransaction,
        getUnsyncedTransactions,
        getUnsyncedCount,
        syncTransactions,
        updateOfflineIndicator,
        isOnline: () => isOnline
    };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => OfflineHandler.init());
} else {
    OfflineHandler.init();
}


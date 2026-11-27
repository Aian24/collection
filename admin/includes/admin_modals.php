<?php
// Shared Logout, Profile, and Settings Modals for Admin Pages
$currentPageName = basename($_SERVER['PHP_SELF']);
$profilePhotoSrc = (isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo']))
    ? htmlspecialchars($_SESSION['profile_photo'])
    : 'img/undraw_profile.svg';
?>
<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt text-danger mr-2"></i>Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger" href="../logout.php"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel"><i class="fas fa-user-circle text-primary mr-2"></i>Edit Profile</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($currentPageName); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="mb-4">
                                <img id="profilePreview" class="img-profile rounded-circle"
                                    src="<?php echo $profilePhotoSrc; ?>"
                                    style="width: 170px; height: 170px; object-fit: cover;">
                                <div class="mt-3">
                                    <label class="btn btn-primary btn-sm" style="cursor:pointer;">
                                        <i class="fas fa-upload mr-1"></i> Upload Photo
                                        <input type="file" name="profile_photo" id="profilePhotoInput"
                                            accept="image/*" style="display: none;" onchange="if(typeof previewImage==='function'){previewImage(this);}">
                                    </label>
                                </div>
                            </div>
                            <div class="text-muted small">
                                <p>Recommended: Square image<br>Max size: 5MB</p>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                                        <i class="fas fa-user mr-1"></i> Basic Info
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                        <i class="fas fa-lock mr-1"></i> Security
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content mt-3" id="profileTabContent">
                                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>First Name</label>
                                                <input type="text" class="form-control" name="fname"
                                                    value="<?php echo isset($_SESSION['fname']) ? htmlspecialchars($_SESSION['fname']) : ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Last Name</label>
                                                <input type="text" class="form-control" name="lname"
                                                    value="<?php echo isset($_SESSION['lname']) ? htmlspecialchars($_SESSION['lname']) : ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" class="form-control" name="username"
                                            value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label>Branch</label>
                                        <input type="text" class="form-control" name="branch"
                                            value="<?php echo isset($_SESSION['branch']) ? htmlspecialchars($_SESSION['branch']) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="security" role="tabpanel">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="current_password">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" onclick="if(typeof togglePassword==='function'){togglePassword(this);}">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="new_password">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" onclick="if(typeof togglePassword==='function'){togglePassword(this);}">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">
                                            Password must be at least 8 characters long and contain uppercase, lowercase, and numbers
                                        </small>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label>Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="confirm_password">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" onclick="if(typeof togglePassword==='function'){togglePassword(this);}">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel"><i class="fas fa-sliders-h text-primary mr-2"></i>Appearance & Settings</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6 class="settings-section-title mb-3 font-weight-bold text-uppercase small text-muted">
                    <i class="fas fa-paint-brush mr-2"></i> Appearance
                </h6>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="darkModeToggle">
                                <label class="custom-control-label font-weight-bold" for="darkModeToggle">Dark Mode</label>
                            </div>
                            <small class="form-text text-muted">Switch between light and dark theme across all admin pages</small>
                        </div>
                        <div class="form-group mb-0">
                            <label for="fontSize" class="font-weight-bold small">Font Size</label>
                            <select class="form-control" id="fontSize">
                                <option value="small">Small</option>
                                <option value="medium" selected>Medium</option>
                                <option value="large">Large</option>
                            </select>
                            <small class="form-text text-muted">Change the text size across the application</small>
                        </div>
                    </div>
                </div>

                <h6 class="settings-section-title mb-3 font-weight-bold text-uppercase small text-muted">
                    <i class="fas fa-bell mr-2"></i> Notifications
                </h6>
                <div class="card mb-0">
                    <div class="card-body">
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enableNotifications" checked>
                                <label class="custom-control-label font-weight-bold" for="enableNotifications">Enable Notifications</label>
                            </div>
                            <small class="form-text text-muted">Receive alerts about new transactions</small>
                        </div>
                        <div class="form-group mb-0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="notificationSound" checked>
                                <label class="custom-control-label font-weight-bold" for="notificationSound">Notification Sound</label>
                            </div>
                            <small class="form-text text-muted">Play sound for new notifications</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveSettings"><i class="fas fa-check mr-1"></i> Save Settings</button>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof previewImage !== 'function') {
    function previewImage(input) {
        if (input.files && input.files[0]) {
            if (input.files[0].size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                input.value = '';
                return;
            }
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(input.files[0].type)) {
                alert('Only JPG, PNG & GIF files are allowed');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('profilePreview');
                if (preview) preview.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
}
if (typeof togglePassword !== 'function') {
    function togglePassword(button) {
        const input = button.closest('.input-group').querySelector('input');
        const icon = button.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
}
</script>


<aside id="sidebar" class="w-64 bg-green-500 text-white flex flex-col shadow-lg transition-all duration-300 h-screen">
  <div class="flex items-center justify-between p-6 border-b border-green-600">
    <a href="../manage_profile.php" class="flex items-center space-x-2">
      <span class="material-icons text-3xl">account_circle</span>
      <span class="font-bold text-xl sidebar-text"><?= htmlspecialchars($user['username'] ?? 'User') ?></span>
    </a>
    <button id="toggleSidebar" class="material-icons cursor-pointer">chevron_left</button>
  </div>

  <nav class="flex-1 overflow-y-auto px-2 py-6 space-y-2">
    <a href="../dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons">dashboard</span><span class="ml-3 sidebar-text">Dashboard</span>
    </a>
    <a href="../officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Barangay Officials</span>
    </a>
    <a href="../residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Residents</span>
    </a>
    <a href="../households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">home</span><span class="sidebar-text">Household</span>
    </a>

    <?php if($role === 'admin'): ?>
      <div class="mt-4">
        <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Community</span>
        <a href="../announcements.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
        </a>
        <a href="../news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">article</span><span class="sidebar-text">News & Updates</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="mt-4">
      <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Certificate Management</span>
      <a href="../certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">assignment</span><span class="sidebar-text">Certificate Requests</span>
      </a>
      <a href="../certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">person_add</span><span class="sidebar-text">Walk-in Requests</span>
      </a>
    </div>

    <div class="mt-4">
      <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Blotter</span>
      <a href="../blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">gavel</span><span class="sidebar-text">Blotter Records</span>
      </a>
    </div>

    <a href="../reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="mt-4">
      <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">ID Management</span>
      <a href="#" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">ID Requests</span>
      </a>
      <a href="#" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">elderly</span><span class="sidebar-text">Senior / PWD / Solo Parent</span>
      </a>
    </div>

    <?php if($role === 'admin'): ?>
      <div class="mt-4">
        <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">User Management</span>
        <a href="../user_manage/user_management.php" class="flex items-center px-4 py-3 rounded bg-green-600 mt-1 transition-colors">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
        </a>
        <a href="../user_manage/settings.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">settings</span><span class="sidebar-text">Settings</span>
        </a>

      </div>
    <?php endif; ?>

    <a href="../../logout.php" class="flex items-center px-4 py-3 rounded hover:bg-red-600 transition-colors mt-1">
      <span class="material-icons mr-3">logout</span><span class="sidebar-text">Logout</span>
    </a>
  </nav>
</aside>
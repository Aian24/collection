<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title id="pageTitle"></title>
  <link rel="stylesheet" href="navadmin.css">
  <script src="nav.js"> </script>
  <script src="https://cdn.lordicon.com/lordicon-1.1.0.js"></script>
</head>

<body>
  <div id="nav-bar" style="margin-left:-.9%;">
    <input id="nav-toggle" type="checkbox" />
    <div id="nav-header"><a id="nav-title" href="#"><img style="width:185px; height:150px; margin-top:15%; margin-left:8%;" src="images/adminimage.png"></a>
    </div>

    <div id="nav-content">
      <div class="nav-button"><a href="admin_page.php"> <lord-icon src="https://cdn.lordicon.com/pcllgpqm.json"
            trigger="hover" stroke="bold" colors="primary:#104891,secondary:#ebe6ef,tertiary:#104891"
            style="width:50px;height:50px">
          </lord-icon></a> <a href="admin_page.php"> <span style="font-weight:900;">Dashboard</span> </a>
      </div>

      <div class="nav-button"><a href="dailyreport_admin.php"> <lord-icon src="https://cdn.lordicon.com/veoexymv.json"
            trigger="hover" stroke="bold" colors="primary:#121331,secondary:#1663c7,tertiary:#ebe6ef"
            style="width:50px;height:50px">
          </lord-icon></a> <a href="dailyreport_admin.php"> <span style="font-weight:900;">Report</span> </a>
      </div>

      <div class="nav-button"> <a href="users.php"> <lord-icon src="https://cdn.lordicon.com/mebvgwrs.json"
            trigger="hover" style="width:50px;height:50px">
          </lord-icon></a> <a href="users.php"><span style="font-weight:900;">Users</span></a>
      </div>

      <div class="nav-button"> <a href="adduser.php"> <lord-icon src="https://cdn.lordicon.com/piolrlvu.json"
            trigger="hover" style="width:50px;height:50px">
          </lord-icon></a> <a href="adduser.php"><span style="font-weight:900;">Add User</span></a>
      </div>

      <div class="nav-button"><a href="update_tenants.php"> <lord-icon src="https://cdn.lordicon.com/abaxrbtq.json"
            trigger="hover" colors="primary:#104891,secondary:#121331" style="width:50px;height:50px">
          </lord-icon></a> <a href="update_tenants.php"> <span style="font-weight:900;">Update Tenants</span> </a>
      </div>

      <div class="nav-button"><a href="addtenant.php"> <lord-icon src="https://cdn.lordicon.com/pdsourfn.json"
            trigger="hover" colors="primary:#121331,secondary:#4030e8,tertiary:#3080e8" style="width:50px;height:50px">
          </lord-icon></a> <a href="addtenant.php"> <span style="font-weight:900;">Add New Tenant</span> </a>
      </div>

      <div class="nav-button"><a href="analytics.php"> <lord-icon src="https://cdn.lordicon.com/eodavnff.json"
            trigger="hover" stroke="bold" colors="primary:#104891,secondary:#1663c7,tertiary:#1663c7,quaternary:#4030e8"
            style="width:50px;height:50px">
          </lord-icon></a> <a href="analytics.php"> <span style="font-weight:900;">Analytics</span> </a>
      </div>


      <div class="nav-button"><a href="display_contactus.php"> <lord-icon src="https://cdn.lordicon.com/nqisoomz.json"
            trigger="hover" colors="primary:#121331,secondary:#ebe6ef,tertiary:#4bb3fd,quaternary:#c71f16"
            style="width:50px;height:50px">
          </lord-icon></a> <a href="display_contactus.php"> <span style="font-weight:900;">Contact Us</span> </a>
      </div>

      <div class="nav-button">
        <lord-icon src="https://cdn.lordicon.com/yfgqmumn.json" trigger="hover"
          colors="primary:#2516c7,secondary:#ebe6ef,tertiary:#104891,quaternary:#9cc2f4,quinary:#2516c7,senary:#3080e8"
          style="width:50px;height:50px">
        </lord-icon>

        <form id="branchForm" method="post">
          <select style="font-weight:900;" id="branch" name="branch" required onchange="changeBranch()">
            <option value="" disabled selected>Select Branch</option>
            <option value="iMall Antipolo">iMall Antipolo</option>
            <option value="iMall Canlubang">iMall Canlubang</option>
            <option value="iMall Camarin">iMall Camarin</option>
            <option value="iMall Famy">iMall Famy</option>
            <option value="Cogeo Town Plaza">Cogeo Town Plaza</option>
            <option value="APM Commercial">APM Commercial</option>
            <option value="CITI Centre">CITI Centre</option>
          </select>
        </form>
      </div>

      <div id="nav-content-highlight"></div>
    </div>
    <input id="nav-footer-toggle" type="checkbox" />
    <div id="nav-footer">
      <div id="nav-footer-heading">
        <a href="logout.php"> <lord-icon src="https://cdn.lordicon.com/dnggaxtw.json" trigger="hover" stroke="bold"
            colors="primary:#121331,secondary:#4bb3fd,tertiary:#66a1ee" style="width:50px;height:50px;left:50px;">
          </lord-icon></a>
        <div id="nav-footer-titlebox"><a id="nav-footer-title" href="logout.php" target="_self"
            style="font-weight:900; margin-left:40px;">Logout</a>
        </div>

      </div>
    </div>
  </div>


  <script>
    // Set the document title to the current date in the format "November 20, 2023"
    var options = { year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('pageTitle').innerText = new Date().toLocaleDateString('en-US', options);
  </script>

</body>

</html>
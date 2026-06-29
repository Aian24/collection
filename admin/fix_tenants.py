import os
import re

files = ['tenants.php', 'tenantsacc.php', 'tenantsapm.php']

for file in files:
    if not os.path.exists(file):
        continue
    
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    old_code = r"\$totalDaily\s*=\s*array_sum\(array_column\(\$data,\s*'daily'\)\);\s*\$totalRentBal\s*=\s*array_sum\(array_column\(\$data,\s*'rentbal'\)\);\s*\$totalRunningBal\s*=\s*array_sum\(array_column\(\$data,\s*'runningbal'\)\);"
    
    new_code = """$totalDaily = 0;
$totalRentBal = 0;
$totalRunningBal = 0;
foreach ($data as $row) {
    $totalDaily += (float)($row['daily'] ?? 0);
    $totalRentBal += (float)($row['rentbal'] ?? 0);
    $totalRunningBal += (float)($row['runningbal'] ?? 0);
}"""
    
    if re.search(old_code, content):
        content = re.sub(old_code, new_code, content)
        with open(file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Fixed {file}")
    else:
        print(f"Pattern not found in {file}")

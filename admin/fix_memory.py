import re
import os

files = ['collection.php', 'collectionacc.php', 'collectionapm.php', 'collectiononly.php']

for f in files:
    if not os.path.exists(f): 
        print(f"Skipping {f}, not found.")
        continue
    
    with open(f, 'r', encoding='utf-8') as file:
        content = file.read()
        
    # Fix the missing 'total_' prefix on variables
    content = content.replace("'tables_chairs' => number_format($tables_chairs, 2)", "'tables_chairs' => number_format($total_tables_chairs, 2)")
    content = content.replace("'overnight_works' => number_format($overnight_works, 2)", "'overnight_works' => number_format($total_overnight_works, 2)")
    content = content.replace("'vendo_sale' => number_format($vendo_sale, 2)", "'vendo_sale' => number_format($total_vendo_sale, 2)")
    content = content.replace("'zumba' => number_format($zumba, 2)", "'zumba' => number_format($total_zumba, 2)")

    # Replace fetch_all with nothing since we will put the while loop in the first foreach
    old_start = r"\$allRows\s*=\s*mysqli_fetch_all\(\$result,\s*MYSQLI_ASSOC\);\s*mysqli_free_result\(\$result\);\s*// Free the result set memory"
    content = re.sub(old_start, "", content)
    
    content = re.sub(r"foreach\s*\(\$allRows\s+as\s+\$row\)\s*\{", "while ($row = mysqli_fetch_assoc($result)) {", content, count=1)
    
    # Find the end of the first loop and start of the second loop
    mid_pattern = r"\}\s*// Process the fetched data AGAIN to apply PHP-side filtering and calculate DISPLAYED totals\s*// \(totals for the \*filtered\* data\)\s*foreach\s*\(\$allRows\s+as\s+\$row\)\s*\{"
    
    if re.search(mid_pattern, content):
        content = re.sub(mid_pattern, "\n    // --- CONTINUING TO PART 2 (Filtered Data Processing) ---\n", content)
        print(f"Successfully modified {f}")
    else:
        print(f"Warning: Mid pattern not found in {f}")

    with open(f, 'w', encoding='utf-8') as file:
        file.write(content)
        
print("Done!")

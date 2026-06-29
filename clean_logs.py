import re

filename = r"c:\xampp\htdocs\collection\void_transaction.php"

with open(filename, "r", encoding="utf-8") as f:
    content = f.read()

# Replace any error_log( that is not already commented out
# Find error_log( preceded by spaces/tabs, but NOT by //
# Actually, the simplest way is to split by lines, check if error_log is in line and doesn't have // before it, then add //
lines = content.split('\n')
new_lines = []
for line in lines:
    stripped = line.lstrip()
    if stripped.startswith("error_log("):
        new_lines.append(line.replace("error_log(", "// error_log("))
    else:
        new_lines.append(line)

with open(filename, "w", encoding="utf-8") as f:
    f.write("\n".join(new_lines))

print("Cleaned up void_transaction.php")

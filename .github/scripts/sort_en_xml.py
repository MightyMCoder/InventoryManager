import xml.etree.ElementTree as ET

file = 'languages/en.xml'
tree = ET.parse(file)
root = tree.getroot()

# Filter only <string> elements
string_elements = [e for e in root.findall('string')]

# Sort them by name attribute
sorted_strings = sorted(string_elements, key=lambda e: e.attrib.get('name', ''))

# Remove all <string> elements
for e in string_elements:
    root.remove(e)

# Append sorted <string> elements back
for e in sorted_strings:
    root.append(e)

# Write back to file
ET.indent(tree, space="  ", level=0)
tree.write(file, encoding='utf-8', xml_declaration=True)
print("en.xml successfully sorted.")
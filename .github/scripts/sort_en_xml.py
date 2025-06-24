import xml.etree.ElementTree as ET

file = 'languages/en.xml'
tree = ET.parse(file)
root = tree.getroot()
root[:] = sorted(root, key=lambda e: e.attrib.get('name',''))
ET.indent(tree, space="  ")
tree.write(file, encoding='utf-8', xml_declaration=True)
print("en.xml successfully sorted.")

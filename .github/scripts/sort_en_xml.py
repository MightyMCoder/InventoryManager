from lxml import etree

file = 'languages/en.xml'

# Parse XML with comments preserved
parser = etree.XMLParser(remove_blank_text=False)
tree = etree.parse(file, parser)
root = tree.getroot()

new_children = []
buffer = []
inside_block = False

for elem in root.iterchildren():
    if isinstance(elem, etree._Comment):
        # Flush previous block
        if buffer:
            # Sort buffer by name attribute
            sorted_buffer = sorted(buffer, key=lambda e: e.attrib.get('name', ''))
            new_children.extend(sorted_buffer)
            buffer.clear()
        # Add the comment itself
        new_children.append(elem)
        inside_block = True  # Next strings are considered a new block
    elif elem.tag == 'string':
        if inside_block:
            buffer.append(elem)
        else:
            new_children.append(elem)
    else:
        # Flush buffer before non-string non-comment
        if buffer:
            sorted_buffer = sorted(buffer, key=lambda e: e.attrib.get('name', ''))
            new_children.extend(sorted_buffer)
            buffer.clear()
        new_children.append(elem)
        inside_block = False

# Flush any remaining buffer
if buffer:
    sorted_buffer = sorted(buffer, key=lambda e: e.attrib.get('name', ''))
    new_children.extend(sorted_buffer)

# Replace children in root
root[:] = new_children

# Write back
tree.write(file, encoding='utf-8', xml_declaration=True, pretty_print=True)
print("en.xml successfully sorted.")

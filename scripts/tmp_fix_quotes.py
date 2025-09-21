from pathlib import Path
path = Path('docs/modularisation_notes.md')
text = path.read_text(encoding='utf-8')
text = text.replace('\\"Origines G', '"Origines G')
text = text.replace('G\u00e9ographiques"', 'Géographiques"')
text = text.replace('\"', '"')
path.write_text(text, encoding='utf-8')

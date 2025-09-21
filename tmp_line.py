from pathlib import Path
lines = Path('app/Modules/Infographie/InfographieController.php').read_text(encoding='utf-8').splitlines()
for idx in range(740, 806):
    print(f"{idx+1}: {lines[idx]}")

from __future__ import annotations

import re
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
EXCLUDED_PARTS = {".dart_tool", ".git", "build", "node_modules", "vendor"}
UNRESOLVED_MARKER = re.compile(r"\b(?:FIXME|TBD|TODO)\b")
MARKDOWN_LINK = re.compile(r"\[[^\]]+\]\((?!https?://|mailto:|#)([^)]+)\)")


def markdown_files() -> list[Path]:
    return sorted(
        path
        for path in ROOT.rglob("*.md")
        if not EXCLUDED_PARTS.intersection(path.relative_to(ROOT).parts)
    )


def lint(path: Path) -> list[str]:
    issues: list[str] = []
    lines = path.read_text(encoding="utf-8").splitlines()
    fence_open = False
    previous_heading_level = 0

    for number, line in enumerate(lines, start=1):
        if "\t" in line:
            issues.append(f"{path}:{number}: tab character")

        if line.endswith(" ") and not line.endswith("  "):
            issues.append(f"{path}:{number}: trailing whitespace")

        if line.startswith("```"):
            fence_open = not fence_open
            continue

        if fence_open:
            continue

        heading = re.match(r"^(#{1,6})\s+\S", line)
        if heading:
            level = len(heading.group(1))
            if previous_heading_level and level > previous_heading_level + 1:
                issues.append(f"{path}:{number}: heading level jumps")
            previous_heading_level = level

        if UNRESOLVED_MARKER.search(line):
            issues.append(f"{path}:{number}: unresolved work marker")

        for target in MARKDOWN_LINK.findall(line):
            clean_target = target.strip("<>").split("#", maxsplit=1)[0]
            if clean_target and not (path.parent / clean_target).exists():
                issues.append(f"{path}:{number}: broken relative link {target}")

    if fence_open:
        issues.append(f"{path}: unclosed code fence")

    return issues


def main() -> int:
    files = markdown_files()
    issues = [issue for path in files for issue in lint(path)]

    if issues:
        print("\n".join(issues))
        return 1

    print(f"Markdown check passed for {len(files)} files.")
    return 0


if __name__ == "__main__":
    sys.exit(main())

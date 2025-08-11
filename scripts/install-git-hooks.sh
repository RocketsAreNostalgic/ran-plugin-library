#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
GIT_DIR="${PROJECT_ROOT_DIR}/.git"
HOOKS_DIR="${GIT_DIR}/hooks"
SOURCE_HOOK="${PROJECT_ROOT_DIR}/scripts/git-hooks/pre-commit"
TARGET_HOOK="${HOOKS_DIR}/pre-commit"

echo "Installing pre-commit hook..."

if [[ ! -d "${GIT_DIR}" ]]; then
  echo "Error: .git directory not found at ${GIT_DIR}. Are you in a Git repository?"
  exit 1
fi

mkdir -p "${HOOKS_DIR}"

if [[ ! -f "${SOURCE_HOOK}" ]]; then
  echo "Error: Source hook not found at ${SOURCE_HOOK}"
  exit 1
fi

cp "${SOURCE_HOOK}" "${TARGET_HOOK}"
chmod +x "${TARGET_HOOK}"

echo "Pre-commit hook installed to ${TARGET_HOOK}"
echo "You can remove it by deleting that file."



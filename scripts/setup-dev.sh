#!/bin/bash

# Setup development environment for ran-plugin-lib
echo "Setting up development environment for ran-plugin-lib..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed. Please install Composer first."
    echo "Visit https://getcomposer.org/download/ for installation instructions."
    exit 1
fi

# Remove composer.lock if it exists to ensure a clean install
if [ -f "composer.lock" ]; then
    echo "Removing existing composer.lock file..."
    rm composer.lock
fi

# Remove vendor directory if it exists
if [ -d "vendor" ]; then
    echo "Removing existing vendor directory..."
    rm -rf vendor
fi

# Install dependencies
echo "Installing dependencies..."
composer update

# Check if installation was successful
if [ $? -eq 0 ]; then
    echo "Dependencies installed successfully!"

    # Attempt to install git hooks if this is a git repository
    if [ -d ".git" ]; then
        echo "Installing git pre-commit hook..."
        if composer hooks:install > /dev/null 2>&1; then
            echo "Pre-commit hook installed."
        else
            echo "Warning: Failed to install pre-commit hook (continuing)."
        fi
    else
        echo "Git repository not detected; skipping hook installation."
    fi
    echo ""
    echo "Available commands:"
    echo "  composer lint         - Run PHP CS Fixer (dry-run) and PHPCS (summary)"
    echo "  composer cs           - Fix code style with PHP CS Fixer, not as thorough as format."
    echo "  composer format       - Run PHP CS Fixer, then PHPCBF, full style enforcement, but slower."
    echo "  composer qa           - Run code style check, PHPCS summary, and tests, checks all files no code changes"
    echo "  composer qa:ci        - Run code style check, full PHPCS, and tests with coverage"
    echo "  composer hooks:install - Install pre-commit hook"
    echo ""
    echo "See composer.json for all available commands."
    echo ""
    echo "Recommended workflow:"
    echo "  - While coding: run 'composer cs' periodically to quickly fix style."
    echo "  - Commit: rely on the pre-commit hook; optionally run 'composer lint' first."
    echo "  - Before push/PR: run 'composer qa' (checks + tests)."
    echo "  - Repo-wide normalize (infrequent): run 'composer format'."
    echo ""
    echo "Pre-commit hook covers (staged PHP files only):"
    echo "  1) PHP CS Fixer dry-run; auto-fix and re-stage if needed."
    echo "  2) PHPCS auto-fix (PHPCBF) via project runner; re-stage."
    echo "  3) PHPCS verification; blocks commit if violations remain."
    echo "Setup complete! You're ready to start development."
else
    echo "Error: Failed to install dependencies."
    echo "Please check the error messages above and try again."
    exit 1
fi

#!/bin/bash

# Setup development environment for TNY Google Key plugin
echo "Setting up development environment for TNY Google Key plugin..."

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
    echo ""
    echo "Available commands:"
	echo "  composer format     - Format code according to WordPress standards (peforms all steps in the correct order)"
    echo "  composer lint       - Check PHP syntax"
    echo "  composer phpcs      - Check coding standards (summary)"
    echo "  composer phpcs-full - Check coding standards (detailed)"
    echo "  composer phpcbf     - Fix coding standards automatically"
    echo "  composer cs-fix     - Fix code style with PHP CS Fixer"
    echo "  composer cs-check   - Check code style with PHP CS Fixer"
    echo ""
    echo "Setup complete! You're ready to start development."
else
    echo "Error: Failed to install dependencies."
    echo "Please check the error messages above and try again."
    exit 1
fi

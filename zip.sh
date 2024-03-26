#!/bin/bash

# Define the directory to be zipped
dir_to_zip="."

# Define the output zip file
output_zip_file="smspby_whmcs.zip"

# Create a temporary directory
temp_dir=$(mktemp -d)

# Copy the directory to the temporary directory
cp -R "$dir_to_zip"/* "$temp_dir"

# Remove the files you don't want to include
find "$temp_dir" -type f \( -name "dbcompatfunctions.php" -o -name "hookfunctions.php" -o -name "dbfunctions.php" -o -name "zip.sh" -o -name ".DS_Store" -o -name ".gitignore" -o -name "$output_zip_file" \) -delete

# Save the current working directory
old_pwd=$(pwd)

# Change to the temporary directory
cd "$temp_dir"

# Remove old zip file if exists 
rm -f "$old_pwd/$output_zip_file"

# Create the zip file
zip -r "$old_pwd/$output_zip_file" *

# Change back to the original working directory
cd "$old_pwd"

# Remove the temporary directory
rm -rf "$temp_dir"
#!/bin/bash 

# --------------------------------------------------------
# This script backs up current deployment and creates new deployment folder.
# --------------------------------------------------------

# Remove if previous deployment folder exists
rm -rf /var/www/landconnect/prev-deployment

# Backup current deployment 
mv /var/www/landconnect/deployment /var/www/landconnect/prev-deployment

# Create new deployment folder and make deploy-user owner
mkdir /var/www/landconnect/deployment

chown deploy-user:www-data /var/www/landconnect/deployment

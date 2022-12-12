#!/bin/bash 

# Go to deployment directory
cd /var/www/landconnect/deployment

# Remove if there is an existing environment configuration file
rm -f .env

# Link the environment configuration to shared configuration file
ln -s /var/www/landconnect/config/.env .env
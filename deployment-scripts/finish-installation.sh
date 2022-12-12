#!/bin/bash

# ----------------------------------------------------------------
# This script makes final arrangements and restart nginx 
# to complete the installation.
# ----------------------------------------------------------------

# Load environment variables
source /etc/profile

# Print deployment info
DEPLOYMENT_TIME=$( date -u "+%Y/%m/%d %H:%M:%S" )
echo "Deployment finished at: "$DEPLOYMENT_TIME" UTC" > /var/www/landconnect/deployment/deployment_time.txt

# Arrange folder permissions
chown -R deploy-user:www-data /var/www/landconnect/deployment
chmod -R 775 /var/www/landconnect/deployment

service nginx restart
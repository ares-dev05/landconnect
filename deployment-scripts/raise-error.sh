#!/bin/bash 

# This script is used to raise an error to test AWS CodeDeploy automated rollback.
# To test, please define a hook to run this script in your appspec.yaml file.

echo "Raising error to test rollback..."
exit 1